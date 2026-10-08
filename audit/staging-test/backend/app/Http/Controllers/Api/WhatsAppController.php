<?php

namespace App\Http\Controllers\Api;

use App\DTOs\WhatsApp\InboundMessage;
use App\DTOs\WhatsApp\StatusUpdate;
use App\Enums\DriverStatus;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\WhatsAppMessageResource;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Driver;
use App\Models\WhatsAppMessage;
use App\Services\InboundMessageRouter;
use App\Services\InviteDriversService;
use App\Services\WebhookStatusUpdater;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    /**
     * Preview count and duration for driver invitation.
     */
    public function estimateInvites(Request $request, InviteDriversService $service): JsonResponse
    {
        $validated = $request->validate([
            'driver_ids' => ['nullable', 'array'],
            'driver_ids.*' => ['integer', 'exists:drivers,id'],
            'all_new' => ['nullable', 'boolean'],
        ]);

        $res = $service->estimate(
            driverIds: $validated['driver_ids'] ?? [],
            allNew: (bool) ($validated['all_new'] ?? false),
        );

        return response()->json([
            'data' => $res,
            'meta' => null,
            'message' => 'تم حساب التقدير بنجاح',
        ]);
    }

    /**
     * Bulk invite drivers with status NEW.
     */
    public function invite(Request $request, InviteDriversService $service): JsonResponse
    {
        $validated = $request->validate([
            'driver_ids' => ['nullable', 'array'],
            'driver_ids.*' => ['integer', 'exists:drivers,id'],
            'all_new' => ['nullable', 'boolean'],
        ]);

        $res = $service->invite(
            driverIds: $validated['driver_ids'] ?? [],
            allNew: (bool) ($validated['all_new'] ?? false),
            user: $request->user(),
        );

        return response()->json([
            'data' => $res,
            'meta' => null,
            'message' => "تم إرسال وجدولة الدعوات بنجاح لـ {$res['invited_count']} سائق",
        ]);
    }

    /**
     * Get chat/message log for a specific driver.
     */
    public function driverMessages(Driver $driver): JsonResponse
    {
        $messages = WhatsAppMessage::with('driver')
            ->where('driver_id', $driver->id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'data' => WhatsAppMessageResource::collection($messages),
            'meta' => [
                'driver_id' => $driver->id,
                'driver_name' => $driver->name,
                'driver_phone' => $driver->phone,
                'driver_status' => $driver->status->value,
                'can_message' => ! in_array($driver->status, [DriverStatus::OPTED_OUT, DriverStatus::BLOCKED], true),
            ],
            'message' => null,
        ]);
    }

    /**
     * List all messages across the system with status filters and counters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = WhatsAppMessage::with('driver');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('direction')) {
            $query->where('direction', $request->query('direction'));
        }

        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->query('driver_id'));
        }

        if ($request->filled('search')) {
            $term = $request->query('search');
            $query->where(function ($q) use ($term) {
                $q->where('body', 'like', "%{$term}%")
                    ->orWhereHas('driver', function ($dq) use ($term) {
                        $dq->where('phone', 'like', "%{$term}%")
                            ->orWhere('name', 'like', "%{$term}%");
                    });
            });
        }

        $messages = $query->orderBy('created_at', 'desc')->paginate(25);

        // Calculate status counters
        $counts = [
            'total' => WhatsAppMessage::count(),
            'queued' => WhatsAppMessage::where('status', WhatsAppMessageStatus::QUEUED)->count(),
            'sent' => WhatsAppMessage::where('status', WhatsAppMessageStatus::SENT)->count(),
            'delivered' => WhatsAppMessage::where('status', WhatsAppMessageStatus::DELIVERED)->count(),
            'read' => WhatsAppMessage::where('status', WhatsAppMessageStatus::READ)->count(),
            'failed' => WhatsAppMessage::where('status', WhatsAppMessageStatus::FAILED)->count(),
        ];

        return response()->json([
            'data' => WhatsAppMessageResource::collection($messages->items()),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
                'counts' => $counts,
            ],
            'message' => null,
        ]);
    }

    /**
     * Retry sending a failed WhatsApp message.
     */
    public function retry(WhatsAppMessage $message): JsonResponse
    {
        if ($message->direction !== WhatsAppMessageDirection::OUT) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'message' => 'يمكن إعادة المحاولة للرسائل الصادرة فقط',
            ], 422);
        }

        if ($message->status !== WhatsAppMessageStatus::FAILED) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'message' => 'الرسالة ليست في حالة فشل',
            ], 422);
        }

        // Verify driver is not opted out or blocked
        $driver = $message->driver;
        if (in_array($driver->status, [DriverStatus::OPTED_OUT, DriverStatus::BLOCKED], true)) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'message' => 'لا يمكن إرسال رسائل لسائق ملغي اشتراكه أو محظور',
            ], 422);
        }

        $message->update([
            'status' => WhatsAppMessageStatus::QUEUED,
            'error_code' => null,
            'error_message' => null,
            'provider_message_id' => null,
            'queued_at' => now(),
        ]);

        SendWhatsAppMessageJob::dispatch($message->id);

        return response()->json([
            'data' => new WhatsAppMessageResource($message->fresh()),
            'meta' => null,
            'message' => 'تمت إعادة جدولة إرسال الرسالة بنجاح',
        ]);
    }

    /**
     * Get active provider status and settings.
     */
    public function providerStatus(): JsonResponse
    {
        return response()->json([
            'data' => [
                'provider' => config('whatsapp.provider', 'fake'),
                'is_fake' => config('whatsapp.provider') === 'fake',
                'send_rate_per_minute' => (int) config('whatsapp.send_rate_per_minute', 30),
                'invite_batch_size' => (int) config('whatsapp.invite_batch_size', 50),
                'free_form_window' => (int) config('whatsapp.free_form_window_hours', 24),
                'no_response_days' => (int) config('whatsapp.no_response_days', 3),
            ],
            'meta' => null,
            'message' => null,
        ]);
    }

    /**
     * Dev/test simulation endpoint: inbound message.
     */
    public function simulateInbound(Request $request, InboundMessageRouter $router): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string'],
            'text' => ['nullable', 'string'],
            'payload_id' => ['nullable', 'string'],
        ]);

        $inbound = new InboundMessage(
            from: $validated['phone'],
            type: ! empty($validated['payload_id']) ? 'button' : 'text',
            text: $validated['text'] ?? null,
            payloadId: $validated['payload_id'] ?? null,
            providerMessageId: 'sim_'.uniqid('', true),
            timestamp: now()->timestamp,
        );

        $router->handle($inbound);

        return response()->json([
            'data' => ['handled' => true, 'provider_message_id' => $inbound->providerMessageId],
            'meta' => null,
            'message' => 'تمت محاكاة الرسالة الواردة ومعالجتها بنجاح',
        ]);
    }

    /**
     * Dev/test simulation endpoint: status update.
     */
    public function simulateStatus(Request $request, WebhookStatusUpdater $updater): JsonResponse
    {
        $validated = $request->validate([
            'message_id' => ['required'],
            'status' => ['required', 'in:sent,delivered,read,failed'],
            'error_code' => ['nullable', 'string'],
        ]);

        $msg = WhatsAppMessage::where('id', $validated['message_id'])
            ->orWhere('provider_message_id', $validated['message_id'])
            ->firstOrFail();

        if (! $msg->provider_message_id) {
            $msg->update(['provider_message_id' => 'fake_'.uniqid('sim_', true)]);
        }

        $update = new StatusUpdate(
            providerMessageId: $msg->provider_message_id,
            status: $validated['status'],
            errorCode: $validated['error_code'] ?? null,
        );

        $updater->handle($update);

        return response()->json([
            'data' => new WhatsAppMessageResource($msg->fresh()),
            'meta' => null,
            'message' => 'تم تحديث حالة الرسالة بنجاح',
        ]);
    }
}
