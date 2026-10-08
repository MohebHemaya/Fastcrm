<?php

namespace App\Http\Controllers\Api;

use App\Enums\WorkOrderOutcome;
use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\WorkOrderEventResource;
use App\Http\Resources\WorkOrderRecipientResource;
use App\Http\Resources\WorkOrderResource;
use App\Models\WorkOrder;
use App\Models\WorkOrderRecipient;
use App\Services\WorkOrderFollowUpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class WorkOrderFollowUpController extends Controller
{
    public function __construct(
        protected WorkOrderFollowUpService $followUpService
    ) {}

    /**
     * Remove or cancel a confirmed driver with reason.
     */
    public function removeConfirmed(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $validated = $request->validate([
            'recipient_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'reason.required' => 'سبب إلغاء تكليف السائق إلزامي.',
        ]);

        /** @var WorkOrderRecipient $recipient */
        $recipient = WorkOrderRecipient::where('id', $validated['recipient_id'])
            ->where('work_order_id', $workOrder->id)
            ->firstOrFail();

        $order = $this->followUpService->removeConfirmedDriver(
            order: $workOrder,
            recipient: $recipient,
            reason: $validated['reason'],
            userId: Auth::id()
        );

        return response()->json([
            'data' => new WorkOrderResource($order),
            'meta' => null,
            'message' => 'تم إلغاء تكليف السائق بنجاح وتحديث عدد المقاعد الشاغرة',
        ]);
    }

    /**
     * Promote a backup driver to confirmed.
     */
    public function promoteBackup(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $validated = $request->validate([
            'recipient_id' => ['nullable', 'integer'],
        ]);

        $recipient = $this->followUpService->promoteBackup(
            order: $workOrder,
            recipientId: $validated['recipient_id'] ?? null,
            userId: Auth::id()
        );

        return response()->json([
            'data' => new WorkOrderRecipientResource($recipient->load('driver')),
            'meta' => null,
            'message' => 'تمت ترقية السائق الاحتياطي ليصبح مؤكدًا بنجاح وإرسال إشعار الترقية',
        ]);
    }

    /**
     * Remove a backup driver with reason.
     */
    public function removeBackup(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $validated = $request->validate([
            'recipient_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'reason.required' => 'سبب حذف السائق الاحتياطي إلزامي.',
        ]);

        /** @var WorkOrderRecipient $recipient */
        $recipient = WorkOrderRecipient::where('id', $validated['recipient_id'])
            ->where('work_order_id', $workOrder->id)
            ->firstOrFail();

        $order = $this->followUpService->removeBackupDriver(
            order: $workOrder,
            recipient: $recipient,
            reason: $validated['reason'],
            userId: Auth::id()
        );

        return response()->json([
            'data' => new WorkOrderResource($order),
            'meta' => null,
            'message' => 'تم إلغاء السائق الاحتياطي بنجاح',
        ]);
    }

    /**
     * Add a driver manually as confirmed or backup.
     */
    public function addDriver(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $validated = $request->validate([
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
            'as' => ['required', 'string', Rule::in(['confirmed', 'backup'])],
        ], [
            'driver_id.required' => 'يجب تحديد السائق المراد إضافته.',
            'driver_id.exists' => 'السائق المحدد غير موجود.',
            'as.in' => 'نوع الإضافة يجب أن يكون مؤكد أو احتياطي.',
        ]);

        $recipient = $this->followUpService->addDriverManually(
            order: $workOrder,
            driverId: (int) $validated['driver_id'],
            as: $validated['as'],
            userId: Auth::id()
        );

        return response()->json([
            'data' => new WorkOrderRecipientResource($recipient->load('driver')),
            'meta' => null,
            'message' => 'تمت إضافة السائق يدويًا بنجاح وإرسال إشعار التكليف',
        ], 201);
    }

    /**
     * Resend offers to no_response / failed recipients and reopen order to filling.
     */
    public function resendOffers(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $validated = $request->validate([
            'recipient_ids' => ['nullable', 'array'],
            'recipient_ids.*' => ['integer'],
        ]);

        $count = $this->followUpService->resendOffers(
            order: $workOrder,
            recipientIds: $validated['recipient_ids'] ?? [],
            userId: Auth::id()
        );

        return response()->json([
            'data' => [
                'resent_count' => $count,
                'status' => WorkOrderStatus::FILLING->value,
            ],
            'meta' => null,
            'message' => "تمت إعادة إرسال عروض الرحلة بنجاح إلى {$count} سائق وإعادة فتح استقبال السائقين",
        ]);
    }

    /**
     * Advance order status through its lifecycle.
     */
    public function changeStatus(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['in_progress', 'completed', 'cancelled'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'status.in' => 'الحالة المحددة غير صالحة.',
        ]);

        $targetStatus = WorkOrderStatus::from($validated['status']);

        $order = $this->followUpService->changeOrderStatus(
            order: $workOrder,
            newStatus: $targetStatus,
            reason: $validated['reason'] ?? null,
            userId: Auth::id()
        );

        return response()->json([
            'data' => new WorkOrderResource($order),
            'meta' => null,
            'message' => 'تم تحديث حالة أمر العمل بنجاح',
        ]);
    }

    /**
     * Record or update trip outcomes and ratings for confirmed drivers.
     */
    public function recordOutcomes(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $validated = $request->validate([
            'outcomes' => ['required', 'array', 'min:1'],
            'outcomes.*.recipient_id' => ['required', 'integer'],
            'outcomes.*.outcome' => ['required', 'string', Rule::in(array_map(fn ($c) => $c->value, WorkOrderOutcome::cases()))],
            'outcomes.*.rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'outcomes.*.notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'outcomes.required' => 'يجب إدخال نتائج السائقين.',
            'outcomes.*.outcome.in' => 'قيمة النتيجة غير صالحة.',
            'outcomes.*.rating.between' => 'التقييم يجب أن يكون بين 1 و 5 نجوم.',
        ]);

        $this->followUpService->recordOutcomes(
            order: $workOrder,
            outcomes: $validated['outcomes'],
            userId: Auth::id()
        );

        return response()->json([
            'data' => null,
            'meta' => null,
            'message' => 'تم تسجيل نتائج الرحلة وتقييمات السائقين وتحديث مؤشرات الأداء بنجاح',
        ]);
    }

    /**
     * List timeline events of a work order.
     */
    public function events(WorkOrder $workOrder): JsonResponse
    {
        $events = $workOrder->events()
            ->with(['user', 'driver'])
            ->latest('id')
            ->get();

        return response()->json([
            'data' => WorkOrderEventResource::collection($events),
            'meta' => [
                'total' => $events->count(),
            ],
            'message' => 'تم استرجاع سجل أحداث أمر العمل بنجاح',
        ]);
    }
}
