<?php

namespace App\Services\Executive;

use App\Enums\ActionType;
use App\Enums\ApprovalStatus;
use App\Enums\TaskStatus;
use App\Exceptions\ApprovalException;
use App\Models\Approval;
use App\Models\CommunicationLog;
use App\Models\Contact;
use App\Models\Task;
use App\Models\User;
use App\Services\Messaging\CommunicationService;
use App\Support\Actor;
use Throwable;

/**
 * Executes approved Level 2 actions exactly once.
 */
class ActionExecutor
{
    /** Fields the AI may change on a task after CEO approval ("sensitive record" updates). */
    private const SENSITIVE_TASK_FIELDS = ['priority', 'deadline', 'revenue_potential', 'financial_impact', 'strategic_importance', 'contact_id', 'project_id'];

    public function __construct(
        private readonly CommunicationService $communications,
        private readonly TaskService $tasks,
        private readonly AuditLogger $audit,
    ) {}

    public function execute(int $approvalId): ?Approval
    {
        // Single-use lock: only one worker can move approved -> executing.
        $claimed = Approval::whereKey($approvalId)
            ->where('status', ApprovalStatus::Approved->value)
            ->update(['status' => ApprovalStatus::Executing->value, 'updated_at' => now()]);

        if ($claimed === 0) {
            return null;
        }

        $approval = Approval::with(['business', 'task', 'contact', 'decidedBy'])->findOrFail($approvalId);

        try {
            if (! $approval->action_type->isExecutable()) {
                throw new ApprovalException('Level 3 actions are never executed by the AI.');
            }
            if ($approval->decided_version !== $approval->version) {
                throw new ApprovalException('Approved version does not match the current version.');
            }
            if (! hash_equals($approval->payload_hash, Approval::hashPayload($approval->payload))) {
                throw new ApprovalException('Integrity check failed: payload changed after approval.');
            }

            $result = match ($approval->action_type) {
                ActionType::SendCustomerWhatsApp => $this->sendCustomerWhatsApp($approval),
                ActionType::SendCustomerEmail => $this->sendCustomerEmail($approval),
                ActionType::ReassignTask => $this->reassignTask($approval),
                ActionType::UpdateSensitiveRecord => $this->updateTask($approval),
                default => throw new ApprovalException('Unsupported action.'),
            };

            $approval->forceFill([
                'status' => ApprovalStatus::Executed->value,
                'executed_at' => now(),
                'execution_result' => $result,
                'error' => null,
            ])->save();

            $this->audit->record($approval->business_id, 'approval.executed', $approval, $result, Actor::system());
        } catch (Throwable $e) {
            $approval->forceFill([
                'status' => ApprovalStatus::Failed->value,
                'error' => $e->getMessage(),
            ])->save();

            $this->audit->record($approval->business_id, 'approval.failed', $approval, ['error' => $e->getMessage()], Actor::system());
            $this->alertCeo($approval, $e->getMessage());
        }

        return $approval->refresh();
    }

    private function sendCustomerWhatsApp(Approval $approval): array
    {
        $contact = $this->contactFor($approval);
        $phone = $approval->payload['phone'] ?? null;

        if (! $phone || $contact->phone !== $phone) {
            throw new ApprovalException('Recipient phone changed since approval. Prepare a new request.');
        }
        if (! $contact->whatsapp_opt_in_at && ! $contact->whatsapp_last_inbound_at) {
            throw new ApprovalException("No WhatsApp opt-in recorded for {$contact->name}. Record their opt-in on the contact first.");
        }

        $log = $this->communications->sendNow($approval->business, 'whatsapp', [
            'type' => 'customer_message',
            'recipient' => $phone,
            'recipient_name' => $contact->displayName(),
            'body' => $approval->payload['message'],
            'template' => 'customer_followup',
            'approval_id' => $approval->id,
            'idempotency_key' => "approval:{$approval->id}:v{$approval->version}",
        ]);

        return $this->afterCustomerMessage($approval, $log, 'WhatsApp');
    }

    private function sendCustomerEmail(Approval $approval): array
    {
        $contact = $this->contactFor($approval);
        $email = $approval->payload['email'] ?? null;

        if (! $email || $contact->email !== $email) {
            throw new ApprovalException('Recipient email changed since approval. Prepare a new request.');
        }

        $log = $this->communications->sendNow($approval->business, 'email', [
            'type' => 'customer_message',
            'recipient' => $email,
            'recipient_name' => $contact->name,
            'subject' => $approval->payload['subject'] ?? ('Susulan daripada '.$approval->business->name),
            'body' => nl2br(e($approval->payload['message'])),
            'approval_id' => $approval->id,
            'idempotency_key' => "approval:{$approval->id}:v{$approval->version}",
        ]);

        return $this->afterCustomerMessage($approval, $log, 'email');
    }

    private function afterCustomerMessage(Approval $approval, CommunicationLog $log, string $channel): array
    {
        if ($log->status !== CommunicationLog::STATUS_SENT) {
            throw new ApprovalException("Message not sent: {$log->error}");
        }

        if ($approval->task) {
            $actor = $this->actorFor($approval);
            $this->tasks->addNote($approval->task, "Follow-up {$channel} dihantar selepas kelulusan CEO (kod {$approval->code}).", $actor);

            if (in_array($approval->task->status, [TaskStatus::Pending, TaskStatus::InProgress, TaskStatus::WaitingForCeo], true)) {
                $this->tasks->changeStatus($approval->task, TaskStatus::WaitingForCustomer, $actor, 'Menunggu balasan pelanggan.');
            }
        }

        return ['communication_log_id' => $log->id, 'provider_message_id' => $log->provider_message_id];
    }

    private function reassignTask(Approval $approval): array
    {
        $task = $this->taskFor($approval);
        $userId = $approval->payload['assigned_to'] ?? null;

        if ($userId && ! User::whereKey($userId)->where('business_id', $approval->business_id)->exists()) {
            throw new ApprovalException('Assignee is not a member of this business.');
        }

        $this->tasks->update($task, [
            'assigned_to' => $userId,
            'assignee_name' => $approval->payload['assignee_name'] ?? null,
        ], $this->actorFor($approval));

        return ['task_id' => $task->id, 'assigned_to' => $userId, 'assignee_name' => $approval->payload['assignee_name'] ?? null];
    }

    private function updateTask(Approval $approval): array
    {
        $task = $this->taskFor($approval);
        $changes = array_intersect_key($approval->payload['changes'] ?? [], array_flip(self::SENSITIVE_TASK_FIELDS));

        if ($changes === []) {
            throw new ApprovalException('No permitted fields to update.');
        }

        $this->tasks->update($task, $changes, $this->actorFor($approval));

        return ['task_id' => $task->id, 'changed' => array_keys($changes)];
    }

    private function contactFor(Approval $approval): Contact
    {
        $contact = Contact::whereKey($approval->payload['contact_id'] ?? 0)->where('business_id', $approval->business_id)->first();

        if (! $contact) {
            throw new ApprovalException('Customer not found in this business.');
        }

        return $contact;
    }

    private function taskFor(Approval $approval): Task
    {
        $task = Task::whereKey($approval->payload['task_id'] ?? 0)->where('business_id', $approval->business_id)->first();

        if (! $task) {
            throw new ApprovalException('Task not found in this business.');
        }

        return $task;
    }

    private function actorFor(Approval $approval): Actor
    {
        return $approval->decidedBy ? Actor::user($approval->decidedBy, 'ai') : Actor::ai();
    }

    private function alertCeo(Approval $approval, string $error): void
    {
        $ceo = $approval->business->primaryCeo();
        if (! $ceo?->canCommandViaWhatsApp()) {
            return;
        }

        $this->communications->queue($approval->business, 'whatsapp', [
            'type' => 'alert',
            'recipient' => $ceo->phone,
            'recipient_name' => $ceo->name,
            'body' => "⚠️ *Tindakan gagal · Kod {$approval->code}*\n{$approval->title}\nSebab: ".mb_substr($error, 0, 300)."\nSemak dan cuba semula di Decision Room.",
            'template' => 'ceo_update',
            'idempotency_key' => "approval-failed:{$approval->id}:".now()->timestamp,
        ]);
    }
}
