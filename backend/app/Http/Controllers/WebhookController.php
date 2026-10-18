<?php

namespace App\Http\Controllers;

use App\Contracts\WhatsAppProvider;
use App\DTOs\WhatsApp\InboundMessage;
use App\DTOs\WhatsApp\StatusUpdate;
use App\Jobs\ProcessWebhookEventJob;
use App\Models\WebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WebhookController extends Controller
{
    /**
     * Webhook verification endpoint (GET /webhooks/whatsapp).
     */
    public function verify(Request $request, WhatsAppProvider $provider): Response
    {
        try {
            $challenge = $provider->verifyWebhook($request);

            return response($challenge, 200)->header('Content-Type', 'text/plain');
        } catch (ValidationException $e) {
            return response('Forbidden', 403)->header('Content-Type', 'text/plain');
        } catch (\Throwable $e) {
            Log::error('[WebhookController] Verification error: '.$e->getMessage());

            return response('Error', 500)->header('Content-Type', 'text/plain');
        }
    }

    /**
     * Webhook event receiver endpoint (POST /webhooks/whatsapp).
     */
    public function receive(Request $request, WhatsAppProvider $provider)
    {
        // Dynamically resolve provider if incoming payload format matches a specific provider
        if ($request->has('event') && $request->has('data')) {
            $provider = app(\App\WhatsApp\WassengerProvider::class);
        } elseif ($request->has('entry') || $request->hasHeader('X-Hub-Signature-256')) {
            $provider = app(\App\WhatsApp\CloudApiProvider::class);
        }

        try {
            $events = $provider->parseWebhook($request);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Invalid webhook signature or payload',
                'details' => $e->errors(),
            ], 403);
        } catch (\Throwable $e) {
            Log::error('[WebhookController] Webhook parse error: '.$e->getMessage());

            return response()->json(['error' => 'Webhook processing failed'], 400);
        }

        $messages = [];
        $statuses = [];

        foreach ($events as $event) {
            if ($event instanceof InboundMessage) {
                $messages[] = [
                    'id' => $event->providerMessageId,
                    'from' => $event->from,
                    'type' => $event->type,
                    'text' => $event->text,
                    'payload_id' => $event->payloadId,
                    'timestamp' => $event->timestamp,
                ];
            } elseif ($event instanceof StatusUpdate) {
                $statuses[] = [
                    'id' => $event->providerMessageId,
                    'status' => $event->status,
                    'error_code' => $event->errorCode,
                ];
            }
        }

        // Generate deduplication ID
        $externalId = $messages[0]['id'] ?? ($statuses[0]['id'] ?? ('wh_'.hash('sha256', $request->getContent())));

        // Check deduplication
        $existing = WebhookEvent::where('external_id', $externalId)->first();
        if ($existing) {
            return response()->json(['status' => 'duplicate_ignored'], 200);
        }

        try {
            $webhookEvent = WebhookEvent::create([
                'provider' => config('whatsapp.provider', 'wassenger'),
                'external_id' => $externalId,

                'payload' => [
                    'messages' => $messages,
                    'statuses' => $statuses,
                    'raw' => $request->all(),
                ],
            ]);
        } catch (UniqueConstraintViolationException|QueryException $e) {
            if (str_contains($e->getMessage(), '1062') || str_contains($e->getMessage(), 'Duplicate entry')) {
                return response()->json(['status' => 'duplicate_ignored'], 200);
            }

            throw $e;
        }

        ProcessWebhookEventJob::dispatch($webhookEvent->id);

        return response()->json([
            'status' => 'received',
            'event_id' => $webhookEvent->id,
        ], 200);
    }
}
