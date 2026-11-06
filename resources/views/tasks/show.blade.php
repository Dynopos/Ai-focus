@extends('layouts.app')
@section('title', $task->reference())
@php use App\Support\Ui; $tz = $task->business->timezone; @endphp
@section('content')
<div class="topbar">
    <div>
        <p class="muted small">{{ $task->reference() }} · source {{ $task->source->label() }} · created {{ $task->created_at->timezone($tz)->format('d M Y H:i') }} · updated {{ $task->updated_at->diffForHumans() }}</p>
        <h1>{{ $task->title }}</h1>
        <div class="actions">
            <span class="badge {{ Ui::taskClass($task->status) }}">{{ $task->status->label() }}</span>
            <span class="badge {{ Ui::priorityClass($task->priority->value) }}">{{ $task->priority->label() }} priority</span>
            @if ($task->isOverdue())<span class="badge red">Overdue</span>@endif
            @if ($task->ceo_approval_status !== 'not_required')<span class="badge">CEO approval: {{ $task->ceo_approval_status }}</span>@endif
        </div>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('tasks.edit', $task) }}">Edit</a>
        @if ($task->contact_id && $task->status->isOpen())
            <form method="POST" action="{{ route('tasks.follow-up', $task) }}">@csrf<button class="btn primary" type="submit">Draft customer follow-up</button></form>
        @endif
    </div>
</div>

<div class="grid grid-2">
    <div>
        <section class="card" style="margin-bottom:16px;">
            <h2>Details</h2>
            <div class="decision" style="border:0;"><dl>
                <dt>Description</dt><dd>{!! nl2br(e($task->description ?: '—')) !!}</dd>
                <dt>Customer</dt><dd>{{ $task->contact?->displayName() ?? '—' }}</dd>
                <dt>Project</dt><dd>{{ $task->project?->name ?? '—' }}</dd>
                <dt>Owner</dt><dd>{{ $task->assigneeLabel() }}</dd>
                <dt>Deadline</dt><dd>{{ $task->deadline?->timezone($tz)->format('d M Y H:i') ?? '—' }}</dd>
                <dt>Revenue potential</dt><dd>{{ $task->revenue_potential ? 'RM'.number_format((float) $task->revenue_potential, 2) : '—' }}</dd>
                <dt>Financial impact</dt><dd>{{ $task->financial_impact ? 'RM'.number_format((float) $task->financial_impact, 2) : '—' }}</dd>
                <dt>Strategic importance</dt><dd>{{ $task->strategic_importance }}/5</dd>
                <dt>Completion evidence</dt><dd>{!! nl2br(e($task->completion_evidence ?: '—')) !!}</dd>
            </dl></div>
        </section>

        <section class="card" style="margin-bottom:16px;">
            <h2>Update status</h2>
            <form method="POST" action="{{ route('tasks.status', $task) }}">
                @csrf @method('PATCH')
                <div class="field"><select name="status">@foreach (\App\Enums\TaskStatus::cases() as $s)<option value="{{ $s->value }}" @selected($task->status === $s)>{{ $s->label() }}</option>@endforeach</select></div>
                <div class="field"><label for="note">Note</label><input id="note" type="text" name="note" maxlength="1000"></div>
                <div class="field"><label for="completion_evidence">Completion evidence</label><textarea id="completion_evidence" name="completion_evidence" style="min-height:60px;" placeholder="e.g. invoice number, customer confirmation"></textarea></div>
                <button class="btn primary" type="submit">Update</button>
            </form>
        </section>

        <section class="card">
            <h2>Request reassignment</h2>
            <p class="small muted">Important reassignments go to the Decision Room for CEO approval.</p>
            <form method="POST" action="{{ route('tasks.reassign', $task) }}" class="grid grid-2">
                @csrf
                <div class="field"><select name="assigned_to"><option value="">— person without account —</option>@foreach ($members as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></div>
                <div class="field"><input type="text" name="assignee_name" placeholder="Name (if no account)"></div>
                <div><button class="btn" type="submit">Send for approval</button></div>
            </form>
        </section>
    </div>

    <div>
        @if ($task->approvals->isNotEmpty())
            <section class="card" style="margin-bottom:16px;">
                <h2>Decisions</h2>
                <ul class="list">
                    @foreach ($task->approvals as $a)
                        <li class="row"><span class="badge {{ Ui::approvalClass($a->status) }}">{{ $a->status->label() }}</span><div class="grow"><a href="{{ route('approvals.show', $a) }}">{{ $a->code }} · {{ $a->title }}</a></div></li>
                    @endforeach
                </ul>
            </section>
        @endif
        <section class="card">
            <h2>Activity history</h2>
            <ul class="list timeline">
                @foreach ($task->activities as $act)
                    <li>
                        <div class="small"><b>{{ str_replace('_', ' ', $act->action) }}</b>
                            @if ($act->from_status || $act->to_status) {{ $act->from_status ?? '∅' }} → {{ $act->to_status }} @endif
                        </div>
                        @if ($act->note)<div class="small">{{ $act->note }}</div>@endif
                        <div class="small muted">{{ $act->user?->name ?? ucfirst($act->actor) }} via {{ $act->channel }} · {{ $act->created_at->timezone($tz)->format('d M Y H:i') }}</div>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
</div>
@endsection
