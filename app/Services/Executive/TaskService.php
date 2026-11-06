<?php

namespace App\Services\Executive;

use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Business;
use App\Models\Task;
use App\Support\Actor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The single write path for tasks. Dashboard, WhatsApp, email and AI all go
 * through here so every channel sees the same state and history.
 */
class TaskService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Create a task, or return the existing open task with the same title and
     * customer (duplicate guard). The second value says whether it was created.
     *
     * @return array{0: Task, 1: bool}
     */
    public function create(Business $business, array $data, Actor $actor): array
    {
        $dedupeKey = $this->dedupeKey($data['title'], $data['contact_id'] ?? null);

        $existing = $business->tasks()->open()->where('dedupe_key', $dedupeKey)->first();
        if ($existing) {
            return [$existing, false];
        }

        $data['source'] ??= match ($actor->channel) {
            'whatsapp' => TaskSource::WhatsApp->value,
            'email' => TaskSource::Email->value,
            'ai' => TaskSource::Ai->value,
            default => TaskSource::Dashboard->value,
        };
        $data['status'] ??= TaskStatus::Pending->value;
        $data['dedupe_key'] = $dedupeKey;

        $task = DB::transaction(function () use ($business, $data, $actor) {
            $task = $business->tasks()->create($data);
            $this->logActivity($task, $actor, 'created', null, $task->status->value);

            return $task;
        });

        $this->audit->record($business->id, 'task.created', $task, ['title' => $task->title], $actor);

        return [$task, true];
    }

    public function update(Task $task, array $data, Actor $actor): Task
    {
        $newStatus = isset($data['status']) ? TaskStatus::from($data['status']) : null;
        unset($data['status']);

        DB::transaction(function () use ($task, $data, $actor) {
            $before = $task->only(array_keys($data));
            $task->fill($data);

            if (array_key_exists('title', $data) || array_key_exists('contact_id', $data)) {
                $task->dedupe_key = $this->dedupeKey($task->title, $task->contact_id);
            }

            $dirty = $task->getDirty();
            if ($dirty === []) {
                return;
            }
            $task->save();

            $action = array_key_exists('assigned_to', $dirty) || array_key_exists('assignee_name', $dirty) ? 'reassigned' : 'updated';
            $this->logActivity($task, $actor, $action, null, null, null, [
                'changed' => array_keys($dirty),
                'before' => array_intersect_key($before, $dirty),
            ]);
        });

        if ($newStatus && $newStatus !== $task->status) {
            $this->changeStatus($task, $newStatus, $actor);
        }

        return $task->refresh();
    }

    public function changeStatus(Task $task, TaskStatus $status, Actor $actor, ?string $note = null, ?string $evidence = null): Task
    {
        $from = $task->status;

        if ($from === $status && $evidence === null) {
            return $task;
        }

        DB::transaction(function () use ($task, $status, $actor, $note, $evidence, $from) {
            $task->status = $status;
            $task->completed_at = $status === TaskStatus::Completed ? now() : null;
            if ($evidence !== null && $evidence !== '') {
                $task->completion_evidence = $evidence;
            }
            $task->save();

            $this->logActivity($task, $actor, 'status_changed', $from->value, $status->value, $note);
        });

        $this->audit->record($task->business_id, 'task.status_changed', $task, [
            'from' => $from->value,
            'to' => $status->value,
        ], $actor);

        return $task;
    }

    public function addNote(Task $task, string $note, Actor $actor): void
    {
        $this->logActivity($task, $actor, 'note', null, null, $note);
    }

    /**
     * Find open tasks matching free text from the CEO, e.g. "Fatihah" or "T-12".
     *
     * @return Collection<int, Task>
     */
    public function search(Business $business, string $query, bool $openOnly = true): Collection
    {
        $query = trim($query);

        if (preg_match('/\bT-?(\d+)\b/i', $query, $m)) {
            $task = $business->tasks()->whereKey((int) $m[1])->first();

            return $task ? collect([$task]) : collect();
        }

        $words = collect(preg_split('/\s+/', Str::lower($query)))
            ->map(fn ($w) => trim($w, " \t\n\r\0\x0B.,!?:;\"'"))
            ->filter(fn ($w) => mb_strlen($w) >= 3)
            ->values();

        if ($words->isEmpty()) {
            return collect();
        }

        $base = $business->tasks()->with('contact');
        if ($openOnly) {
            $base->open();
        }

        $candidates = $base->get();

        $scored = $candidates->map(function (Task $task) use ($words) {
            $haystack = Str::lower($task->title.' '.$task->description.' '.$task->contact?->name.' '.$task->contact?->company);
            $hits = $words->filter(fn ($w) => str_contains($haystack, $w))->count();

            return ['task' => $task, 'score' => $hits];
        })->filter(fn ($row) => $row['score'] > 0)->sortByDesc('score')->values();

        if ($scored->isEmpty()) {
            return collect();
        }

        $best = $scored->first()['score'];

        return $scored->filter(fn ($row) => $row['score'] === $best)->pluck('task')->values();
    }

    private function logActivity(Task $task, Actor $actor, string $action, ?string $from = null, ?string $to = null, ?string $note = null, array $meta = []): void
    {
        $task->activities()->create([
            'business_id' => $task->business_id,
            'user_id' => $actor->user?->id,
            'actor' => $actor->type,
            'channel' => $actor->channel,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'meta' => $meta ?: null,
        ]);
    }

    private function dedupeKey(string $title, ?int $contactId): string
    {
        return sha1(Str::of($title)->lower()->squish()->replaceMatches('/[^\pL\pN ]+/u', '').'|'.($contactId ?? ''));
    }
}
