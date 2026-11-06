<?php

namespace App\Services\Executive;

use App\Enums\ActionType;
use App\Enums\ApprovalStatus;
use App\Enums\AuthorityLevel;
use App\Exceptions\ApprovalException;
use App\Jobs\ExecuteApprovalJob;
use App\Models\Approval;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Task;
use App\Models\User;
use App\Services\Messaging\CommunicationService;
use App\Support\Actor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Decision Room. Approvals are bound to: the business, the authenticated CEO,
 * the exact payload (hash) and the exact version the CEO saw. They are single-use.
 */
class ApprovalService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CommunicationService $communications,
    ) {}

    /**
     * @param  array{title: string, situation?: ?string, analysis?: ?string, recommendation?: ?string, expected_impact?: ?string, recipient_name?: ?string, recipient_address?: ?string, preview?: ?string}  $meta
     */
    public function propose(Business $business, ActionType $type, array $payload, array $meta, Actor $actor, ?Task $task = null, ?Contact $contact = null, bool $notify = true): Approval
    {
        if ($task && $task->business_id !== $business->id) {
            throw new ApprovalException('Task does not belong to this business.');
        }
        if ($contact && $contact->business_id !== $business->id) {
            throw new ApprovalException('Contact does not belong to this business.');
        }

        $approval = DB::transaction(function () use ($business, $type, $payload, $meta, $actor, $task, $contact) {
            $approval = $business->approvals()->create([
                'code' => $this->uniqueCode($business),
                'task_id' => $task?->id,
                'contact_id' => $contact?->id,
                'requested_by_user_id' => $actor->user?->id,
                'requested_by' => $actor->type,
                'action_type' => $type->value,
                'authority_level' => $type->authorityLevel()->value,
                'title' => $meta['title'],
                'situation' => $meta['situation'] ?? null,
                'analysis' => $meta['analysis'] ?? null,
                'recommendation' => $meta['recommendation'] ?? null,
                'expected_impact' => $meta['expected_impact'] ?? null,
                'payload' => $payload,
                'payload_hash' => Approval::hashPayload($payload),
                'version' => 1,
                'recipient_name' => $meta['recipient_name'] ?? null,
                'recipient_address' => $meta['recipient_address'] ?? null,
                'preview' => $meta['preview'] ?? null,
                'status' => ApprovalStatus::Pending->value,
                'expires_at' => now()->addHours(config('vice_ceo.approval_ttl_hours', 24)),
            ]);

            $task?->forceFill(['ceo_approval_status' => 'pending'])->save();

            return $approval;
        });

        $this->audit->record($business->id, 'approval.proposed', $approval, [
            'action' => $type->value,
            'level' => $type->authorityLevel()->value,
            'code' => $approval->code,
        ], $actor);

        if ($notify) {
            $this->notifyCeo($approval);
        }

        return $approval;
    }

    /** CEO edits the proposal (e.g. the draft text). Bumps the version so older approvals no longer match. */
    public function modify(Approval $approval, User $ceo, array $payloadChanges, ?string $preview, string $channel = 'dashboard', ?string $note = null): Approval
    {
        $this->assertCeoOf($approval, $ceo);
        $this->expireIfStale($approval);

        return DB::transaction(function () use ($approval, $ceo, $payloadChanges, $preview, $channel, $note) {
            $locked = Approval::whereKey($approval->id)->lockForUpdate()->firstOrFail();
            $this->assertPending($locked);

            $payload = array_replace($locked->payload, $payloadChanges);
            $locked->forceFill([
                'payload' => $payload,
                'payload_hash' => Approval::hashPayload($payload),
                'version' => $locked->version + 1,
                'preview' => $preview ?? $locked->preview,
                'decision_note' => $note,
            ])->save();

            $this->audit->record($locked->business_id, 'approval.modified', $locked, [
                'version' => $locked->version,
                'changed' => array_keys($payloadChanges),
            ], Actor::user($ceo, $channel));

            return $locked;
        });
    }

    public function approve(Approval $approval, User $ceo, int $expectedVersion, string $channel = 'dashboard', ?string $note = null): Approval
    {
        $this->assertCeoOf($approval, $ceo);
        $this->expireIfStale($approval);

        $approval = DB::transaction(function () use ($approval, $ceo, $expectedVersion, $channel, $note) {
            $locked = Approval::whereKey($approval->id)->lockForUpdate()->firstOrFail();
            $this->assertPending($locked);

            if ($locked->version !== $expectedVersion) {
                throw new ApprovalException("This request changed after you saw it (now version {$locked->version}). Please review the latest version.");
            }

            if (! hash_equals($locked->payload_hash, Approval::hashPayload($locked->payload))) {
                throw new ApprovalException('Integrity check failed: the action parameters do not match what was proposed.');
            }

            $isCeoOnly = $locked->authority_level === AuthorityLevel::CeoOnly;

            $locked->forceFill([
                // Level 3: the CEO's decision is recorded; the AI never executes it.
                'status' => $isCeoOnly ? ApprovalStatus::Acknowledged->value : ApprovalStatus::Approved->value,
                'decided_by_user_id' => $ceo->id,
                'decided_at' => now(),
                'decided_via' => $channel,
                'decided_version' => $locked->version,
                'decision_note' => $note,
            ])->save();

            $locked->task?->forceFill(['ceo_approval_status' => 'approved'])->save();

            return $locked;
        });

        $this->audit->record($approval->business_id, 'approval.approved', $approval, [
            'version' => $approval->version,
            'code' => $approval->code,
        ], Actor::user($ceo, $channel));

        if ($approval->status === ApprovalStatus::Approved) {
            ExecuteApprovalJob::dispatch($approval->id);
        }

        return $approval;
    }

    public function reject(Approval $approval, User $ceo, string $channel = 'dashboard', ?string $note = null): Approval
    {
        $this->assertCeoOf($approval, $ceo);
        $this->expireIfStale($approval);

        $approval = DB::transaction(function () use ($approval, $ceo, $channel, $note) {
            $locked = Approval::whereKey($approval->id)->lockForUpdate()->firstOrFail();
            $this->assertPending($locked);

            $locked->forceFill([
                'status' => ApprovalStatus::Rejected->value,
                'decided_by_user_id' => $ceo->id,
                'decided_at' => now(),
                'decided_via' => $channel,
                'decided_version' => $locked->version,
                'decision_note' => $note,
            ])->save();

            $locked->task?->forceFill(['ceo_approval_status' => 'rejected'])->save();

            return $locked;
        });

        $this->audit->record($approval->business_id, 'approval.rejected', $approval, ['note' => $note], Actor::user($ceo, $channel));

        return $approval;
    }

    /** Re-run a failed execution. The idempotency key prevents double-sending if it actually went out. */
    public function retry(Approval $approval, User $ceo): Approval
    {
        $this->assertCeoOf($approval, $ceo);

        $updated = Approval::whereKey($approval->id)
            ->where('status', ApprovalStatus::Failed->value)
            ->update(['status' => ApprovalStatus::Approved->value, 'error' => null, 'updated_at' => now()]);

        if ($updated === 0) {
            throw new ApprovalException('Only failed actions can be retried.');
        }

        $this->audit->record($approval->business_id, 'approval.retried', $approval, [], Actor::user($ceo));
        ExecuteApprovalJob::dispatch($approval->id);

        return $approval->refresh();
    }

    public function expireStale(): int
    {
        return Approval::where('status', ApprovalStatus::Pending->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => ApprovalStatus::Expired->value, 'updated_at' => now()]);
    }

    /** Send the approval request to the CEO on WhatsApp (if they have a verified number). */
    public function notifyCeo(Approval $approval): void
    {
        $business = $approval->business;
        $ceo = $business->primaryCeo();

        if (! $ceo || ! $ceo->canCommandViaWhatsApp()) {
            return;
        }

        $levelNote = $approval->authority_level === AuthorityLevel::CeoOnly
            ? "\n_Level 3: AI tidak akan melaksanakan. Ini rekod keputusan anda sahaja._"
            : '';

        $body = "*Keputusan diperlukan · Kod {$approval->code}*\n"
            ."{$approval->title}\n"
            .($approval->recipient_name ? "Kepada: {$approval->recipient_name}".($approval->recipient_address ? " ({$approval->recipient_address})" : '')."\n" : '')
            .($approval->preview ? "\n─────\n".Str::limit($approval->preview, 700)."\n─────\n" : '')
            .($approval->expected_impact ? "\nImpak: {$approval->expected_impact}\n" : '')
            ."\nBalas *APPROVE {$approval->code}* atau *REJECT {$approval->code}*.".$levelNote;

        $this->communications->queue($business, 'whatsapp', [
            'type' => 'approval_request',
            'recipient' => $ceo->phone,
            'recipient_name' => $ceo->name,
            'body' => $body,
            'template' => 'ceo_update',
            'approval_id' => $approval->id,
            'idempotency_key' => "approval-notify:{$approval->id}:v{$approval->version}",
            'buttons' => [
                "apv:{$approval->ulid}:{$approval->version}:approve" => 'Approve',
                "apv:{$approval->ulid}:{$approval->version}:reject" => 'Reject',
            ],
        ]);

        $approval->forceFill(['notified_version' => $approval->version])->save();
    }

    private function assertCeoOf(Approval $approval, User $user): void
    {
        if (! $user->isCeo() || $user->business_id !== $approval->business_id) {
            throw new ApprovalException('Only the CEO of this business can decide on this request.');
        }
    }

    private function assertPending(Approval $approval): void
    {
        if ($approval->status !== ApprovalStatus::Pending) {
            throw new ApprovalException("This request is already {$approval->status->value}; approvals cannot be reused.");
        }

        if ($approval->isExpired()) {
            throw new ApprovalException('This request has expired. Ask the AI to prepare it again.');
        }
    }

    /** Persist expiry (outside any transaction) before refusing a late decision. */
    private function expireIfStale(Approval $approval): void
    {
        $expired = Approval::whereKey($approval->id)
            ->where('status', ApprovalStatus::Pending->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => ApprovalStatus::Expired->value, 'updated_at' => now()]);

        if ($expired > 0) {
            $this->audit->record($approval->business_id, 'approval.expired', $approval);

            throw new ApprovalException('This request has expired. Ask the AI to prepare it again.');
        }
    }

    private function uniqueCode(Business $business): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I

        do {
            $code = '';
            for ($i = 0; $i < 4; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while ($business->approvals()->where('code', $code)->exists());

        return $code;
    }
}
