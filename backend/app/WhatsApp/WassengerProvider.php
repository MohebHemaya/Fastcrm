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
 * Wassenger WhatsApp API Provider (https://wassenger.com).
 * Communicates via Wassenger REST API with QR Session / Linked Device support,
 * delivering native in-chat WhatsApp Polls for interactive action buttons.
 */
class WassengerProvider implements WhatsAppProvider
{
    private PendingRequest $http;

    private string $token;

    private string $deviceId;

    private string $baseUrl;

    public function __construct()
    {
        $this->token = (string) config('whatsapp.wassenger.token', env('WASSENGER_TOKEN', ''));
        $this->deviceId = (string) config('whatsapp.wassenger.device_id', env('WASSENGER_DEVICE_ID', ''));
        $this->baseUrl = rtrim((string) config('whatsapp.wassenger.base_url', 'https://api.wassenger.com/v1'), '/');

        $options = [];
        if (PHP_OS_FAMILY === 'Windows' && empty(ini_get('curl.cainfo'))) {
            $candidateCert = 'C:\\xampp\\perl\\vendor\\lib\\Mozilla\\CA\\cacert.pem';
            if (file_exists($candidateCert)) {
                $options['verify'] = $candidateCert;
            } else {
                $options['verify'] = false;
            }
        }

        $this->http = Http::withHeaders([
            'Token' => $this->token,
            'Content-Type' => 'application/json',
        ])
            ->withOptions($options)
            ->baseUrl($this->baseUrl)
            ->timeout(15)
            ->connectTimeout(5)
            ->acceptJson();
    }

    /**
     * Send a template message or rendered text.
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
     * Send a direct text message via Wassenger.
     */
    public function sendText(string $to, string $body): string
    {
        $formattedPhone = $this->normalizePhone($to);

        $payload = [
            'phone' => $formattedPhone,
            'message' => $body,
        ];

        if (! empty($this->deviceId)) {
            $payload['device'] = $this->deviceId;
        }

        $response = $this->http->post('messages', $payload);

        $this->assertSuccess($response, 'sendText');

        $responseData = $response->json();

        return (string) ($responseData['id'] ?? $responseData['wid'] ?? ('ws_'.uniqid('', true)));
    }

    /**
     * Send interactive buttons using native WhatsApp Polls.
     * Each option in $buttons becomes a selectable button in the in-chat poll.
     */
    public function sendButtons(string $to, string $body, array $buttons): string
    {
        $formattedPhone = $this->normalizePhone($to);

        $options = [];
        foreach ($buttons as $btn) {
            $title = trim((string) ($btn['title'] ?? ($btn['text'] ?? '')));
            if ($title !== '') {
                $options[] = $title;
            }
        }

        // WhatsApp polls require at least 2 options and maximum 12 options
        if (count($options) < 2) {
            // Fallback to text if less than 2 options
            $formatted = $body;
            if (! empty($options)) {
                $formatted .= "\n\n🔘 *الخيارات:*\n" . implode("\n", array_map(fn ($opt, $idx) => ($idx + 1).'️⃣ '.$opt, $options, array_keys($options)));
            }
            return $this->sendText($to, $formatted);
        }

        $payload = [
            'phone' => $formattedPhone,
            'poll' => [
                'name' => $body,
                'options' => $options,
                'multiple' => false,
            ],
        ];

        if (! empty($this->deviceId)) {
            $payload['device'] = $this->deviceId;
        }

        $response = $this->http->post('messages', $payload);

        $this->assertSuccess($response, 'sendButtons (Poll)');

        $responseData = $response->json();

        return (string) ($responseData['id'] ?? $responseData['wid'] ?? ('ws_poll_'.uniqid('', true)));
    }

    /**
     * Send a list message with up to 10 rows (sent as native Poll options).
     */
    public function sendList(string $to, string $body, string $buttonLabel, array $rows): string
    {
        $options = [];
        foreach ($rows as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            if ($title !== '') {
                $options[] = $title;
            }
        }

        if (count($options) >= 2 && count($options) <= 12) {
            $buttons = array_map(fn ($title) => ['title' => $title], $options);
            return $this->sendButtons($to, $body, $buttons);
        }

        // Fallback to clear text if rows exceed 12 options
        $formatted = $body."\n\n*{$buttonLabel}*:";
        foreach ($rows as $idx => $row) {
            $num = $idx + 1;
            $formatted .= "\n{$num}. ".($row['title'] ?? '');
            if (! empty($row['description'])) {
                $formatted .= ' — '.$row['description'];
            }
        }

        return $this->sendText($to, $formatted);
    }

    /**
     * Send an image with an optional caption.
     */
    public function sendImage(string $to, string $imageUrl, string $caption = ''): string
    {
        $formattedPhone = $this->normalizePhone($to);

        $payload = [
            'phone' => $formattedPhone,
            'media' => [
                'url' => $imageUrl,
            ],
            'message' => $caption,
        ];

        if (! empty($this->deviceId)) {
            $payload['device'] = $this->deviceId;
        }

        $response = $this->http->post('messages', $payload);

        $this->assertSuccess($response, 'sendImage');

        $responseData = $response->json();

        return (string) ($responseData['id'] ?? $responseData['wid'] ?? ('ws_img_'.uniqid('', true)));
    }

    /**
     * Verify incoming webhook challenge or token.
     */
    public function verifyWebhook(Request $request): string
    {
        $expectedToken = (string) config('whatsapp.verify_token', '');
        $providedToken = $request->query('token')
            ?? $request->query('hub_verify_token')
            ?? $request->header('X-Webhook-Token');

        if ($expectedToken !== '' && $providedToken !== null && ! hash_equals($expectedToken, (string) $providedToken)) {
            throw ValidationException::withMessages(['webhook' => 'Invalid verification token.']);
        }

        if ($challenge = $request->query('hub_challenge')) {
            return (string) $challenge;
        }

        return 'OK';
    }

    /**
     * Parse Wassenger inbound webhook payload into normalized InboundMessage and StatusUpdate events.
     *
     * @return array<InboundMessage|StatusUpdate>
     */
    public function parseWebhook(Request $request): array
    {
        $payload = $request->json()->all();
        $events = [];

        $event = (string) ($payload['event'] ?? '');
        $data = $payload['data'] ?? [];

        if (empty($data)) {
            return [];
        }

        // 1. Inbound message received (user text, audio, image)
        if ($event === 'message:in:new') {
            $isFromMe = ! empty($data['fromMe']);
            if (! $isFromMe) {
                $rawPhone = (string) (
                    $data['chat']['contact']['phone']
                    ?? $data['author']['phone']
                    ?? $data['chat']['phone']
                    ?? $data['from']
                    ?? ''
                );

                $cleanPhone = preg_replace('/[^0-9]/', '', str_replace(['@c.us', '@s.whatsapp.net', '@g.us'], '', $rawPhone));

                if ($cleanPhone !== '') {
                    $from = '+'.$cleanPhone;
                    $rawType = strtolower((string) ($data['type'] ?? 'text'));
                    $text = $data['text'] ?? $data['body'] ?? null;
                    $id = (string) ($data['id'] ?? ('ws_in_'.uniqid('', true)));
                    $timestamp = (int) ($data['timestamp'] ?? $payload['time'] ?? now()->timestamp);

                    $mediaUrl = null;
                    $mediaType = null;

                    if (in_array($rawType, ['image', 'photo'], true)) {
                        $mediaUrl = $data['media']['url'] ?? (filter_var((string) $text, FILTER_VALIDATE_URL) ? $text : null);
                        $mediaType = 'image';
                        $text = $data['caption'] ?? 'صورة 📷';
                        $normalizedType = 'image';
                    } elseif (in_array($rawType, ['audio', 'voice', 'ptt'], true)) {
                        $text = 'رسالة صوتية (غير مدعومة)';
                        $normalizedType = 'text';
                    } elseif (in_array($rawType, ['document', 'file'], true)) {
                        $mediaUrl = $data['media']['url'] ?? null;
                        $mediaType = 'document';
                        $text = $data['filename'] ?? ($data['caption'] ?? 'مستند مرفق');
                        $normalizedType = 'text';
                    } else {
                        $normalizedType = 'text';
                    }

                    $events[] = new InboundMessage(
                        from: $from,
                        type: $normalizedType,
                        text: $text,
                        payloadId: null,
                        providerMessageId: $id,
                        timestamp: $timestamp,
                        mediaUrl: $mediaUrl,
                        mediaType: $mediaType,
                    );
                }
            }
        }

        // 2. Poll vote update (when driver clicks an option on a WhatsApp Poll)
        elseif ($event === 'message:update' && (($data['type'] ?? '') === 'poll' || isset($data['poll']))) {
            $poll = $data['poll'] ?? [];
            $votes = $poll['votes'] ?? [];

            if (! empty($votes) && is_array($votes)) {
                $latestVote = end($votes);
                $votedOption = trim((string) ($latestVote['option'] ?? ''));
                $senderRaw = (string) ($latestVote['sender'] ?? ($data['chat']['contact']['phone'] ?? ($data['to'] ?? '')));
                $cleanPhone = preg_replace('/[^0-9]/', '', str_replace(['@c.us', '@s.whatsapp.net'], '', $senderRaw));

                if ($cleanPhone !== '' && $votedOption !== '') {
                    $from = '+'.$cleanPhone;
                    $voteTs = (int) ($latestVote['timestamp'] ?? $payload['time'] ?? now()->timestamp);
                    $voteId = 'poll_vote_'.($data['id'] ?? 'poll').'_'.md5($from.$votedOption.$voteTs);

                    $events[] = new InboundMessage(
                        from: $from,
                        type: 'button',
                        text: $votedOption,
                        payloadId: $votedOption,
                        providerMessageId: $voteId,
                        timestamp: $voteTs,
                    );
                }
            }
        }

        // 3. Outbound message delivery status update
        elseif (in_array($event, ['message:out:ack', 'message:out:new', 'message:out:failed', 'message:update'], true)) {
            $msgId = (string) ($data['id'] ?? '');
            if ($msgId !== '') {
                $ack = isset($data['ack']) ? (int) $data['ack'] : null;
                $rawStatus = strtolower((string) ($data['status'] ?? ''));

                $status = match (true) {
                    $ack === 3 || $rawStatus === 'read' => 'read',
                    $ack === 2 || $rawStatus === 'delivered' => 'delivered',
                    $ack === 1 || $rawStatus === 'sent' || $rawStatus === 'queued' => 'sent',
                    $ack === 0 || $rawStatus === 'failed' || $event === 'message:out:failed' => 'failed',
                    default => 'sent',
                };

                $errorCode = $status === 'failed' ? ($data['error']['message'] ?? ($data['failedReason'] ?? 'Wassenger delivery error')) : null;

                $events[] = new StatusUpdate(
                    providerMessageId: $msgId,
                    status: $status,
                    errorCode: $errorCode,
                );
            }
        }

        return $events;
    }

    /**
     * Actively poll recent message statuses if needed.
     */
    public function syncRecentStatuses(int $limit = 30): int
    {
        try {
            $response = $this->http->get('messages', ['limit' => $limit]);
            if (! $response->successful()) {
                return 0;
            }

            $messages = $response->json();
            if (! is_array($messages)) {
                return 0;
            }

            $updated = 0;
            foreach ($messages as $msg) {
                $id = (string) ($msg['id'] ?? '');
                $rawStatus = (string) ($msg['status'] ?? '');
                if ($id === '' || $rawStatus === '') {
                    continue;
                }

                $record = WhatsAppMessage::where('provider_message_id', $id)->first();
                if ($record) {
                    $newStatus = match ($rawStatus) {
                        'read' => WhatsAppMessageStatus::READ,
                        'delivered' => WhatsAppMessageStatus::DELIVERED,
                        'sent' => WhatsAppMessageStatus::SENT,
                        'failed' => WhatsAppMessageStatus::FAILED,
                        default => null,
                    };

                    if ($newStatus && $record->status !== $newStatus) {
                        $record->update(['status' => $newStatus]);
                        $updated++;
                    }
                }
            }

            return $updated;
        } catch (\Throwable $e) {
            Log::warning('[WassengerProvider] syncRecentStatuses error: '.$e->getMessage());
            return 0;
        }
    }

    /**
     * Actively sync inbound messages for a specific phone/chat.
     */
    public function syncChatInbound(string $phone): int
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if ($cleanPhone === '') {
            return 0;
        }

        try {
            $response = $this->http->get('messages', [
                'phone' => '+'.$cleanPhone,
                'limit' => 20,
            ]);

            if (! $response->successful()) {
                return 0;
            }

            $messages = $response->json();
            if (! is_array($messages)) {
                return 0;
            }

            $syncedCount = 0;
            foreach ($messages as $msg) {
                if (! empty($msg['fromMe'])) {
                    continue;
                }

                $id = (string) ($msg['id'] ?? '');
                if ($id === '' || WhatsAppMessage::where('provider_message_id', $id)->exists()) {
                    continue;
                }

                $text = (string) ($msg['message'] ?? ($msg['text'] ?? ''));
                $timestamp = (int) ($msg['timestamp'] ?? now()->timestamp);

                $driver = Driver::where('phone', '+'.$cleanPhone)->first();

                WhatsAppMessage::create([
                    'driver_id' => $driver?->id,
                    'direction' => WhatsAppMessageDirection::INBOUND,
                    'type' => WhatsAppMessageType::TEXT,
                    'body' => $text,
                    'status' => WhatsAppMessageStatus::DELIVERED,
                    'provider_message_id' => $id,
                    'sent_at' => Carbon::createFromTimestamp($timestamp),
                ]);

                $syncedCount++;
            }

            return $syncedCount;
        } catch (\Throwable $e) {
            Log::warning('[WassengerProvider] syncChatInbound error: '.$e->getMessage());
            return 0;
        }
    }

    /**
     * Normalize an input phone number to E.164.
     */
    private function normalizePhone(string $phone): string
    {
        $clean = preg_replace('/[^0-9]/', '', $phone);

        // Egyptian local numbers starting with 01 (e.g. 010..., 011..., 012..., 015...)
        if (str_starts_with($clean, '01') && strlen($clean) === 11) {
            return '+20'.substr($clean, 1);
        }

        // Egyptian numbers without leading 0 or with 20 prefix
        if (str_starts_with($clean, '20') && strlen($clean) === 12) {
            return '+'.$clean;
        }

        return '+'.$clean;
    }

    /**
     * Check if API request failed and throw appropriate exception.
     */
    private function assertSuccess(Response $response, string $action): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $body = $response->json() ?? [];
        $message = (string) ($body['message'] ?? $response->body());

        Log::error("[WassengerProvider] {$action} failed (HTTP {$status}): {$message}");

        if (in_array($status, [400, 401, 403, 404, 422], true)) {
            throw new WhatsAppPermanentException("Wassenger API Error ({$status}): {$message}");
        }

        throw new WhatsAppRetryableException("Wassenger Temporary Error ({$status}): {$message}");
    }
}
