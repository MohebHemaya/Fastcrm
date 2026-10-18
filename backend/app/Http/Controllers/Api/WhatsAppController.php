<?php

namespace App\Http\Controllers\Api;

use App\Contracts\WhatsAppProvider;
use App\Enums\DriverStatus;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\WhatsAppMessageResource;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Driver;
use App\Models\WhatsAppMessage;
use App\Services\InviteDriversService;
use App\Services\MessageSender;
use App\Services\PhoneNormalizer;
use App\Services\WhatsAppTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
    public function driverMessages(Driver $driver, WhatsAppProvider $provider): JsonResponse
    {
        // Actively check for any incoming messages from driver that might have missed the webhook
        $provider->syncChatInbound($driver->phone);

        // If there are recent outgoing messages awaiting final delivery status, sync them
        $hasPending = WhatsAppMessage::where('driver_id', $driver->id)
            ->where('direction', WhatsAppMessageDirection::OUT)
            ->whereIn('status', [WhatsAppMessageStatus::QUEUED, WhatsAppMessageStatus::SENT, WhatsAppMessageStatus::DELIVERED])
            ->where('created_at', '>=', now()->subHours(2))
            ->exists();

        if ($hasPending) {
            $provider->syncRecentStatuses(25);
        }

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
        $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'direction' => ['nullable', 'string', 'in:in,out'],
            'driver_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

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
                'provider' => config('whatsapp.provider', 'wassenger'),
                'is_fake' => config('whatsapp.provider') === 'fake',
                'send_rate_per_minute' => (int) config('whatsapp.send_rate_per_minute', 30),
                'invite_batch_size' => (int) config('whatsapp.invite_batch_size', 50),
                'free_form_window' => (int) config('whatsapp.free_form_window_hours', 24),
                'no_response_days' => (int) config('whatsapp.no_response_days', 3),
                'anti_ban' => config('whatsapp.anti_ban'),
            ],
            'meta' => null,
            'message' => null,
        ]);
    }

    /**
     * Get recent conversations list for the split-pane WhatsApp inbox.
     */
    public function conversations(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = trim((string) $request->query('search', ''));

        $driverQuery = Driver::query();

        if ($search !== '') {
            $driverQuery->where(function ($q) use ($search) {
                $q->where('phone', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        } else {
            $driverQuery->whereHas('messages');
        }

        $driverQuery->withMax('messages as last_message_id', 'id');
        $driverQuery->orderByDesc('last_message_id');

        $drivers = $driverQuery->paginate(35);

        $items = [];
        foreach ($drivers as $driver) {
            $lastMsg = WhatsAppMessage::where('driver_id', $driver->id)
                ->latest('id')
                ->first();

            $unreadCount = WhatsAppMessage::where('driver_id', $driver->id)
                ->where('direction', WhatsAppMessageDirection::IN)
                ->where('status', '!=', WhatsAppMessageStatus::READ)
                ->count();

            $items[] = [
                'id' => $driver->id,
                'name' => $driver->name ?: 'كابتن بدون اسم',
                'phone' => $driver->phone,
                'status' => $driver->status->value,
                'last_message' => $lastMsg ? [
                    'id' => $lastMsg->id,
                    'body' => $lastMsg->body,
                    'direction' => $lastMsg->direction->value,
                    'status' => $lastMsg->status->value,
                    'created_at' => $lastMsg->created_at?->toISOString(),
                ] : null,
                'unread_count' => $unreadCount,
            ];
        }

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $drivers->currentPage(),
                'last_page' => $drivers->lastPage(),
                'per_page' => $drivers->perPage(),
                'total' => $drivers->total(),
            ],
            'message' => null,
        ]);
    }

    /**
     * Mark all inbound messages from a driver as read ("seen").
     * Called automatically when the operator opens a driver conversation.
     */
    public function markRead(Driver $driver): JsonResponse
    {
        WhatsAppMessage::where('driver_id', $driver->id)
            ->where('direction', WhatsAppMessageDirection::IN)
            ->where('status', '!=', WhatsAppMessageStatus::READ)
            ->update([
                'status' => WhatsAppMessageStatus::READ,
                'read_at' => now(),
            ]);

        return response()->json([
            'data' => ['driver_id' => $driver->id],
            'meta' => null,
            'message' => 'تم تعيين الرسائل كمقروءة',
        ]);
    }

    /**
     * Fetch messages received after a given message ID (incremental polling).
     * Returns only new rows — avoids re-fetching entire chat history.
     * Also returns recent outbound message statuses so ticks update in real-time.
     */
    public function newMessages(Request $request, Driver $driver, WhatsAppProvider $provider): JsonResponse
    {
        $afterId = (int) $request->query('after_id', 0);

        // Actively check for incoming messages from this driver (throttled every 2 seconds)
        $inboundCacheKey = "whatsapp_sync_inbound_{$driver->id}";
        if (! cache()->has($inboundCacheKey)) {
            cache()->put($inboundCacheKey, true, now()->addSeconds(2));
            $provider->syncChatInbound($driver->phone);
        }

        // Check if there are active outbound messages awaiting final delivery status
        $hasPending = WhatsAppMessage::where('driver_id', $driver->id)
            ->where('direction', WhatsAppMessageDirection::OUT)
            ->whereIn('status', [WhatsAppMessageStatus::QUEUED, WhatsAppMessageStatus::SENT, WhatsAppMessageStatus::DELIVERED])
            ->where('created_at', '>=', now()->subHours(2))
            ->exists();

        if ($hasPending) {
            $cacheKey = "whatsapp_sync_pending_{$driver->id}";
            if (! cache()->has($cacheKey)) {
                cache()->put($cacheKey, true, now()->addSeconds(5));
                $provider->syncRecentStatuses(25);
            }
        }

        $messages = WhatsAppMessage::with('driver')
            ->where('driver_id', $driver->id)
            ->when($afterId > 0, fn ($q) => $q->where('id', '>', $afterId))
            ->orderBy('id', 'asc')
            ->limit(50)
            ->get();

        $recentStatuses = WhatsAppMessage::where('driver_id', $driver->id)
            ->where('direction', WhatsAppMessageDirection::OUT)
            ->where('created_at', '>=', now()->subHours(24))
            ->orderBy('id', 'desc')
            ->limit(30)
            ->get(['id', 'status', 'sent_at', 'delivered_at', 'read_at', 'error_message']);

        $freshDriver = $driver->fresh() ?? $driver;

        return response()->json([
            'data' => WhatsAppMessageResource::collection($messages),
            'meta' => [
                'driver_id' => $driver->id,
                'driver_name' => $driver->name,
                'driver_phone' => $driver->phone,
                'driver_status' => $freshDriver->status->value,
                'can_message' => ! in_array($freshDriver->status, [DriverStatus::OPTED_OUT, DriverStatus::BLOCKED], true),
                'after_id' => $afterId,
                'statuses' => $recentStatuses,
            ],
            'message' => null,
        ]);
    }

    /**
     * Connection / provider health widget.
     * Returns whether the WhatsApp session is considered healthy.
     */
    public function connectionStatus(): JsonResponse
    {
        $provider = config('whatsapp.provider', 'wassenger');
        $isFake = ($provider === 'fake');

        // For real providers: a simple proxy check — look for recent sent messages
        // within the last 10 minutes as a liveness signal.
        $recentSent = WhatsAppMessage::where('direction', WhatsAppMessageDirection::OUT)
            ->where('status', WhatsAppMessageStatus::SENT)
            ->where('sent_at', '>=', now()->subMinutes(10))
            ->exists();

        // Consider connected if: fake (always OK) or had a recent sent message
        // OR the queue is healthy (no stuck queued messages > 30 minutes)
        $stuckQueued = WhatsAppMessage::where('status', WhatsAppMessageStatus::QUEUED)
            ->where('queued_at', '<=', now()->subMinutes(30))
            ->exists();

        $isConnected = $isFake || ($recentSent && ! $stuckQueued) || ! $stuckQueued;

        return response()->json([
            'data' => [
                'provider' => $provider,
                'is_fake' => $isFake,
                'is_connected' => $isConnected,
                'stuck_queued' => $stuckQueued,
            ],
            'meta' => null,
            'message' => null,
        ]);
    }

    /**
     * Send direct message from operator to driver.
     */
    public function directMessage(Request $request, Driver $driver, MessageSender $sender): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ], [
            'message.required' => 'نص الرسالة مطلوب',
            'message.max' => 'تجاوزت الرسالة الحد الأقصى المسموح به (4000 حرف)',
        ]);

        try {
            $msg = $sender->sendText($driver, $validated['message']);
        } catch (\RuntimeException $e) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => new WhatsAppMessageResource($msg),
            'meta' => null,
            'message' => 'تم إرسال الرسالة بنجاح',
        ]);
    }

    /**
     * Send image media message from operator to driver.
     */
    public function sendMedia(Request $request, Driver $driver, MessageSender $sender): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:image'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,gif'],
            'media_url' => ['nullable', 'url', 'max:1000'],
            'caption' => ['nullable', 'string', 'max:1000'],
        ], [
            'type.required' => 'نوع الوسائط مطلوب',
            'type.in' => 'نوع الوسائط يجب أن يكون صورة',
            'file.max' => 'حجم الصورة يتجاوز الحد الأقصى (10 ميجابايت)',
            'file.mimes' => 'صيغة الصورة غير مدعومة (فقط JPG, PNG, WEBP, GIF)',
        ]);

        if (empty($validated['file']) && empty($validated['media_url'])) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'message' => 'يجب إرفاق ملف صورة أو رابط صورة صالح',
            ], 422);
        }

        $mediaUrl = '';
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            if ($file) {
                $path = $file->store('whatsapp_media', 'public');
                if ($path !== false) {
                    $mediaUrl = url(Storage::url($path));
                }
            }
        } else {
            $mediaUrl = (string) $validated['media_url'];
        }

        if ($mediaUrl === '') {
            return response()->json([
                'data' => null,
                'meta' => null,
                'message' => 'فشل حفظ أو قراءة الصورة المرفقة',
            ], 422);
        }

        try {
            $caption = (string) ($validated['caption'] ?? '');
            $msg = $sender->sendImage($driver, $mediaUrl, $caption);
        } catch (\RuntimeException $e) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => new WhatsAppMessageResource($msg),
            'meta' => null,
            'message' => 'تم إرسال الصورة بنجاح',
        ], 201);
    }

    /**
     * Get customizable WhatsApp message templates and quick replies.
     */
    public function templates(WhatsAppTemplateService $templateService): JsonResponse
    {
        return response()->json([
            'data' => [
                'templates' => $templateService->getTemplates(),
                'quick_replies' => $templateService->getQuickReplies(),
            ],
            'meta' => null,
            'message' => null,
        ]);
    }

    /**
     * Save customizable WhatsApp message templates and quick replies.
     */
    public function updateTemplates(Request $request, WhatsAppTemplateService $templateService): JsonResponse
    {
        $validated = $request->validate([
            'templates' => ['nullable', 'array'],
            'templates.*' => ['nullable', 'string', 'max:2000'],
            'quick_replies' => ['nullable', 'array'],
            'quick_replies.*.id' => ['required_with:quick_replies', 'string'],
            'quick_replies.*.title' => ['required_with:quick_replies', 'string'],
            'quick_replies.*.text' => ['required_with:quick_replies', 'string'],
        ], [
            'templates.*.max' => 'نص القالب يجب ألا يتجاوز 2000 حرف',
        ]);

        if (! empty($validated['templates'])) {
            $templateService->saveTemplates($validated['templates']);
        }

        if (isset($validated['quick_replies'])) {
            $templateService->saveQuickReplies($validated['quick_replies']);
        }

        return response()->json([
            'data' => [
                'templates' => $templateService->getTemplates(),
                'quick_replies' => $templateService->getQuickReplies(),
            ],
            'meta' => null,
            'message' => 'تم حفظ صيغ وقوالب الرسائل بنجاح',
        ]);
    }

    /**
     * Send a test message with template interpolation to a given phone number.
     */
    public function testSendTemplate(
        Request $request,
        WhatsAppTemplateService $templateService,
        WhatsAppProvider $provider
    ): JsonResponse {
        $validated = $request->validate([
            'phone' => ['required', 'string'],
            'template_key' => ['nullable', 'string'],
            'custom_text' => ['nullable', 'string', 'max:3000'],
            'sample_data' => ['nullable', 'array'],
        ], [
            'phone.required' => 'رقم الهاتف مطلوب لإرسال الرسالة التجريبية',
        ]);

        $normalized = PhoneNormalizer::normalize($validated['phone']);
        if (! $normalized->isValid || empty($normalized->e164)) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'message' => 'رقم الهاتف غير صالح للإرسال (يرجى التأكد من كتابة رقم هاتف صحيح بالرمز الدولي)',
            ], 422);
        }

        $templateKey = (string) ($validated['template_key'] ?? 'driver_invitation');
        $incomingSample = $request->input('sample_data');
        if (is_array($incomingSample)) {
            if (isset($incomingSample['اسم_السائق'])) {
                $incomingSample['driver_name'] = $incomingSample['اسم_السائق'];
            }
            if (isset($incomingSample['رقم_الطلب'])) {
                $incomingSample['order_code'] = $incomingSample['رقم_الطلب'];
            }
        }
        $sampleData = array_merge(
            $templateService->getSampleDataForTemplate($templateKey),
            is_array($incomingSample) ? $incomingSample : []
        );

        $body = ! empty($validated['custom_text'])
            ? $templateService->renderRaw((string) $validated['custom_text'], $sampleData)
            : $templateService->render($templateKey, $sampleData);

        try {
            $msgId = $provider->sendText($normalized->e164, $body);
        } catch (\Throwable $e) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'message' => 'تعذر إرسال الرسالة التجريبية عبر مزود واتساب: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'data' => [
                'provider_message_id' => $msgId,
                'phone' => $normalized->e164,
                'rendered_body' => $body,
            ],
            'meta' => null,
            'message' => 'تم إرسال الرسالة التجريبية بنجاح إلى الرقم '.$normalized->e164,
        ]);
    }

    /**
     * Background sync: poll WhatsApp provider for any incoming messages across all active drivers.
     * Keeps questionnaire bots and incoming chats alive without needing any specific modal open.
     */
    public function syncActiveInbound(WhatsAppProvider $provider): JsonResponse
    {
        // 1. If provider supports batch sync in 1 single HTTP call (e.g. Wassenger), use it for blazing speed
        if (method_exists($provider, 'syncAllRecentInbound')) {
            $processed = $provider->syncAllRecentInbound(30);

            return response()->json([
                'data' => [
                    'synced_drivers' => 1,
                    'processed_messages' => $processed,
                ],
                'meta' => null,
                'message' => 'تمت مزامنة الرسائل الواردة بنجاح',
            ]);
        }

        // 2. Fallback for other providers: limit to at most 3 drivers per poll to avoid blocking the thread
        $activeDrivers = Driver::query()
            ->whereIn('status', [
                DriverStatus::INVITED,
                DriverStatus::QUESTIONNAIRE_IN_PROGRESS,
            ])
            ->orWhereHas('workOrderRecipients', function ($q) {
                $q->where('status', 'sent');
            })
            ->distinct()
            ->limit(3)
            ->get();

        $processed = 0;
        foreach ($activeDrivers as $driver) {
            $processed += $provider->syncChatInbound($driver->phone);
        }

        return response()->json([
            'data' => [
                'synced_drivers' => $activeDrivers->count(),
                'processed_messages' => $processed,
            ],
            'meta' => null,
            'message' => 'تمت مزامنة الرسائل الواردة بنجاح',
        ]);
    }
}
