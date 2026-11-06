<?php

namespace App\Services\Messaging;

use App\Jobs\SendCommunicationJob;
use App\Mail\HtmlMessage;
use App\Models\Business;
use App\Models\CommunicationLog;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Every outbound email/WhatsApp goes through here: logged first (with an
 * idempotency key so it is never sent twice), then delivered by a queued job.
 */
class CommunicationService
{
    public function __construct(private readonly WhatsAppGateway $whatsapp) {}

    /**
     * @param  array{type: string, recipient: string, recipient_name?: ?string, subject?: ?string, body: string, idempotency_key?: ?string, approval_id?: ?int, template?: ?string, buttons?: array<string,string>}  $message
     */
    public function queue(Business $business, string $channel, array $message, bool $dispatch = true): CommunicationLog
    {
        $log = $this->createLog($business, $channel, $message);

        if ($dispatch && $log->wasRecentlyCreated) {
            SendCommunicationJob::dispatch($log->id);
        }

        return $log;
    }

    /** Create the log row and deliver synchronously (used by the approval executor). */
    public function sendNow(Business $business, string $channel, array $message): CommunicationLog
    {
        $log = $this->createLog($business, $channel, $message);

        if (in_array($log->status, [CommunicationLog::STATUS_QUEUED, CommunicationLog::STATUS_FAILED], true)) {
            $this->deliver($log);
        }

        return $log->refresh();
    }

    /** Deliver one logged message. Safe to call repeatedly: sent messages are never resent. */
    public function deliver(CommunicationLog $log): SendResult
    {
        // Claim the row so two workers cannot send the same message.
        $claimed = CommunicationLog::whereKey($log->id)
            ->whereIn('status', [CommunicationLog::STATUS_QUEUED, CommunicationLog::STATUS_FAILED])
            ->update(['status' => 'sending', 'attempts' => $log->attempts + 1, 'updated_at' => now()]);

        if ($claimed === 0) {
            return SendResult::failed('Already sent or being sent.');
        }

        $log->refresh();

        try {
            $result = $log->channel === 'email' ? $this->deliverEmail($log) : $this->deliverWhatsApp($log);
        } catch (Throwable $e) {
            $result = SendResult::failed($e->getMessage(), [], true);
        }

        $log->forceFill([
            'status' => $result->ok ? CommunicationLog::STATUS_SENT : CommunicationLog::STATUS_FAILED,
            'provider_message_id' => $result->messageId ?? $log->provider_message_id,
            'provider_response' => $result->response ?: null,
            'error' => $result->ok ? null : $result->error,
            'sent_at' => $result->ok ? now() : $log->sent_at,
        ])->save();

        return $result;
    }

    public function whatsappConfigured(): bool
    {
        return $this->whatsapp->name() === 'log'
            || (config('vice_ceo.whatsapp.access_token') && config('vice_ceo.whatsapp.phone_number_id'));
    }

    public function whatsappDriver(): string
    {
        return $this->whatsapp->name();
    }

    private function createLog(Business $business, string $channel, array $message): CommunicationLog
    {
        $key = $message['idempotency_key'] ?? null;

        if ($key && ($existing = CommunicationLog::where('idempotency_key', $key)->first())) {
            return $existing;
        }

        try {
            return CommunicationLog::create([
                'business_id' => $business->id,
                'channel' => $channel,
                'direction' => 'outbound',
                'type' => $message['type'],
                'recipient' => $message['recipient'],
                'recipient_name' => $message['recipient_name'] ?? null,
                'subject' => $message['subject'] ?? null,
                'body' => $message['body'],
                'payload' => array_filter([
                    'template' => $message['template'] ?? null,
                    'buttons' => $message['buttons'] ?? null,
                ]) ?: null,
                'provider' => $channel === 'email' ? config('mail.default') : $this->whatsapp->name(),
                'status' => CommunicationLog::STATUS_QUEUED,
                'idempotency_key' => $key,
                'approval_id' => $message['approval_id'] ?? null,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Lost a race with another worker: the message is already logged.
            return CommunicationLog::where('idempotency_key', $key)->firstOrFail();
        }
    }

    private function deliverEmail(CommunicationLog $log): SendResult
    {
        Mail::to($log->recipient, $log->recipient_name)->send(new HtmlMessage($log->subject ?? 'Dyno Focus', $log->body ?? ''));

        return SendResult::sent(null, ['mailer' => config('mail.default')]);
    }

    private function deliverWhatsApp(CommunicationLog $log): SendResult
    {
        $business = $log->business;
        $phoneNumberId = $business->whatsapp_phone_number_id ?: (string) config('vice_ceo.whatsapp.phone_number_id');
        $payload = $log->payload ?? [];

        if ($this->insideServiceWindow($business, $log->recipient)) {
            if (! empty($payload['buttons'])) {
                return $this->whatsapp->sendButtons($phoneNumberId, $log->recipient, $log->body ?? '', $payload['buttons']);
            }

            return $this->whatsapp->sendText($phoneNumberId, $log->recipient, $log->body ?? '');
        }

        // Outside Meta's 24-hour customer service window only approved templates may be sent.
        $templateKey = $payload['template'] ?? null;
        $templateName = $templateKey ? config("vice_ceo.whatsapp.templates.{$templateKey}") : null;

        if (! $templateName) {
            return SendResult::failed('Recipient has not messaged in the last 24 hours and no approved WhatsApp template is configured'.($templateKey ? ' (WHATSAPP_TEMPLATE_'.strtoupper($templateKey).')' : '').'. Ask them to message you first, or configure a template.');
        }

        return $this->whatsapp->sendTemplate(
            $phoneNumberId,
            $log->recipient,
            $templateName,
            (string) config('vice_ceo.whatsapp.templates.language', 'ms'),
            [$log->body ?? ''],
        );
    }

    private function insideServiceWindow(Business $business, string $phone): bool
    {
        $cutoff = now()->subHours(24)->addMinutes(5); // small safety margin

        $lastInbound = User::where('business_id', $business->id)->where('phone', $phone)->max('whatsapp_last_inbound_at')
            ?? Contact::where('business_id', $business->id)->where('phone', $phone)->max('whatsapp_last_inbound_at');

        return $lastInbound !== null && Carbon::parse($lastInbound)->greaterThan($cutoff);
    }
}
