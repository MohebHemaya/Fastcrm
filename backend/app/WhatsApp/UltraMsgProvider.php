<?php

namespace App\WhatsApp;

use App\Contracts\WhatsAppProvider;
use App\DTOs\WhatsApp\InboundMessage;
use App\DTOs\WhatsApp\StatusUpdate;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Exceptions\WhatsAppPermanentException;
use App\Exceptions\WhatsAppRetryableException;
use App\Models\Driver;
use App\Models\WhatsAppMessage;
use App\Services\InboundMessageRouter;
use App\Services\WebhookStatusUpdater;
use App\Services\WhatsAppTemplateService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * UltraMsg WhatsApp gateway provider (https://ultramsg.com).
 * Communicates via UltraMsg REST API.
 */
class UltraMsgProvider implements WhatsAppProvider
{
    private PendingRequest $http;

    private string $instanceId;

    private string $token;

    private string $baseUrl;

    public function __construct()
    {
        $this->token = (string) config('whatsapp.ultramsg.token', '');
        $this->instanceId = (string) config('whatsapp.ultramsg.instance_id', '');
        $this->baseUrl = rtrim((string) config('whatsapp.ultramsg.base_url', 'https://api.ultramsg.com'), '/');

        $options = [];
        if (PHP_OS_FAMILY === 'Windows' && empty(ini_get('curl.cainfo'))) {
            $candidateCert = 'C:\\xampp\\perl\\vendor\\lib\\Mozilla\\CA\\cacert.pem';
            if (file_exists($candidateCert)) {
                $options['verify'] = $candidateCert;
            }
        }

        $this->http = Http::asForm()
            ->withOptions($options)
            ->baseUrl($this->baseUrl)
            ->timeout(12)
            ->connectTimeout(5)
            ->acceptJson();
    }

    /**
     * Send a template message.
     */
    public function sendTemplate(
        string $to,
        string $name,
        string $lang,
        array $params = [],
        ?array $buttons = null
    ): string {
        $body = '';

        if (! empty($params['_rendered_body'])) {
            $body = (string) $params['_rendered_body'];
        } elseif (class_exists(WhatsAppTemplateService::class)) {
            $replacements = $params;
            if (isset($params[0]) && ! isset($replacements['اسم_السائق'])) {
                $replacements['اسم_السائق'] = $params[0];
                $replacements['driver_name'] = $params[0];
            }
            $body = app(WhatsAppTemplateService::class)->render($name, $replacements);
        }

        if (empty($body)) {
            $filteredParams = array_filter($params, fn ($k) => $k !== '_rendered_body', ARRAY_FILTER_USE_KEY);
            $body = ! empty($filteredParams) ? implode("\n", array_map('strval', $filteredParams)) : $name;
        }

        if (! empty($buttons)) {
            return $this->sendButtons($to, $body, $buttons);
        }

        return $this->sendText($to, $body);
    }

    /**
     * Send a direct text message via UltraMsg.
     */
    public function sendText(string $to, string $body): string
    {
        $formattedPhone = $this->normalizePhone($to);
        $endpoint = "{$this->instanceId}/messages/chat";
        $refId = 'crm_'.bin2hex(random_bytes(8));

        $response = $this->http->post($endpoint, [
            'token' => $this->token,
            'to' => $formattedPhone,
            'body' => $body,
            'priority' => 10,
            'referenceId' => $refId,
        ]);

        $this->assertSuccess($response, 'sendText');

        $responseData = $response->json();

        return (string) ($responseData['id']
            ?? $responseData['message_id']
            ?? $refId);
    }

    /**
     * Send a message with quick buttons formatted as clear numbered choices.
     */
    public function sendButtons(string $to, string $body, array $buttons): string
    {
        $formatted = $body;

        if (! empty($buttons)) {
            $formatted .= "\n\n🔘 *الخيارات:*";
            foreach ($buttons as $idx => $btn) {
                $num = $idx + 1;
                $title = $btn['title'] ?? ($btn['text'] ?? '');
                $formatted .= "\n".$num.'️⃣ '.$title;
            }
            $formatted .= "\n\n_(يمكنك الرد برقم أو اسم الخيار)_";
        }

        return $this->sendText($to, $formatted);
    }

    /**
     * Send an interactive list message.
     */
    public function sendList(string $to, string $body, string $buttonLabel, array $rows): string
    {
        $formatted = $body;

        if (! empty($rows)) {
            $formatted .= "\n\n📋 *".($buttonLabel ?: 'الخيارات المتاحة').':*';
            foreach ($rows as $idx => $r) {
                $num = $idx + 1;
                $title = $r['title'] ?? '';
                $desc = ! empty($r['description']) ? ' - '.$r['description'] : '';
                $formatted .= "\n".$num.'️⃣ '.$title.$desc;
            }
            $formatted .= "\n\n_(يمكنك الرد برقم أو اسم الخيار)_";
        }

        return $this->sendText($to, $formatted);
    }

    /**
     * Send an image with an optional caption.
     */
    public function sendImage(string $to, string $imageUrl, string $caption = ''): string
    {
        $to = $this->normalizePhone($to);
        $refId = 'img_'.uniqid('', true);

        $payload = [
            'token' => $this->token,
            'to' => $to,
            'image' => $imageUrl,
            'caption' => $caption,
            'priority' => 10,
            'referenceId' => $refId,
        ];

        $response = $this->http->post("{$this->instanceId}/messages/image", $payload);

        $this->assertSuccess($response, 'sendImage');

        $responseData = $response->json();

        return (string) ($responseData['id']
            ?? $responseData['message_id']
            ?? $refId);
    }

    /**
     * Verify incoming webhook challenge or token.
     */
    public function verifyWebhook(Request $request): string
    {
        $expectedToken = (string) config('whatsapp.verify_token', '');
        $headerToken = $request->header('X-Webhook-Token');
        if (is_array($headerToken)) {
            $headerToken = $headerToken[0] ?? null;
        }

        $providedToken = $request->query('token')
            ?? $request->query('hub_verify_token')
            ?? $headerToken;

        if ($expectedToken !== '' && $providedToken !== null && ! hash_equals($expectedToken, (string) $providedToken)) {
            throw ValidationException::withMessages(['webhook' => 'Invalid verification token.']);
        }

        if ($challenge = $request->query('hub_challenge')) {
            return (string) $challenge;
        }

        return 'OK';
    }

    /**
     * Parse UltraMsg inbound webhook payload into normalized InboundMessage and StatusUpdate events.
     *
     * @return array<InboundMessage|StatusUpdate>
     */
    public function parseWebhook(Request $request): array
    {
        $data = $request->json()->all();
        $events = [];

        $eventType = (string) ($data['event_type'] ?? '');
        $msgData = $data['data'] ?? [];

        // 1. Inbound message received
        if ($eventType === 'message_received' || ($eventType !== 'message_create' && ! empty($msgData) && empty($msgData['ack']))) {
            $isFromMe = ! empty($msgData['fromMe']) || ! empty($msgData['from_me']) || ! empty($msgData['self']);

            if (! $isFromMe && ! empty($msgData)) {
                $rawFrom = (string) ($msgData['from'] ?? '');
                $cleanPhone = preg_replace('/[^0-9]/', '', str_replace(['@c.us', '@s.whatsapp.net', '@g.us'], '', $rawFrom));

                if ($cleanPhone !== '') {
                    $from = '+'.$cleanPhone;
                    $rawType = strtolower((string) ($msgData['type'] ?? 'text'));
                    $text = $msgData['body'] ?? $msgData['text'] ?? null;
                    $id = (string) ($msgData['id'] ?? $data['id'] ?? ('ultramsg_'.uniqid('', true)));
                    $timestamp = (int) ($msgData['time'] ?? now()->timestamp);

                    $mediaUrl = null;
                    $mediaType = null;

                    if (in_array($rawType, ['image', 'photo'], true)) {
                        $mediaUrl = $msgData['media'] ?? (filter_var((string) $text, FILTER_VALIDATE_URL) ? $text : null);
                        $mediaType = 'image';
                        $text = $msgData['caption'] ?? (! empty($msgData['media']) ? $text : 'صورة 📷');
                        $normalizedType = 'image';
                    } elseif (in_array($rawType, ['audio', 'voice', 'ptt'], true)) {
                        $text = 'رسالة صوتية (غير مدعومة)';
                        $normalizedType = 'text';
                    } elseif (in_array($rawType, ['document', 'file'], true)) {
                        $mediaUrl = $msgData['media'] ?? (filter_var((string) $text, FILTER_VALIDATE_URL) ? $text : null);
                        $mediaType = 'document';
                        $text = $msgData['filename'] ?? ($msgData['caption'] ?? 'مستند مرفق');
                        $normalizedType = 'document';
                    } else {
                        $normalizedType = 'text';
                    }

                    $events[] = new InboundMessage(
                        from: $from,
                        type: $normalizedType,
                        text: $text,
                        payloadId: $text,
                        providerMessageId: $id,
                        timestamp: $timestamp,
                        mediaUrl: $mediaUrl,
                        mediaType: $mediaType
                    );
                }
            }
        }

        // 2. Status delivery update (message_ack)
        if ($eventType === 'message_ack' || ! empty($msgData['ack']) || ! empty($data['ack'])) {
            $topId = isset($data['id']) && $data['id'] !== '' ? (string) $data['id'] : null;
            $dataId = isset($msgData['id']) && $msgData['id'] !== '' ? (string) $msgData['id'] : null;
            $refId = isset($data['referenceId']) && $data['referenceId'] !== '' ? (string) $data['referenceId'] : (isset($msgData['referenceId']) && $msgData['referenceId'] !== '' ? (string) $msgData['referenceId'] : null);

            $primaryId = $topId ?? $refId ?? $dataId ?? '';
            $altId = $dataId !== $primaryId ? $dataId : ($refId !== $primaryId ? $refId : null);

            $rawAck = strtolower((string) ($msgData['ack'] ?? $data['ack'] ?? ''));

            $normalizedStatus = match ($rawAck) {
                'server', 'pending', 'sent' => 'sent',
                'device', 'delivered' => 'delivered',
                'read', 'played' => 'read',
                'failed', 'error' => 'failed',
                default => null,
            };

            if ($primaryId !== '' && $normalizedStatus !== null) {
                $events[] = new StatusUpdate(
                    providerMessageId: $primaryId,
                    status: $normalizedStatus,
                    errorCode: $normalizedStatus === 'failed' ? ($msgData['error'] ?? 'ACK_FAILED') : null,
                    altProviderMessageId: $altId
                );
            }
        }

        return $events;
    }

    /**
     * Normalize phone number to international format with plus sign.
     */
    private function normalizePhone(string $phone): string
    {
        $digits = (string) preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($digits, '01') && strlen($digits) === 11) {
            $digits = '20'.substr($digits, 1);
        }

        return '+'.$digits;
    }

    /**
     * Assert HTTP response is successful and doesn't contain provider error.
     */
    private function assertSuccess(Response $response, string $operation): void
    {
        if ($response->status() === 429) {
            Log::warning("[UltraMsgProvider] {$operation} failed", [
                'code' => 429,
                'message' => 'Rate limit exceeded.',
                'isRetryable' => true,
            ]);
            throw new WhatsAppRetryableException('UltraMsg rate limit reached. Retry later.', 429);
        }

        if ($response->serverError()) {
            Log::warning("[UltraMsgProvider] {$operation} failed", [
                'code' => $response->status(),
                'message' => 'UltraMsg server error.',
                'isRetryable' => true,
            ]);
            throw new WhatsAppRetryableException("UltraMsg server error HTTP {$response->status()}", $response->status());
        }

        $json = $response->json();

        if (isset($json['error'])) {
            $errorMsg = is_array($json['error']) ? json_encode($json['error'], JSON_UNESCAPED_UNICODE) : (string) $json['error'];

            Log::warning("[UltraMsgProvider] {$operation} failed", [
                'code' => 'ULTRAMSG_ERROR',
                'message' => $errorMsg,
                'isRetryable' => false,
            ]);

            throw new WhatsAppPermanentException("UltraMsg API error: {$errorMsg}", 400);
        }

        if ($response->failed()) {
            throw new WhatsAppPermanentException("UltraMsg request failed with HTTP {$response->status()}", $response->status());
        }
    }

    /**
     * Actively poll and sync recent message statuses (delivered, read) from UltraMsg.
     * Guarantees message ticks update even if webhooks are delayed, blocked, or in local dev.
     */
    public function syncRecentStatuses(int $limit = 30): int
    {
        if (empty($this->token) || empty($this->instanceId)) {
            return 0;
        }

        try {
            $response = $this->http->get("{$this->instanceId}/messages", [
                'token' => $this->token,
                'page' => 1,
                'limit' => $limit,
                'status' => 'all',
                'sort' => 'desc',
            ]);

            if (! $response->successful()) {
                return 0;
            }

            $data = $response->json();
            $messages = $data['messages'] ?? [];
            $updater = app(WebhookStatusUpdater::class);
            $updatedCount = 0;

            foreach ($messages as $msg) {
                $id = (string) ($msg['id'] ?? '');
                $refId = (string) ($msg['referenceId'] ?? '');
                $rawAck = strtolower((string) ($msg['ack'] ?? $msg['status'] ?? ''));

                $normalizedStatus = match ($rawAck) {
                    'server', 'pending', 'sent' => 'sent',
                    'device', 'delivered' => 'delivered',
                    'read', 'played' => 'read',
                    'failed', 'error', 'unsent', 'invalid' => 'failed',
                    default => null,
                };

                if ($id !== '' && $normalizedStatus !== null) {
                    $res = $updater->handle(new StatusUpdate(
                        providerMessageId: $id,
                        status: $normalizedStatus,
                        errorCode: $normalizedStatus === 'failed' ? ($msg['failed_reason'] ?? 'FAILED') : null,
                        altProviderMessageId: $refId ?: null,
                    ));

                    if ($res) {
                        $updatedCount++;
                    }
                }
            }

            return $updatedCount;
        } catch (\Throwable $e) {
            Log::warning("[UltraMsgProvider] syncRecentStatuses error: {$e->getMessage()}");

            return 0;
        }
    }

    /**
     * Poll recent inbound messages for a specific phone/chat from UltraMsg.
     * Guarantees incoming messages are captured even if webhook is dropped or in local dev.
     */
    public function syncChatInbound(string $phone): int
    {
        if (empty($this->token) || empty($this->instanceId)) {
            return 0;
        }

        try {
            $formattedPhone = preg_replace('/[^0-9]/', '', $this->normalizePhone($phone));
            $chatId = "{$formattedPhone}@c.us";

            $response = $this->http->get("{$this->instanceId}/chats/messages", [
                'token' => $this->token,
                'chatId' => $chatId,
                'limit' => 15,
            ]);

            if (! $response->successful()) {
                return 0;
            }

            $messages = $response->json();
            if (isset($messages['value']) && is_array($messages['value'])) {
                $messages = $messages['value'];
            }
            if (! is_array($messages)) {
                return 0;
            }

            $router = app(InboundMessageRouter::class);
            $processedCount = 0;

            foreach ($messages as $msg) {
                // Only process messages from the contact (fromMe === false)
                $isFromMe = ! empty($msg['fromMe']) || ! empty($msg['from_me']) || ! empty($msg['self']);
                if ($isFromMe) {
                    continue;
                }

                $msgId = (string) ($msg['id'] ?? '');
                if ($msgId === '') {
                    continue;
                }

                // Check idempotency: skip if already stored
                $alreadyExists = WhatsAppMessage::where('provider_message_id', $msgId)->exists();
                if ($alreadyExists) {
                    continue;
                }

                $rawBody = (string) ($msg['body'] ?? $msg['text'] ?? '');
                $timestamp = (int) ($msg['timestamp'] ?? $msg['time'] ?? now()->timestamp);

                // If message is older than 10 minutes, silently persist to chat history without triggering bot reactions
                if ($timestamp < now()->subMinutes(10)->timestamp) {
                    $driverId = Driver::where('phone', '+'.$formattedPhone)->value('id');
                    if ($driverId) {
                        WhatsAppMessage::create([
                            'driver_id' => $driverId,
                            'provider_message_id' => $msgId,
                            'direction' => WhatsAppMessageDirection::IN,
                            'type' => WhatsAppMessageType::TEXT,
                            'status' => WhatsAppMessageStatus::DELIVERED,
                            'body' => $rawBody,
                            'payload' => ['raw_type' => 'text', 'timestamp' => $timestamp, 'historical' => true],
                            'sent_at' => Carbon::createFromTimestamp($timestamp),
                        ]);
                    }

                    continue;
                }

                $inbound = new InboundMessage(
                    from: '+'.$formattedPhone,
                    type: 'text',
                    text: $rawBody,
                    payloadId: $rawBody,
                    providerMessageId: $msgId,
                    timestamp: $timestamp
                );

                $router->handle($inbound);
                $processedCount++;
            }

            return $processedCount;
        } catch (\Throwable $e) {
            Log::warning("[UltraMsgProvider] syncChatInbound error: {$e->getMessage()}");

            return 0;
        }
    }
}
