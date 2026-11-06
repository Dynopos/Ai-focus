<?php

namespace App\Services\Executive;

use App\Enums\TaskStatus;
use App\Models\Business;
use App\Models\Task;
use Illuminate\Support\Collection;

/**
 * Ranks open tasks by urgency, financial impact and strategic importance.
 * Deterministic so the ranking is explainable and testable.
 */
class PriorityEngine
{
    public function score(Task $task): int
    {
        $score = $task->priority->weight();

        // Urgency
        if ($task->deadline) {
            $hours = now()->diffInHours($task->deadline, false);
            $score += match (true) {
                $hours < 0 => 60,       // overdue
                $hours <= 24 => 40,
                $hours <= 72 => 20,
                $hours <= 168 => 8,
                default => 0,
            };
        }

        // Financial impact: log-scaled so a RM100k deal doesn't drown everything else.
        $money = (float) ($task->revenue_potential ?? 0) + abs((float) ($task->financial_impact ?? 0));
        if ($money > 0) {
            $score += (int) min(50, round(log10($money + 1) * 9));
        }

        // Strategic importance 1..5
        $score += ($task->strategic_importance ?? 3) * 5;

        // Blocked on the CEO: unblock quickly.
        if ($task->status === TaskStatus::WaitingForCeo) {
            $score += 15;
        }

        return $score;
    }

    /** @return Collection<int, array{task: Task, score: int, reasons: list<string>}> */
    public function rank(Business $business, int $limit = 3): Collection
    {
        return $business->tasks()->open()->with(['contact', 'assignee', 'project'])->get()
            ->map(fn (Task $task) => [
                'task' => $task,
                'score' => $this->score($task),
                'reasons' => $this->reasons($task),
            ])
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    /** @return list<string> */
    public function reasons(Task $task): array
    {
        $reasons = [];

        if ($task->isOverdue()) {
            $reasons[] = 'overdue since '.$task->deadline->diffForHumans();
        } elseif ($task->deadline && $task->deadline->lessThanOrEqualTo(now()->addDay())) {
            $reasons[] = 'due '.$task->deadline->diffForHumans();
        }

        if ($task->revenue_potential) {
            $reasons[] = 'revenue potential RM'.number_format((float) $task->revenue_potential, 2);
        }

        if (in_array($task->priority->value, ['high', 'critical'], true)) {
            $reasons[] = $task->priority->value.' priority';
        }

        if ($task->status === TaskStatus::WaitingForCeo) {
            $reasons[] = 'waiting for your decision';
        }

        if (($task->strategic_importance ?? 3) >= 4) {
            $reasons[] = 'strategically important';
        }

        return $reasons;
    }
}
