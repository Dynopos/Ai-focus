@extends('layouts.app')
@section('title', $task->exists ? 'Edit task' : 'New task')
@php $tz = $business->timezone; @endphp
@section('content')
<div class="topbar"><h1>{{ $task->exists ? 'Edit '.$task->reference() : 'New task' }}</h1></div>
<form method="POST" action="{{ $task->exists ? route('tasks.update', $task) : route('tasks.store') }}" class="card" style="max-width:860px;">
    @csrf
    @if ($task->exists) @method('PUT') @endif
    <div class="field"><label for="title">Title</label><input id="title" type="text" name="title" value="{{ old('title', $task->title) }}" required maxlength="200"></div>
    <div class="field"><label for="description">Description</label><textarea id="description" name="description">{{ old('description', $task->description) }}</textarea></div>
    <div class="grid grid-3">
        <div class="field"><label for="priority">Priority</label>
            <select id="priority" name="priority">@foreach (\App\Enums\TaskPriority::cases() as $p)<option value="{{ $p->value }}" @selected(old('priority', $task->priority?->value) === $p->value)>{{ $p->label() }}</option>@endforeach</select></div>
        @if ($task->exists)
            <div class="field"><label for="status">Status</label>
                <select id="status" name="status">@foreach (\App\Enums\TaskStatus::cases() as $s)<option value="{{ $s->value }}" @selected(old('status', $task->status?->value) === $s->value)>{{ $s->label() }}</option>@endforeach</select></div>
        @endif
        <div class="field"><label for="deadline">Deadline ({{ $tz }})</label><input id="deadline" type="datetime-local" name="deadline" value="{{ old('deadline', $task->deadline?->timezone($tz)->format('Y-m-d\TH:i')) }}"></div>
        <div class="field"><label for="strategic_importance">Strategic importance (1–5)</label><input id="strategic_importance" type="number" min="1" max="5" name="strategic_importance" value="{{ old('strategic_importance', $task->strategic_importance ?? 3) }}" required></div>
        <div class="field"><label for="contact_id">Customer</label>
            <select id="contact_id" name="contact_id"><option value="">—</option>@foreach ($business->contacts()->orderBy('name')->get() as $contact)<option value="{{ $contact->id }}" @selected((string) old('contact_id', $task->contact_id) === (string) $contact->id)>{{ $contact->displayName() }}</option>@endforeach</select></div>
        <div class="field"><label for="project_id">Project</label>
            <select id="project_id" name="project_id"><option value="">—</option>@foreach ($business->projects()->orderBy('name')->get() as $project)<option value="{{ $project->id }}" @selected((string) old('project_id', $task->project_id) === (string) $project->id)>{{ $project->name }}</option>@endforeach</select></div>
        <div class="field"><label for="assigned_to">Assigned to (user)</label>
            <select id="assigned_to" name="assigned_to"><option value="">—</option>@foreach ($business->users()->orderBy('name')->get() as $member)<option value="{{ $member->id }}" @selected((string) old('assigned_to', $task->assigned_to) === (string) $member->id)>{{ $member->name }}</option>@endforeach</select></div>
        <div class="field"><label for="assignee_name">…or person without account</label><input id="assignee_name" type="text" name="assignee_name" value="{{ old('assignee_name', $task->assignee_name) }}"></div>
        <div class="field"><label for="revenue_potential">Revenue potential (RM)</label><input id="revenue_potential" type="number" step="0.01" min="0" name="revenue_potential" value="{{ old('revenue_potential', $task->revenue_potential) }}"><div class="hint">Optional. Only enter verified figures.</div></div>
        <div class="field"><label for="financial_impact">Estimated financial impact (RM)</label><input id="financial_impact" type="number" step="0.01" name="financial_impact" value="{{ old('financial_impact', $task->financial_impact) }}"></div>
    </div>
    <div class="actions"><button class="btn primary" type="submit">Save</button><a class="btn" href="{{ $task->exists ? route('tasks.show', $task) : route('tasks.index') }}">Cancel</a></div>
</form>
@endsection
