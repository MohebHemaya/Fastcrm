<?php

namespace App\WhatsApp;

use App\Contracts\WhatsAppProvider;
use App\DTOs\WhatsApp\InboundMessage;
use App\DTOs\WhatsApp\StatusUpdate;
use App\Exceptions\WhatsAppPermanentException;
use App\Exceptions\WhatsAppRetryableException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Meta Cloud API WhatsApp provider.
 * Tested only with HTTP fakes; no live network calls in tests.
 */
class CloudApiProvider implements WhatsAppProvider
{
    private PendingRequest $http;

    private string $phoneNumberId;

    public function __construct()
    {
        $token = config('whatsapp.cloud_api.token', '');
        $this->phoneNumberId = config('whatsapp.cloud_api.phone_number_id', '');
        $version = config('whatsapp.cloud_api.version', 'v25.0');

        $this->http = Http::withToken($token)
            ->baseUrl("https://graph.facebook.com/{$version}/")
            ->timeout(10)
            ->connectTimeout(5)
            ->acceptJson();
    }

    public function sendTemplate(
        string $to,
        string $name,
        string $lang,
        array $params = [],
        ?array $buttons = null
    ): string {
        $components = [];

        if (! empty($params)) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => $p], $params),
            ];
        }

        if (! empty($buttons)) {
            foreach ($buttons as $idx => $btn) {
                $components[] = [
                    'type' => 'button',
                    'sub_type' => 'quick_reply',
                    'index' => $idx,
                    'parameters' => [['type' => 'payload', 'payload' => $btn['id']]],
                ];
            }
        }

        $response = $this->http->post("{$this->phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $name,
                'language' => ['code' => $lang],
                'components' => $components,
            ],
        ]);

        $this->assertSuccess($response, 'sendTemplate');

        return $response->json('messages.0.id') ?? '';
    }

    public function sendText(string $to, string $body): string
    {
        $response = $this->http->post("{$this->phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $body, 'preview_url' => false],
        ]);

        $this->assertSuccess($response, 'sendText');

        return $response->json('messages.0.id') ?? '';
    }

    public function sendButtons(string $to, string $body, array $buttons): string
    {
        $response = $this->http->post("{$this->phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $body],
                'action' => [
                    'buttons' => array_map(fn ($btn) => [
                        'type' => 'reply',
                        'reply' => ['id' => $btn['id'], 'title' => $btn['title']],
                    ], array_slice($buttons, 0, 3)),
                ],
            ],
        ]);

        $this->assertSuccess($response, 'sendButtons');

        return $response->json('messages.0.id') ?? '';
    }

    public function sendList(string $to, string $body, string $buttonLabel, array $rows): string
    {
        $response = $this->http->post("{$this->phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => ['text' => $body],
                'action' => [
                    'button' => $buttonLabel,
                    'sections' => [[
                        'title' => '',
                        'rows' => array_slice($rows, 0, 10),
                    ]],
                ],
            ],
        ]);

        $this->assertSuccess($response, 'sendList');

        return $response->json('messages.0.id') ?? '';
    }

    /**
     * Send an image with an optional caption.
     */
    public function sendImage(string $to, string $imageUrl, string $caption = ''): string
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $to);

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $cleanPhone,
            'type' => 'image',
            'image' => array_filter([
                'link' => $imageUrl,
                'caption' => $caption !== '' ? $caption : null,
            ]),
        ];

        $response = $this->http->post("{$this->phoneNumberId}/messages", $payload);
        $this->assertSuccess($response, 'sendImage');

        return $response->json('messages.0.id') ?? '';
    }

    public function verifyWebhook(Request $request): string
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');
        $expected = config('whatsapp.verify_token', '');

        if ($mode === 'subscribe' && hash_equals($expected, (string) $token) && $challenge) {
            return (string) $challenge;
        }

        throw ValidationException::withMessages(['webhook' => 'Invalid verification token.']);
    }

    /**
     * Parse a Meta Cloud API webhook payload.
     *
     * @return array<InboundMessage|StatusUpdate>
     */
    public function parseWebhook(Request $request): array
    {
        $this->verifySignature($request);

        $events = [];
        $payload = $request->json()->all();

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                foreach ($value['messages'] ?? [] as $msg) {
                    $type = $msg['type'] ?? 'other';
                    $text = null;
                    $payloadId = null;

                    if ($type === 'text') {
                        $text = $msg['text']['body'] ?? null;
                    } elseif ($type === 'button') {
                        $text = $msg['button']['text'] ?? null;
                        $payloadId = $msg['button']['payload'] ?? null;
                    } elseif ($type === 'interactive') {
                        $interactiveType = $msg['interactive']['type'] ?? 'list_reply';
                        $reply = $msg['interactive'][$interactiveType] ?? [];
                        $text = $reply['title'] ?? null;
                        $payloadId = $reply['id'] ?? null;
                        $type = 'list';
                    }

                    $events[] = new InboundMessage(
                        from: $msg['from'],
                        type: $type,
                        text: $text,
                        payloadId: $payloadId,
                        providerMessageId: $msg['id'],
                        timestamp: (int) ($msg['timestamp'] ?? now()->timestamp),
                    );
                }

                foreach ($value['statuses'] ?? [] as $st) {
                    $events[] = new StatusUpdate(
                        providerMessageId: $st['id'],
                        status: $st['status'],
                        errorCode: $st['errors'][0]['code'] ?? null,
                    );
                }
            }
        }

        return $events;
    }

    /**
     * Verify the X-Hub-Signature-256 header.
     */
    private function verifySignature(Request $request): void
    {
        $secret = (string) config('whatsapp.cloud_api.app_secret', '');
        $signature = (string) $request->header('X-Hub-Signature-256', '');

        if ($secret === '' || $signature === '') {
            throw ValidationException::withMessages(['signature' => 'Webhook secret or signature header is missing.']);
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            throw ValidationException::withMessages(['signature' => 'Invalid webhook signature.']);
        }
    }

    /**
     * Assert that the HTTP response indicates success; throw with error info otherwise.
     */
    private function assertSuccess(Response $response, string $operation): void
    {
        if ($response->successful()) {
            return;
        }

        $errorData = $response->json('error', []);
        $code = $errorData['code'] ?? $response->status();
        $message = $errorData['message'] ?? 'Unknown WhatsApp API error';
        $errorSubCode = $errorData['error_subcode'] ?? null;

        // Permanent errors: 4xx except 429
        // Retryable errors: 429 (rate limit) and 5xx
        $isRetryable = $response->status() === 429 || $response->serverError();

        Log::warning("[CloudApiProvider] {$operation} failed", compact('code', 'message', 'isRetryable'));

        if ($isRetryable) {
            throw new WhatsAppRetryableException("[{$code}] {$message}", (int) $errorSubCode);
        }

        throw new WhatsAppPermanentException("[{$code}] {$message}", (int) $errorSubCode);
    }

    public function syncRecentStatuses(int $limit = 30): int
    {
        return 0;
    }

    public function syncChatInbound(string $phone): int
    {
        return 0;
    }
}
