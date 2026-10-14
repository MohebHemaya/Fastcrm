<?php

namespace App\Http\Controllers\Api;

use App\Enums\WorkOrderRecipientStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\DriverResource;
use App\Http\Resources\WorkOrderRecipientResource;
use App\Http\Resources\WorkOrderResource;
use App\Models\WorkOrder;
use App\Models\WorkOrderRecipient;
use App\Services\Audit;
use App\Services\DriverMatcher;
use App\Services\MessageSender;
use App\Services\WorkOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class WorkOrderController extends Controller
{
    public function __construct(
        protected WorkOrderService $workOrderService,
        protected DriverMatcher $driverMatcher,
        protected MessageSender $messageSender
    ) {}

    /**
     * List work orders with filtering, search, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'search' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = WorkOrder::query()
            ->with(['creator', 'filters.question.currentVersion'])
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('from_location', 'like', "%{$search}%")
                    ->orWhere('to_location', 'like', "%{$search}%")
                    ->orWhere('cargo_type', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('scheduled_at', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('scheduled_at', '<=', $request->query('date_to'));
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $orders = $query->paginate($perPage);

        return response()->json([
            'data' => WorkOrderResource::collection($orders->items()),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
            'message' => 'تم استرجاع أوامر العمل بنجاح',
        ]);
    }

    /**
     * Store a new work order draft.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_location' => ['nullable', 'string', 'max:255'],
            'to_location' => ['nullable', 'string', 'max:255'],
            'scheduled_at' => ['nullable', 'date'],
            'scheduled_dispatch_at' => ['nullable', 'date'],
            'offer_expires_in_minutes' => ['nullable', 'integer', 'min:1'],
            'cargo_type' => ['nullable', 'string', 'max:255'],
            'pay_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'custom_message' => ['nullable', 'string', 'max:5000'],
            'trips_needed' => ['required', 'integer', 'min:1'],
            'backup_needed' => ['nullable', 'integer', 'min:0'],
            'send_strategy' => ['nullable', 'string', Rule::in(['all', 'staged'])],
            'batch_size' => ['nullable', 'integer', 'min:1'],
            'batch_interval_minutes' => ['nullable', 'integer', 'min:1'],
            'exclude_same_day_confirmed' => ['nullable', 'boolean'],
            'min_avg_rating' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'max_no_shows' => ['nullable', 'integer', 'min:0'],
            'last_trip_within_days' => ['nullable', 'integer', 'min:1'],
            'include_unrated' => ['nullable', 'boolean'],
            'target_group_id' => ['nullable', 'integer', Rule::exists('whatsapp_groups', 'id')],
            'driver_experience' => ['nullable', 'string', Rule::in(['all', 'new_only', 'experienced_only'])],
            'filters' => ['nullable', 'array'],
            'filters.*.question_id' => ['required_with:filters', 'integer', Rule::exists('questions', 'id')->where('is_filter', true)],
            'filters.*.operator' => ['required_with:filters', 'string', Rule::in(['in', 'eq', 'gte', 'lte'])],
            'filters.*.value' => ['required_with:filters'],
        ], [
            'trips_needed.required' => 'عدد الرحلات الأساسية المطلوبة إلزامي.',
            'trips_needed.min' => 'يجب أن يكون عدد الرحلات المطلوبة 1 على الأقل.',
            'filters.*.question_id.exists' => 'أحد الأسئلة المحددة في الفلاتر غير صالح أو غير متاح كفلتر.',
        ]);

        $order = $this->workOrderService->createDraft($validated, Auth::id());

        return response()->json([
            'data' => new WorkOrderResource($order),
            'meta' => null,
            'message' => 'تم إنشاء مسودة أمر العمل بنجاح',
        ], 201);
    }

    /**
     * Show a work order with details and counts.
     */
    public function show(WorkOrder $workOrder): JsonResponse
    {
        $workOrder->load(['filters.question.currentVersion', 'creator']);

        $recipientCounts = WorkOrderRecipient::where('work_order_id', $workOrder->id)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return response()->json([
            'data' => new WorkOrderResource($workOrder),
            'meta' => [
                'recipient_counts' => [
                    'all' => array_sum($recipientCounts),
                    'confirmed' => $recipientCounts[WorkOrderRecipientStatus::CONFIRMED->value] ?? 0,
                    'backup' => $recipientCounts[WorkOrderRecipientStatus::BACKUP->value] ?? 0,
                    'declined' => $recipientCounts[WorkOrderRecipientStatus::DECLINED->value] ?? 0,
                    'sent' => $recipientCounts[WorkOrderRecipientStatus::SENT->value] ?? 0,
                    'too_late' => $recipientCounts[WorkOrderRecipientStatus::TOO_LATE->value] ?? 0,
                    'failed' => $recipientCounts[WorkOrderRecipientStatus::FAILED->value] ?? 0,
                    'cancelled' => $recipientCounts[WorkOrderRecipientStatus::CANCELLED->value] ?? 0,
                ],
            ],
            'message' => 'تم استرجاع تفاصيل أمر العمل بنجاح',
        ]);
    }

    /**
     * Update an existing draft work order.
     */
    public function update(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $validated = $request->validate([
            'from_location' => ['nullable', 'string', 'max:255'],
            'to_location' => ['nullable', 'string', 'max:255'],
            'scheduled_at' => ['nullable', 'date'],
            'scheduled_dispatch_at' => ['nullable', 'date'],
            'offer_expires_in_minutes' => ['nullable', 'integer', 'min:1'],
            'cargo_type' => ['nullable', 'string', 'max:255'],
            'pay_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'custom_message' => ['nullable', 'string', 'max:5000'],
            'trips_needed' => ['sometimes', 'required', 'integer', 'min:1'],
            'backup_needed' => ['nullable', 'integer', 'min:0'],
            'send_strategy' => ['nullable', 'string', Rule::in(['all', 'staged'])],
            'batch_size' => ['nullable', 'integer', 'min:1'],
            'batch_interval_minutes' => ['nullable', 'integer', 'min:1'],
            'exclude_same_day_confirmed' => ['nullable', 'boolean'],
            'min_avg_rating' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'max_no_shows' => ['nullable', 'integer', 'min:0'],
            'last_trip_within_days' => ['nullable', 'integer', 'min:1'],
            'include_unrated' => ['nullable', 'boolean'],
            'filters' => ['nullable', 'array'],
            'filters.*.question_id' => ['required_with:filters', 'integer', Rule::exists('questions', 'id')->where('is_filter', true)],
            'filters.*.operator' => ['required_with:filters', 'string', Rule::in(['in', 'eq', 'gte', 'lte'])],
            'filters.*.value' => ['required_with:filters'],
        ], [
            'filters.*.question_id.exists' => 'أحد الأسئلة المحددة في الفلاتر غير صالح أو غير متاح كفلتر.',
        ]);

        $order = $this->workOrderService->updateDraft($workOrder, $validated);

        return response()->json([
            'data' => new WorkOrderResource($order),
            'meta' => null,
            'message' => 'تم تحديث مسودة أمر العمل بنجاح',
        ]);
    }

    /**
     * Send offers for a draft work order.
     */
    public function send(WorkOrder $workOrder): JsonResponse
    {
        $order = $this->workOrderService->send($workOrder);

        return response()->json([
            'data' => new WorkOrderResource($order),
            'meta' => null,
            'message' => 'تم إرسال عروض الرحلة إلى السائقين المطابقين بنجاح',
        ]);
    }

    /**
     * Cancel an active or filling work order.
     */
    public function cancel(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $order = $this->workOrderService->cancel($workOrder, $validated['reason'] ?? null);

        return response()->json([
            'data' => new WorkOrderResource($order),
            'meta' => null,
            'message' => 'تم إلغاء أمر العمل وإشعار السائقين بنجاح',
        ]);
    }

    /**
     * Duplicate a previous work order into a new draft.
     */
    public function duplicate(WorkOrder $workOrder): JsonResponse
    {
        $newOrder = $this->workOrderService->duplicate($workOrder, Auth::id());

        return response()->json([
            'data' => new WorkOrderResource($newOrder),
            'meta' => null,
            'message' => 'تم تكرار أمر العمل بنجاح كمسودة جديدة',
        ], 201);
    }

    /**
     * List recipients of a work order filtered by status tab.
     */
    public function recipients(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $query = WorkOrderRecipient::where('work_order_id', $workOrder->id)
            ->with('driver')
            ->orderBy('sequence_number', 'asc')
            ->orderBy('id', 'asc');

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('status', $request->query('status'));
        }

        $recipients = $query->paginate(50);

        return response()->json([
            'data' => WorkOrderRecipientResource::collection($recipients->items()),
            'meta' => [
                'current_page' => $recipients->currentPage(),
                'last_page' => $recipients->lastPage(),
                'total' => $recipients->total(),
            ],
            'message' => 'تم استرجاع قائمة المستلمين بنجاح',
        ]);
    }

    /**
     * Retry sending offer to a failed recipient.
     */
    public function retryRecipient(WorkOrder $workOrder, WorkOrderRecipient $recipient): JsonResponse
    {
        if ($recipient->work_order_id !== $workOrder->id) {
            abort(404);
        }

        if ($recipient->status !== WorkOrderRecipientStatus::FAILED) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'message' => 'لا يمكن إعادة الإرسال إلا للمستلمين الذين فشل إرسالهم.',
            ], 422);
        }

        $driver = $recipient->driver;
        if (! $driver) {
            abort(404);
        }

        $buttons = [
            ['id' => "order_accept:{$workOrder->id}:{$driver->id}", 'title' => 'أوافق'],
            ['id' => "order_decline:{$workOrder->id}:{$driver->id}", 'title' => 'أعتذر'],
        ];

        $driverName = $driver->name ?: 'كابتن';
        $payFormatted = $workOrder->pay_amount ? number_format((float) $workOrder->pay_amount, 2).' ج.م' : 'حسب الاتفاق';

        $scheduledFormatted = $workOrder->scheduled_at ? $workOrder->scheduled_at->format('Y-m-d H:i') : 'حسب الاتفاق';

        $this->messageSender->sendTemplate(
            driver: $driver,
            templateName: 'order_offer',
            lang: 'ar',
            params: [
                $driverName,
                $workOrder->code,
                $workOrder->from_location,
                $workOrder->to_location,
                $scheduledFormatted,
                $payFormatted,
            ],
            buttons: $buttons,
            orderId: $workOrder->id,
            body: "فرصة رحلة جديدة 🚗\nمرحبًا يا {$driverName}، تتوفر رحلة جديدة مطابقة لبياناتك:\n- رقم الطلب: {$workOrder->code}\n- خط السير: من {$workOrder->from_location} إلى {$workOrder->to_location}\n- الموعد: {$scheduledFormatted}\n- المقابل المادي: {$payFormatted}\nهل ترغب في قبول هذه الرحلة؟ (الأولوية لأسبقية الرد)"
        );

        $recipient->update([
            'status' => WorkOrderRecipientStatus::SENT,
            'sent_at' => now(),
        ]);

        Audit::log('work_order_recipient_retried', $recipient, [
            'work_order_id' => $workOrder->id,
            'driver_id' => $driver->id,
        ]);

        return response()->json([
            'data' => new WorkOrderRecipientResource($recipient),
            'meta' => null,
            'message' => 'تمت إعادة إرسال عرض الرحلة إلى السائق بنجاح',
        ]);
    }

    /**
     * Preview matching driver count and first 20 drivers without N+1.
     */
    public function matchPreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filters' => ['nullable', 'array'],
            'filters.*.question_id' => ['required_with:filters', 'integer', Rule::exists('questions', 'id')->where('is_filter', true)],
            'filters.*.operator' => ['required_with:filters', 'string', Rule::in(['in', 'eq', 'gte', 'lte'])],
            'filters.*.value' => ['required_with:filters'],
            'exclude_same_day_confirmed' => ['nullable', 'boolean'],
            'min_avg_rating' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'max_no_shows' => ['nullable', 'integer', 'min:0'],
            'last_trip_within_days' => ['nullable', 'integer', 'min:1'],
            'include_unrated' => ['nullable', 'boolean'],
            'target_group_id' => ['nullable', 'integer', Rule::exists('whatsapp_groups', 'id')],
            'driver_experience' => ['nullable', 'string', Rule::in(['all', 'new_only', 'experienced_only'])],
            'phone' => ['nullable', 'string', 'max:50'],
            'scheduled_at' => ['nullable', 'date'],
        ], [
            'filters.*.question_id.exists' => 'أحد الأسئلة المحددة في الفلاتر غير صالح أو غير متاح كفلتر.',
        ]);

        $query = $this->driverMatcher->buildQuery(
            filters: $validated['filters'] ?? [],
            excludeSameDayConfirmed: (bool) ($validated['exclude_same_day_confirmed'] ?? false),
            scheduledAt: $validated['scheduled_at'] ?? null,
            currentWorkOrderId: null,
            minAvgRating: isset($validated['min_avg_rating']) ? (float) $validated['min_avg_rating'] : null,
            maxNoShows: isset($validated['max_no_shows']) ? (int) $validated['max_no_shows'] : null,
            lastTripWithinDays: isset($validated['last_trip_within_days']) ? (int) $validated['last_trip_within_days'] : null,
            includeUnrated: (bool) ($validated['include_unrated'] ?? false),
            targetGroupId: isset($validated['target_group_id']) ? (int) $validated['target_group_id'] : null,
            driverExperience: $validated['driver_experience'] ?? null,
            phone: $validated['phone'] ?? null
        );

        $count = $query->count();

        // Eager load relationships on first 20 drivers to eliminate N+1 queries
        $drivers = $query->clone()
            ->with(['currentAnswers.question', 'currentAnswers.option', 'whatsappGroups', 'metrics'])
            ->limit(20)
            ->get();

        return response()->json([
            'data' => [
                'count' => $count,
                'drivers' => DriverResource::collection($drivers),
            ],
            'meta' => null,
            'message' => 'تم حساب عدد السائقين المطابقين بنجاح',
        ]);
    }
}
