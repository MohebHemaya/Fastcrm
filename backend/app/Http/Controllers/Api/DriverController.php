<?php

namespace App\Http\Controllers\Api;

use App\Enums\DriverStatus;
use App\Exceptions\InvalidDriverStatusTransitionException;
use App\Http\Requests\ChangeDriverStatusRequest;
use App\Http\Requests\StoreDriverRequest;
use App\Http\Requests\UpdateDriverRequest;
use App\Http\Resources\DriverResource;
use App\Models\Driver;
use App\Models\WhatsappGroup;
use App\Services\Audit;
use App\Services\DriverStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverController extends ApiController
{
    /**
     * Display a listing of drivers with search, filters, and sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'pool' => ['nullable', 'string', 'in:new,fleet'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'],
            'group_id' => ['nullable', 'integer'],
            'import_id' => ['nullable', 'integer'],
            'sort_by' => ['nullable', 'string', 'max:50'],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc,ASC,DESC'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Driver::with(['whatsappGroups', 'metrics'])->withCount('currentAnswers');

        // Pool filtering: new (leads/onboarding) vs fleet (active/regular drivers)
        if ($request->query('pool') === 'new') {
            $query->whereIn('status', [
                DriverStatus::NEW,
                DriverStatus::QUESTIONNAIRE_IN_PROGRESS,
                DriverStatus::INVITED,
                DriverStatus::INTERESTED,
            ]);
        } elseif ($request->query('pool') === 'fleet') {
            $query->whereNotIn('status', [
                DriverStatus::NEW,
                DriverStatus::QUESTIONNAIRE_IN_PROGRESS,
            ]);
        }

        // Search by phone or name
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('phone', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Filter by status (single or comma-separated)
        if ($request->filled('status')) {
            $statuses = array_filter(array_map('trim', explode(',', $request->query('status'))));
            if (count($statuses) > 1) {
                $query->whereIn('status', $statuses);
            } elseif (count($statuses) === 1) {
                $query->where('status', $statuses[0]);
            }
        }

        // Filter by WhatsApp group
        if ($request->filled('group_id')) {
            $groupId = $request->query('group_id');
            $query->whereHas('whatsappGroups', function ($q) use ($groupId) {
                $q->where('whatsapp_groups.id', $groupId);
            });
        }

        // Filter by import ID
        if ($request->filled('import_id')) {
            $importId = $request->query('import_id');
            $query->whereHas('whatsappGroups', function ($q) use ($importId) {
                $q->where('driver_group.import_id', $importId);
            });
        }

        // Sorting
        $metricSorts = ['trips_completed', 'cancellations', 'no_shows', 'late_count', 'avg_rating', 'last_trip_at'];
        $allowedSorts = array_merge(['id', 'name', 'phone', 'status', 'created_at'], $metricSorts);
        $sortBy = in_array($request->query('sort_by'), $allowedSorts, true)
            ? $request->query('sort_by')
            : 'id';
        $sortDir = strtolower((string) $request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (in_array($sortBy, $metricSorts, true)) {
            $query->leftJoin('driver_metrics', 'drivers.id', '=', 'driver_metrics.driver_id')
                ->select('drivers.*')
                ->orderBy("driver_metrics.{$sortBy}", $sortDir);
        } else {
            $query->orderBy("drivers.{$sortBy}", $sortDir);
        }

        $perPage = min(max((int) $request->query('per_page', 20), 5), 100);
        $paginated = $query->paginate($perPage);

        return $this->paginated(
            DriverResource::collection($paginated),
            $paginated
        );
    }

    /**
     * Store a newly created driver manually.
     */
    public function store(StoreDriverRequest $request): JsonResponse
    {
        $driver = Driver::create([
            'phone' => $request->normalizedPhone,
            'name' => $request->name,
            'status' => DriverStatus::NEW,
            'notes' => $request->notes,
        ]);

        if ($request->filled('group_name')) {
            $group = WhatsappGroup::firstOrCreate([
                'name' => trim((string) $request->group_name),
            ]);
            $driver->whatsappGroups()->attach($group->id, [
                'first_seen_at' => now(),
            ]);
        }

        Audit::log('driver_created', $driver, [
            'source' => 'manual',
            'user_id' => $request->user()->id,
        ]);

        $driver->load('whatsappGroups');

        return $this->success(
            new DriverResource($driver),
            'تمت إضافة السائق بنجاح.',
            201
        );
    }

    /**
     * Display driver details with status histories and WhatsApp groups.
     */
    public function show(Driver $driver): JsonResponse
    {
        $driver->load(['whatsappGroups', 'statusHistories.changer', 'metrics', 'workOrderRecipients.workOrder']);

        return $this->success(new DriverResource($driver));
    }

    /**
     * Update driver's editable details (name, notes).
     */
    public function update(UpdateDriverRequest $request, Driver $driver): JsonResponse
    {
        $driver->update($request->only('name', 'notes'));

        Audit::log('driver_updated', $driver, [
            'user_id' => $request->user()->id,
        ]);

        $driver->load(['whatsappGroups', 'statusHistories.changer']);

        return $this->success(
            new DriverResource($driver),
            'تم تحديث بيانات السائق بنجاح.'
        );
    }

    /**
     * Change driver status through the state machine.
     */
    public function changeStatus(ChangeDriverStatusRequest $request, Driver $driver): JsonResponse
    {
        try {
            $newStatus = DriverStatus::from($request->status);
            DriverStatusService::transition(
                $driver,
                $newStatus,
                $request->reason,
                $request->user()
            );

            $driver->load(['whatsappGroups', 'statusHistories.changer']);

            return $this->success(
                new DriverResource($driver),
                'تم تغيير حالة السائق بنجاح.'
            );
        } catch (InvalidDriverStatusTransitionException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Remove the specified driver from storage (Soft delete).
     */
    public function destroy(Request $request, Driver $driver): JsonResponse
    {
        $driver->delete();

        Audit::log('driver_deleted', $driver, [
            'user_id' => $request->user()?->id,
            'driver_phone' => $driver->phone,
            'driver_name' => $driver->name,
        ]);

        return $this->success(
            null,
            'تم حذف السائق بنجاح.'
        );
    }
}

