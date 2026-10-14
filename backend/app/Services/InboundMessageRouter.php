<?php

namespace App\Services;

use App\DTOs\WhatsApp\InboundMessage;
use App\Enums\DriverStatus;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Enums\WorkOrderRecipientStatus;
use App\Enums\WorkOrderStatus;
use App\Events\WhatsAppConversationUpdated;
use App\Events\WhatsAppMessageReceived;
use App\Models\Driver;
use App\Models\Question;
use App\Models\WhatsAppMessage;
use App\Models\WorkOrderRecipient;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class InboundMessageRouter
{
    public function __construct(
        protected PhoneNormalizer $phoneNormalizer,
        protected MessageSender $messageSender,
        protected QuestionnaireService $questionnaireService,
        protected AcceptOrderOffer $acceptOrderOffer,
    ) {}

    /**
     * Route an incoming WhatsApp message.
     */
    public function handle(InboundMessage $inbound): void
    {
        $driver = $this->resolveDriver($inbound->from);

        if (! $driver) {
            $norm = $this->phoneNormalizer->normalize($inbound->from);
            $cleanPhone = $norm->isValid && $norm->e164 ? $norm->e164 : (str_starts_with($inbound->from, '+') ? $inbound->from : '+' . preg_replace('/[^\d]/', '', $inbound->from));

            $driver = Driver::create([
                'name' => 'سائق جديد ' . substr($cleanPhone, -4),
                'phone' => $cleanPhone,
                'status' => DriverStatus::NEW,
            ]);
            Log::info("[InboundMessageRouter] Auto-created driver {$driver->id} for phone: {$cleanPhone}");
        }

        // Persist the inbound message (idempotent by provider_message_id)
        $this->storeInboundMessage($driver, $inbound);

        $text = $inbound->text ?? '';
        $payloadId = $inbound->payloadId ?? '';

        // 1. Check Opt-Out keyword first (highest priority, applies to any driver state)
        if ($this->isOptOut($text, $payloadId)) {
            $this->handleOptOut($driver);

            return;
        }

        // 2. Check Work Order offer responses (accept/decline)
        if ($this->handleWorkOrderResponse($driver, $inbound)) {
            return;
        }

        // 3. Handle state-specific logic
        if ($driver->status === DriverStatus::INVITED) {
            $this->handleInvitedDriver($driver, $inbound);
        } elseif ($driver->status === DriverStatus::INTERESTED) {
            if (Question::active()->exists()) {
                $this->questionnaireService->startQuestionnaire($driver);
            } else {
                $this->handleUnknownOrFallback($driver);
            }
        } elseif ($driver->status === DriverStatus::QUESTIONNAIRE_IN_PROGRESS) {
            $this->questionnaireService->handleInboundReply($driver, $inbound);
        } elseif ($driver->status === DriverStatus::NO_RESPONSE) {
            $this->questionnaireService->resumeQuestionnaire($driver, $inbound);
        } elseif (in_array($driver->status, [DriverStatus::OPTED_OUT, DriverStatus::BLOCKED], true)) {
            // Silently ignore messages from blocked/opted-out drivers (never respond)
            Log::info("[InboundMessageRouter] Driver {$driver->id} is {$driver->status->value}; ignoring message.");
        } else {
            $this->handleUnknownOrFallback($driver);
        }
    }

    /**
     * Resolve a driver from the sender phone number.
     */
    protected function resolveDriver(string $phone): ?Driver
    {
        $normResult = $this->phoneNormalizer->normalize($phone);
        if ($normResult->isValid && $normResult->e164) {
            $driver = Driver::where('phone', $normResult->e164)->first();
            if ($driver) {
                return $driver;
            }
        }

        $clean = preg_replace('/[^\d+]/', '', $phone);
        $withPlus = str_starts_with($clean, '+') ? $clean : '+'.$clean;
        $withoutPlus = ltrim($clean, '+');

        return Driver::where('phone', $withPlus)
            ->orWhere('phone', $withoutPlus)
            ->first();
    }

    /**
     * Store the inbound message in database if not already stored.
     */
    protected function storeInboundMessage(Driver $driver, InboundMessage $inbound): WhatsAppMessage
    {
        $existing = WhatsAppMessage::where('provider_message_id', $inbound->providerMessageId)->first();
        if ($existing) {
            return $existing;
        }

        $type = match ($inbound->type) {
            'button', 'interactive' => WhatsAppMessageType::BUTTON,
            'list' => WhatsAppMessageType::LIST,
            default => WhatsAppMessageType::TEXT,
        };

        $msg = WhatsAppMessage::create([
            'driver_id' => $driver->id,
            'direction' => WhatsAppMessageDirection::IN,
            'provider_message_id' => $inbound->providerMessageId,
            'type' => $type,
            'template_name' => null,
            'payload' => [
                'payload_id' => $inbound->payloadId,
                'raw_type' => $inbound->type,
                'timestamp' => $inbound->timestamp,
            ],
            'body' => $inbound->text,
            'status' => WhatsAppMessageStatus::DELIVERED,
            'delivered_at' => now(),
        ]);

        // Broadcast real-time events via Pusher
        $body = $inbound->text ?? '';
        $createdAt = $msg->created_at?->toIso8601String() ?? now()->toIso8601String();

        try {
            broadcast(new WhatsAppMessageReceived(
                driverId: $driver->id,
                messageId: $msg->id,
                body: $body,
                direction: 'in',
                createdAt: $createdAt,
            ));

            broadcast(new WhatsAppConversationUpdated(
                driverId: $driver->id,
                driverName: $driver->name ?? '',
                lastMessage: mb_substr($body, 0, 60),
                unreadCount: WhatsAppMessage::where('driver_id', $driver->id)
                    ->where('direction', WhatsAppMessageDirection::IN)
                    ->whereNull('read_at')
                    ->count(),
                updatedAt: $createdAt,
            ));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Inbound WhatsApp broadcast failed: ' . $e->getMessage());
        }

        return $msg;
    }

    /**
     * Normalize text for Arabic keyword matching (remove diacritics, standardize letters).
     */
    public function normalizeArabicText(string $text): string
    {
        $str = mb_strtolower(trim($text), 'UTF-8');

        // Remove Arabic diacritics (tashkeel & tatweel)
        $str = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $str) ?? $str;

        // Normalize Alef forms
        $str = preg_replace('/[إأآٱ]/u', 'ا', $str) ?? $str;

        // Normalize Teh Marbuta to Heh
        $str = preg_replace('/ة/u', 'ه', $str) ?? $str;

        // Normalize Alef Maksura to Yeh
        $str = preg_replace('/ى/u', 'ي', $str) ?? $str;

        // Remove punctuation / special characters
        $str = preg_replace('/[^\p{L}\p{N}\s]/u', '', $str) ?? $str;

        return trim(preg_replace('/\s+/', ' ', $str) ?? $str);
    }

    /**
     * Check if the incoming message contains an opt-out keyword.
     * Matches the whole message, the first word, or a starting multi-word phrase.
     * Ambiguous words (وقف, انهاء) only match if they constitute the entire message.
     */
    public function isOptOut(string $text, string $payloadId = ''): bool
    {
        if (in_array(strtolower(trim($payloadId)), ['opt_out', 'stop', 'unsubscribe'], true)) {
            return true;
        }

        $normalized = $this->normalizeArabicText($text);
        if ($normalized === '') {
            return false;
        }

        // 1. Check exact-only everyday words (only opt-out if whole normalized message matches)
        $exactOnlyKeywords = config('whatsapp.optout_exact_only_keywords', [
            'وقف', 'انهاء', 'إنهاء', 'قف', 'خروج',
        ]);
        foreach ($exactOnlyKeywords as $kw) {
            if ($normalized === $this->normalizeArabicText($kw)) {
                return true;
            }
        }

        // 2. Check standard keywords: must match whole message or first word only
        $keywords = config('whatsapp.optout_keywords', [
            'ايقاف', 'إيقاف', 'stop', 'الغاء', 'إلغاء', 'unsubscribe',
            'اوقف', 'لا اريد', 'لا أريد',
        ]);

        $tokens = explode(' ', $normalized);
        $firstWord = $tokens[0] ?? '';

        foreach ($keywords as $kw) {
            $normKw = $this->normalizeArabicText($kw);

            // Whole message match
            if ($normalized === $normKw) {
                return true;
            }

            // Single word keyword: match first word only (never in the middle or end)
            if (! str_contains($normKw, ' ') && $firstWord === $normKw) {
                return true;
            }

            // Multi-word phrase keyword: message must start with the phrase
            if (str_contains($normKw, ' ') && str_starts_with($normalized, $normKw.' ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle driver opt-out.
     */
    protected function handleOptOut(Driver $driver): void
    {
        if ($driver->status === DriverStatus::OPTED_OUT) {
            return;
        }

        if (DriverStatusService::canTransition($driver->status, DriverStatus::OPTED_OUT)) {
            DriverStatusService::transition(
                $driver,
                DriverStatus::OPTED_OUT,
                'إلغاء الاشتراك بناءً على طلب السائق عبر واتساب'
            );

            // Send one final confirmation message
            $this->messageSender->sendOptOutConfirmation(
                $driver,
                'تم إلغاء اشتراكك بنجاح. لن تتلقى أي رسائل أخرى مننا.'
            );
        }
    }

    /**
     * Handle responses from drivers in INVITED status.
     */
    protected function handleInvitedDriver(Driver $driver, InboundMessage $inbound): void
    {
        $payloadId = strtolower(trim((string) $inbound->payloadId));
        $normalized = $this->normalizeArabicText($inbound->text ?? '');

        // YES patterns
        $isYes = in_array($payloadId, ['invite_yes', 'yes', '1'], true)
            || in_array($normalized, ['نعم', 'نعم مهتم', 'مهتم', 'موافق', 'اكيد', 'yes', '1'], true)
            || str_starts_with($normalized, 'نعم')
            || str_contains($normalized, 'مهتم');

        if ($isYes) {
            if (DriverStatusService::canTransition($driver->status, DriverStatus::INTERESTED)) {
                DriverStatusService::transition(
                    $driver,
                    DriverStatus::INTERESTED,
                    'الموافقة على الدعوة عبر واتساب'
                );

                $this->messageSender->sendText(
                    $driver,
                    'شكرًا لك! تم تسجيل اهتمامك وسنتواصل معك قريبًا بالتفاصيل.'
                );

                if (Question::active()->exists()) {
                    $this->questionnaireService->startQuestionnaire($driver);
                }
            }

            return;
        }

        // NO patterns
        $isNo = in_array($payloadId, ['invite_no', 'no', '2'], true)
            || in_array($normalized, ['لا', 'لا شكرا', 'غير مهتم', 'مش مهتم', 'no', '2'], true)
            || str_starts_with($normalized, 'لا');

        if ($isNo) {
            if (DriverStatusService::canTransition($driver->status, DriverStatus::DECLINED)) {
                DriverStatusService::transition(
                    $driver,
                    DriverStatus::DECLINED,
                    'رفض الدعوة عبر واتساب'
                );
            }

            return;
        }

        // Neither yes nor no: send throttled guidance back to invitation choices
        $cacheKey = "whatsapp_invite_guidance:{$driver->id}";
        if (! Cache::has($cacheKey)) {
            Cache::put($cacheKey, true, now()->addMinutes(30));
            $this->messageSender->sendButtons(
                $driver,
                'أهلاً بك يا كابتن! لتأكيد رغبتك بالانضمام للأسطول وبدء العمل معنا، برجاء الاختيار:',
                [
                    ['id' => 'invite_yes', 'title' => 'نعم، مهتم'],
                    ['id' => 'invite_no', 'title' => 'لا، شكرًا'],
                ]
            );
        }
    }

    /**
     * Send polite fallback message, throttled to at most once per hour per driver.
     */
    protected function handleUnknownOrFallback(Driver $driver): void
    {
        $cacheKey = "whatsapp_fallback_sent:{$driver->id}";

        if (Cache::has($cacheKey)) {
            Log::info("[InboundMessageRouter] Suppressed repeated fallback message for driver {$driver->id}");

            return;
        }

        Cache::put($cacheKey, true, now()->addHour());

        $buttons = [
            ['id' => 'help', 'title' => 'مساعدة'],
            ['id' => 'opt_out', 'title' => 'إيقاف'],
        ];

        try {
            $this->messageSender->sendButtons(
                $driver,
                'أهلًا بك! لم نتمكن من فهم رسالتك. يمكنك الرد بـ "إيقاف" لإلغاء الاشتراك في أي وقت.',
                $buttons
            );
        } catch (\Throwable $e) {
            Log::warning("[InboundMessageRouter] Could not send fallback buttons to driver {$driver->id}: {$e->getMessage()}");
        }
    }

    /**
     * Intercept and handle replies to work order offers (buttons or free-form).
     */
    protected function handleWorkOrderResponse(Driver $driver, InboundMessage $inbound): bool
    {
        $payloadId = trim($inbound->payloadId ?? '');
        $text = trim($inbound->text ?? '');

        // 1. Direct button tap payload: order_accept:{orderId}:{driverId} or order_decline:{orderId}:{driverId}
        if (str_starts_with($payloadId, 'order_accept:')) {
            $parts = explode(':', $payloadId);
            $orderId = (int) ($parts[1] ?? 0);
            if ($orderId > 0) {
                $this->acceptOrderOffer->handle($orderId, $driver->id, 'accept');

                return true;
            }
        }

        if (str_starts_with($payloadId, 'order_decline:')) {
            $parts = explode(':', $payloadId);
            $orderId = (int) ($parts[1] ?? 0);
            if ($orderId > 0) {
                $this->acceptOrderOffer->handle($orderId, $driver->id, 'decline');

                return true;
            }
        }

        // 2. Free text reply from a driver
        $normalizer = app(ArabicReplyNormalizer::class);
        $normalizedText = $normalizer->normalize($text);

        $isAccept = $normalizer->isYes($text) || in_array($normalizedText, ['اوافق', 'موافق', 'قبول', 'موافقه'], true);
        $isDecline = $normalizer->isNo($text) || in_array($normalizedText, ['اعتذر', 'اعتذار', 'مش موافق', 'مش هقدر'], true);

        if (! $isAccept && ! $isDecline) {
            return false;
        }

        // Find open offers: status SENT on an order in FILLING
        $openOffers = WorkOrderRecipient::where('driver_id', $driver->id)
            ->where('status', WorkOrderRecipientStatus::SENT)
            ->whereHas('workOrder', function ($q) {
                $q->where('status', WorkOrderStatus::FILLING);
            })
            ->get();

        if ($openOffers->count() === 1) {
            /** @var WorkOrderRecipient $targetRecipient */
            $targetRecipient = $openOffers->first();
            $action = $isAccept ? 'accept' : 'decline';
            $this->acceptOrderOffer->handle($targetRecipient->work_order_id, $driver->id, $action);

            return true;
        }

        if ($openOffers->count() >= 2) {
            // Multiple open offers: do not guess! Ask them to tap the button on the specific offer message.
            $driverName = $driver->name ?: 'كابتن';
            $this->messageSender->sendText(
                $driver,
                "عفوًا يا {$driverName}، لديك أكثر من عرض رحلة متاح حاليًا. لتأكيد أو الاعتذار عن رحلة معينة، يرجى الضغط على زر (أوافق) أو (أعتذر) الموجود أسفل رسالة تفاصيل الرحلة المطلوبة."
            );

            return true;
        }

        return false;
    }
}
