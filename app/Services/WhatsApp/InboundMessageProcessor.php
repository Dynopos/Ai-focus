<?php

namespace App\Services\WhatsApp;

use App\Enums\TaskStatus;
use App\Models\Business;
use App\Models\CommunicationLog;
use App\Models\Contact;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Executive\AuditLogger;
use App\Services\Executive\TaskService;
use App\Services\Messaging\CommunicationService;
use App\Support\Actor;
use App\Support\Phone;
use Illuminate\Support\Carbon;

/**
 * Routes one inbound WhatsApp message. CEO commands and customer messages are
 * kept strictly apart: only a verified CEO number of THIS business can command.
 */
class InboundMessageProcessor
{
    public function __construct(
        private readonly CommandInterpreter $interpreter,
        private readonly CeoCommandHandler $handler,
        private readonly CommunicationService $communications,
        private readonly TaskService $tasks,
        private readonly AuditLogger $audit,
    ) {}

    /** @return string webhook event status (processed | ignored) */
    public function process(WebhookEvent $event): string
    {
        $data = $event->payload;
        $message = $data['message'] ?? [];
        $from = Phone::normalize($message['from'] ?? null);

        if (! $from) {
            return 'ignored';
        }

        $business = $this->resolveBusiness((string) ($data['phone_number_id'] ?? ''), $from);
        if (! $business) {
            return 'ignored';
        }
        $event->forceFill(['business_id' => $business->id])->save();

        $text = $this->extractText($message);

        // Phone ownership verification: "VERIFY 123456" sent from the phone itself.
        if ($text !== null && preg_match('/^\s*verify\s+(\d{6})\s*$/i', $text, $m)) {
            return $this->verifyPhone($business, $from, $m[1], $message);
        }

        $ceo = User::where('business_id', $business->id)
            ->where('role', User::ROLE_CEO)
            ->where('phone', $from)
            ->whereNotNull('phone_verified_at')
            ->first();

        if ($ceo) {
            return $this->handleCeo($business, $ceo, $message, $text);
        }

        $contact = Contact::where('business_id', $business->id)->where('phone', $from)->first();
        if ($contact) {
            return $this->handleCustomer($business, $contact, $message, $text);
        }

        $this->logInbound($business, 'inbound_unknown', $from, $data['contacts'][0]['profile']['name'] ?? null, $text, $message);

        return 'ignored';
    }

    private function handleCeo(Business $business, User $ceo, array $message, ?string $text): string
    {
        $ceo->forceFill(['whatsapp_last_inbound_at' => now()])->save();
        $this->logInbound($business, 'inbound_ceo', $ceo->phone, $ceo->name, $text, $message);

        $context = [
            'forwarded' => (bool) (($message['context']['forwarded'] ?? false) || ($message['context']['frequently_forwarded'] ?? false)),
            'raw' => (string) $text,
            'button' => false,
        ];

        $buttonId = $message['interactive']['button_reply']['id'] ?? null;

        if ($buttonId && preg_match('/^apv:([0-9A-Za-z]{26}):(\d+):(approve|reject)$/', $buttonId, $m)) {
            $approval = $business->approvals()->where('ulid', strtolower($m[1]))->first()
                ?? $business->approvals()->where('ulid', $m[1])->first();
            $intent = new Intent($m[3] === 'approve' ? Intent::APPROVE : Intent::REJECT, $approval?->code ?? '----', null, 'button');
            $context['button'] = true;
            $context['button_version'] = (int) $m[2];
        } elseif ($text === null) {
            $reply = 'Saya hanya boleh membaca mesej teks buat masa ini.';
        } else {
            $intent = $this->interpreter->interpret($text);
        }

        $reply ??= $this->handler->handle($business, $ceo, $intent, $context);

        $this->audit->record($business->id, 'whatsapp.ceo_command', null, [
            'intent' => isset($intent) ? $intent->name : null,
            'via' => isset($intent) ? $intent->via : null,
            'message_id' => $message['id'] ?? null,
        ], Actor::user($ceo, 'whatsapp'));

        $this->communications->queue($business, 'whatsapp', [
            'type' => 'ceo_reply',
            'recipient' => $ceo->phone,
            'recipient_name' => $ceo->name,
            'body' => $reply,
            'idempotency_key' => 'reply:'.($message['id'] ?? uniqid()),
        ]);

        return 'processed';
    }

    private function handleCustomer(Business $business, Contact $contact, array $message, ?string $text): string
    {
        // Customer text is stored as data only. It is never interpreted as a command.
        $contact->forceFill(['whatsapp_last_inbound_at' => now()])->save();
        $this->logInbound($business, 'inbound_customer', $contact->phone, $contact->displayName(), $text, $message);

        $actor = new Actor('system', 'whatsapp');
        $contact->tasks()->open()->get()->each(function ($task) use ($actor) {
            $this->tasks->addNote($task, 'Pelanggan membalas melalui WhatsApp. Semak Communication Centre.', $actor);
            if ($task->status === TaskStatus::WaitingForCustomer) {
                $this->tasks->changeStatus($task, TaskStatus::InProgress, $actor, 'Pelanggan telah membalas.');
            }
        });

        return 'processed';
    }

    private function verifyPhone(Business $business, string $from, string $code, array $message): string
    {
        $user = User::where('business_id', $business->id)
            ->where('phone', $from)
            ->whereNotNull('phone_verification_hash')
            ->where('phone_verification_expires_at', '>', now())
            ->first();

        $this->logInbound($business, 'inbound_verification', $from, $user?->name, 'VERIFY ******', $message);

        if (! $user || ! hash_equals($user->phone_verification_hash, hash('sha256', $user->id.'|'.$code))) {
            return 'ignored';
        }

        $user->forceFill([
            'phone_verified_at' => now(),
            'whatsapp_opt_in_at' => $user->whatsapp_opt_in_at ?? now(),
            'whatsapp_last_inbound_at' => now(),
            'phone_verification_hash' => null,
            'phone_verification_expires_at' => null,
        ])->save();

        $this->audit->record($business->id, 'user.phone_verified', $user, ['phone' => Phone::mask($from)], Actor::user($user, 'whatsapp'));

        $this->communications->queue($business, 'whatsapp', [
            'type' => 'ceo_reply',
            'recipient' => $from,
            'recipient_name' => $user->name,
            'body' => "✅ Nombor anda disahkan. Saya Dyno Focus, AI Vice CEO anda.\nBalas *help* untuk senarai arahan.",
            'idempotency_key' => 'verify:'.($message['id'] ?? uniqid()),
        ]);

        return 'processed';
    }

    private function resolveBusiness(string $phoneNumberId, string $from): ?Business
    {
        if ($phoneNumberId !== '' && ($business = Business::where('whatsapp_phone_number_id', $phoneNumberId)->first())) {
            return $business;
        }

        // Single shared number (default config): only resolve when the sender belongs to exactly one business.
        if ($phoneNumberId === '' || $phoneNumberId !== (string) config('vice_ceo.whatsapp.phone_number_id')) {
            return null;
        }

        $ids = User::where('phone', $from)->pluck('business_id')
            ->merge(Contact::where('phone', $from)->pluck('business_id'))
            ->filter()->unique()->values();

        return $ids->count() === 1 ? Business::find($ids->first()) : null;
    }

    private function extractText(array $message): ?string
    {
        return match ($message['type'] ?? null) {
            'text' => $message['text']['body'] ?? null,
            'interactive' => $message['interactive']['button_reply']['title'] ?? $message['interactive']['list_reply']['title'] ?? null,
            'button' => $message['button']['text'] ?? null,
            default => null,
        };
    }

    private function logInbound(Business $business, string $type, string $from, ?string $name, ?string $text, array $message): void
    {
        CommunicationLog::create([
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'direction' => 'inbound',
            'type' => $type,
            'recipient' => $from,
            'recipient_name' => $name,
            'body' => $text ?? '['.($message['type'] ?? 'unknown').']',
            'provider' => 'meta',
            'provider_message_id' => $message['id'] ?? null,
            'status' => CommunicationLog::STATUS_RECEIVED,
            'sent_at' => isset($message['timestamp']) ? Carbon::createFromTimestamp((int) $message['timestamp']) : now(),
        ]);
    }
}
