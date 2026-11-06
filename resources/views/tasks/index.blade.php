@extends('layouts.app')
@section('title', 'Tasks')
@php use App\Support\Ui; @endphp
@section('content')
<div class="topbar">
    <div><h1>Tasks</h1><p class="muted">One shared task database for the dashboard, email and WhatsApp.</p></div>
    <a class="btn primary" href="{{ route('tasks.create') }}">+ New task</a>
</div>
<div class="tabs">
    @foreach (['open' => 'Open', 'overdue' => 'Overdue', 'waiting_for_ceo' => 'Waiting for CEO', 'waiting_for_customer' => 'Waiting for customer', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'all' => 'All'] as $key => $label)
        <a href="{{ route('tasks.index', ['status' => $key]) }}" class="{{ $filter === $key ? 'active' : '' }}">{{ $label }}</a>
    @endforeach
</div>
<form method="GET" class="actions" style="margin-bottom:12px;">
    <input type="hidden" name="status" value="{{ $filter }}">
    <input type="search" name="q" value="{{ $search }}" placeholder="Search tasks" style="max-width:320px;">
    <button class="btn" type="submit">Search</button>
</form>
<div class="card table-wrap">
    <table class="table">
        <thead><tr><th>Ref</th><th>Task</th><th>Status</th><th>Priority</th><th>Owner</th><th>Deadline</th><th>Revenue</th><th>Source</th></tr></thead>
        <tbody>
        @forelse ($tasks as $task)
            <tr>
                <td class="nowrap muted">{{ $task->reference() }}</td>
                <td><a href="{{ route('tasks.show', $task) }}"><b>{{ $task->title }}</b></a>@if ($task->contact)<div class="small muted">{{ $task->contact->displayName() }}</div>@endif</td>
                <td><span class="badge {{ Ui::taskClass($task->status) }}">{{ $task->status->label() }}</span></td>
                <td><span class="badge {{ Ui::priorityClass($task->priority->value) }}">{{ $task->priority->label() }}</span></td>
                <td>{{ $task->assigneeLabel() }}</td>
                <td class="nowrap {{ $task->isOverdue() ? '' : 'muted' }}" @if ($task->isOverdue()) style="color:var(--red);font-weight:600;" @endif>{{ $task->deadline?->timezone(auth()->user()->business->timezone)->format('d M Y H:i') ?? '—' }}</td>
                <td class="nowrap">{{ $task->revenue_potential ? 'RM'.number_format((float) $task->revenue_potential, 2) : '—' }}</td>
                <td><span class="badge">{{ $task->source->label() }}</span></td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">No tasks here.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
