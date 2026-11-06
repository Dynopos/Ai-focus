<?php

namespace App\Http\Controllers;

use App\Enums\ActionType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Exceptions\ApprovalException;
use App\Models\Task;
use App\Services\Executive\ApprovalService;
use App\Services\Executive\FollowUpDrafter;
use App\Services\Executive\PriorityEngine;
use App\Services\Executive\TaskService;
use App\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $tasks) {}

    public function index(Request $request, PriorityEngine $priorities): View
    {
        $business = $request->user()->business;
        $filter = $request->query('status', 'open');

        $query = $business->tasks()->with(['contact', 'assignee', 'project']);

        match ($filter) {
            'open' => $query->open(),
            'overdue' => $query->overdue(),
            'all' => null,
            default => in_array($filter, array_column(TaskStatus::cases(), 'value'), true) ? $query->where('status', $filter) : $query->open(),
        };

        if ($search = trim((string) $request->query('q'))) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%"));
        }

        $tasks = $query->get()->sortByDesc(fn (Task $t) => $t->status->isOpen() ? $priorities->score($t) : -1)->values();

        return view('tasks.index', ['tasks' => $tasks, 'filter' => $filter, 'search' => $search, 'priorities' => $priorities]);
    }

    public function create(Request $request): View
    {
        return view('tasks.form', ['task' => new Task(['priority' => 'medium', 'strategic_importance' => 3]), 'business' => $request->user()->business]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $request->user()->business;
        [$task, $created] = $this->tasks->create($business, $this->validated($request), Actor::user($request->user(), 'dashboard', $request->ip()));

        return redirect()->route('tasks.show', $task)->with('status', $created ? 'Task created.' : 'An open task with the same title already exists — showing it instead.');
    }

    public function show(Task $task): View
    {
        Gate::authorize('view', $task);
        $task->load(['contact', 'assignee', 'project', 'activities.user', 'approvals' => fn ($q) => $q->latest()]);

        return view('tasks.show', ['task' => $task, 'members' => $task->business->users()->orderBy('name')->get()]);
    }

    public function edit(Request $request, Task $task): View
    {
        Gate::authorize('update', $task);

        return view('tasks.form', ['task' => $task, 'business' => $request->user()->business]);
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);
        $this->tasks->update($task, $this->validated($request, true), Actor::user($request->user(), 'dashboard', $request->ip()));

        return redirect()->route('tasks.show', $task)->with('status', 'Task updated.');
    }

    public function status(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);
        $data = $request->validate([
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'note' => ['nullable', 'string', 'max:1000'],
            'completion_evidence' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->tasks->changeStatus($task, TaskStatus::from($data['status']), Actor::user($request->user(), 'dashboard', $request->ip()), $data['note'] ?? null, $data['completion_evidence'] ?? null);

        return back()->with('status', 'Status updated to '.TaskStatus::from($data['status'])->label().'.');
    }

    public function followUp(Request $request, Task $task, FollowUpDrafter $drafter): RedirectResponse
    {
        Gate::authorize('update', $task);

        try {
            $approval = $drafter->draftForTask($task, Actor::user($request->user(), 'dashboard', $request->ip()));
        } catch (ApprovalException $e) {
            return back()->withErrors(['follow_up' => $e->getMessage()]);
        }

        return redirect()->route('approvals.show', $approval)->with('status', 'Draft prepared. Nothing is sent until the CEO approves it.');
    }

    /** Reassigning an important task is a Level 2 action: it goes to the Decision Room. */
    public function requestReassign(Request $request, Task $task, ApprovalService $approvals): RedirectResponse
    {
        Gate::authorize('update', $task);
        $business = $request->user()->business;

        $data = $request->validate([
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('business_id', $business->id)],
            'assignee_name' => ['nullable', 'string', 'max:120'],
        ]);

        $newOwner = $data['assigned_to'] ? $business->users()->find($data['assigned_to'])->name : ($data['assignee_name'] ?: 'Unassigned');

        $approval = $approvals->propose($business, ActionType::ReassignTask, [
            'task_id' => $task->id,
            'assigned_to' => $data['assigned_to'] ? (int) $data['assigned_to'] : null,
            'assignee_name' => $data['assigned_to'] ? null : ($data['assignee_name'] ?: null),
        ], [
            'title' => "Reassign {$task->reference()} to {$newOwner}",
            'situation' => "\"{$task->title}\" is currently owned by {$task->assigneeLabel()}.",
            'recommendation' => "Assign to {$newOwner}.",
            'expected_impact' => 'Clear ownership for an important task.',
            'preview' => "{$task->assigneeLabel()} → {$newOwner}",
        ], Actor::user($request->user(), 'dashboard', $request->ip()), $task);

        return redirect()->route('approvals.show', $approval)->with('status', 'Reassignment sent to the Decision Room.');
    }

    private function validated(Request $request, bool $updating = false): array
    {
        $businessId = $request->user()->business_id;

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'status' => [$updating ? 'required' : 'nullable', Rule::enum(TaskStatus::class)],
            'deadline' => ['nullable', 'date'],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('business_id', $businessId)],
            'contact_id' => ['nullable', Rule::exists('contacts', 'id')->where('business_id', $businessId)],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('business_id', $businessId)],
            'assignee_name' => ['nullable', 'string', 'max:120'],
            'revenue_potential' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'financial_impact' => ['nullable', 'numeric', 'min:-999999999', 'max:999999999'],
            'strategic_importance' => ['required', 'integer', 'between:1,5'],
        ]);

        if (! empty($data['deadline'])) {
            // Interpret the form's local time in the business timezone.
            $data['deadline'] = Carbon::parse($data['deadline'], $request->user()->business->timezone)->utc();
        }

        return array_filter($data, fn ($v) => $v !== null) + array_fill_keys(['description', 'deadline', 'project_id', 'contact_id', 'assigned_to', 'assignee_name', 'revenue_potential', 'financial_impact'], null);
    }
}
