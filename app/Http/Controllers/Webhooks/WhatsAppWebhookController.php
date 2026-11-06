<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsAppMessageJob;
use App\Models\CommunicationLog;
use App\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /** Meta verification handshake (GET). */
    public function verify(Request $request): Response
    {
        $token = (string) config('vice_ceo.whatsapp.verify_token');

        if ($token !== ''
            && $request->query('hub_mode') === 'subscribe'
            && hash_equals($token, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /** Inbound messages and delivery statuses (POST). */
    public function receive(Request $request): Response
    {
        if (! $this->validSignature($request)) {
            Log::warning('WhatsApp webhook rejected: invalid signature', ['ip' => $request->ip()]);

            return response('Invalid signature', 401);
        }

        $payload = $request->json()->all();

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? null) !== 'messages') {
                    continue;
                }
                $value = $change['value'] ?? [];
                $phoneNumberId = (string) ($value['metadata']['phone_number_id'] ?? '');

                foreach ($value['messages'] ?? [] as $message) {
                    $this->storeMessage($phoneNumberId, $message, $value['contacts'] ?? []);
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    $this->applyStatus($status);
                }
            }
        }

        // Always 200 quickly so Meta does not retry; work happens on the queue.
        return response('OK', 200);
    }

    private function storeMessage(string $phoneNumberId, array $message, array $contacts): void
    {
        $id = (string) ($message['id'] ?? '');
        if ($id === '') {
            return;
        }

        try {
            $event = WebhookEvent::create([
                'provider' => 'whatsapp',
                'event_id' => $id,
                'payload' => ['phone_number_id' => $phoneNumberId, 'message' => $message, 'contacts' => $contacts],
            ]);
        } catch (UniqueConstraintViolationException) {
            return; // duplicate delivery from Meta: already processed / queued
        }

        ProcessWhatsAppMessageJob::dispatch($event->id);
    }

    private function applyStatus(array $status): void
    {
        $id = $status['id'] ?? null;
        $state = $status['status'] ?? null;
        if (! $id || ! in_array($state, ['sent', 'delivered', 'read', 'failed'], true)) {
            return;
        }

        $log = CommunicationLog::where('provider_message_id', $id)->first();
        if (! $log) {
            return;
        }

        // Never move backwards (read -> delivered) when Meta delivers out of order.
        $rank = ['sending' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3];
        if ($state !== 'failed' && ($rank[$state] ?? 0) <= ($rank[$log->status] ?? -1)) {
            return;
        }

        $log->forceFill([
            'status' => $state,
            'error' => $state === 'failed' ? ($status['errors'][0]['title'] ?? $status['errors'][0]['message'] ?? 'Delivery failed') : $log->error,
        ])->save();
    }

    private function validSignature(Request $request): bool
    {
        $secret = (string) config('vice_ceo.whatsapp.app_secret');
        $header = (string) $request->header('X-Hub-Signature-256');

        if ($secret === '' || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $header);
    }
}
