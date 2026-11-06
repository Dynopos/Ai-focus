@extends('layouts.app')
@section('title', 'Executive Control Panel')
@php use App\Support\Ui; $c = $snapshot['counts']; @endphp
@section('content')
<div class="topbar">
    <div>
        <h1>Good {{ $business->localNow()->hour < 12 ? 'morning' : ($business->localNow()->hour < 18 ? 'afternoon' : 'evening') }}, {{ auth()->user()->name }}.</h1>
        <p class="muted">{{ $business->localNow()->format('l, j F Y · H:i') }} ({{ $business->timezone }})</p>
    </div>
    <div class="actions">
        <a class="btn primary" href="{{ route('tasks.create') }}">+ New task</a>
        <a class="btn" href="{{ route('approvals.create') }}">Log a CEO decision</a>
    </div>
</div>

<div class="grid grid-4" style="margin-bottom:16px;">
    <div class="card stat"><b>{{ $c['open'] }}</b><span class="muted">Open tasks</span></div>
    <div class="card stat {{ $c['overdue'] ? 'alert' : '' }}"><b>{{ $c['overdue'] }}</b><span class="muted">Overdue</span></div>
    <div class="card stat {{ $c['pending_approvals'] ? 'alert' : '' }}"><b>{{ $c['pending_approvals'] }}</b><span class="muted">Pending CEO decisions</span></div>
    <div class="card stat"><b>{{ $c['completed_today'] }}</b><span class="muted">Completed today</span></div>
</div>

<div class="grid grid-2" style="margin-bottom:16px;">
    <section class="card hero">
        <div class="card-head"><h2>Today's Top 3 Priorities</h2><a href="{{ route('tasks.index') }}" style="color:#fff;" class="small">All tasks →</a></div>
        @forelse ($top as $i => $row)
            <div class="row" style="padding:10px 0;border-bottom:1px solid rgba(255,255,255,.1);">
                <span class="rank">{{ $i + 1 }}</span>
                <div class="grow">
                    <a href="{{ route('tasks.show', $row['task']) }}" style="color:#fff;font-weight:600;">{{ $row['task']->title }}</a>
                    <div class="small muted">{{ $row['task']->reference() }} · {{ $row['task']->status->label() }}@if ($row['reasons']) · {{ implode(' · ', $row['reasons']) }}@endif</div>
                </div>
                @if ($row['task']->contact_id)
                    <form method="POST" action="{{ route('tasks.follow-up', $row['task']) }}">@csrf<button class="btn small" type="submit">Draft follow-up</button></form>
                @endif
            </div>
        @empty
            <p class="muted">No open tasks. Add your first task to get a prioritised plan.</p>
        @endforelse
        <p class="small muted" style="margin:10px 0 0;">Ranked by urgency, financial impact and strategic importance.</p>
    </section>

    <section class="card">
        <div class="card-head"><h2>Pending CEO Decisions</h2><a class="small" href="{{ route('approvals.index') }}">Decision Room →</a></div>
        <ul class="list">
            @forelse ($approvals->get('proposed', collect())->take(5) as $a)
                <li class="row">
                    <span class="badge warn">{{ $a->code }}</span>
                    <div class="grow"><a href="{{ route('approvals.show', $a) }}">{{ $a->title }}</a><div class="small muted">{{ $a->action_type->label() }} · L{{ $a->authority_level->value }} · expires {{ $a->expires_at?->diffForHumans() }}</div></div>
                </li>
            @empty
                <li class="muted">Nothing waiting for you.</li>
            @endforelse
        </ul>
    </section>
</div>

<section class="card" style="margin-bottom:16px;">
    <div class="card-head"><h2>Execution Progress</h2><span class="small muted">Proposed → Approved → Executed / Failed</span></div>
    <div class="lanes">
        @foreach (['proposed' => 'Proposed', 'approved' => 'Approved', 'executed' => 'Executed', 'failed' => 'Failed'] as $key => $label)
            <div class="lane">
                <h3>{{ $label }} ({{ $approvals->get($key, collect())->count() }})</h3>
                <ul class="list small">
                    @forelse ($approvals->get($key, collect())->take(4) as $a)
                        <li><a href="{{ route('approvals.show', $a) }}">{{ $a->code }}</a> {{ \Illuminate\Support\Str::limit($a->title, 48) }}</li>
                    @empty
                        <li class="muted">—</li>
                    @endforelse
                </ul>
            </div>
        @endforeach
    </div>
    <p class="small muted" style="margin:12px 0 0;">Tasks: {{ $c['in_progress'] }} in progress · {{ $c['waiting_for_customer'] }} waiting for customer · {{ $c['waiting_for_ceo'] }} waiting for CEO · {{ $c['overdue'] }} overdue.</p>
</section>

<div class="grid grid-2" style="margin-bottom:16px;">
    <section class="card">
        <div class="card-head"><h2>AI Vice CEO Recommendations</h2></div>
        @forelse ($recommendations as $rec)
            <div class="decision" style="padding:8px 12px;margin-bottom:10px;">
                <b>{{ $rec['title'] }}</b>
                <div class="small"><b>Situasi:</b> {{ $rec['situation'] }}</div>
                <div class="small"><b>Analisis:</b> {{ $rec['analysis'] }}</div>
                <div class="small"><b>Tindakan:</b> {{ $rec['action'] }}</div>
                <div class="small"><b>Impak:</b> {{ $rec['impact'] }}</div>
            </div>
        @empty
            <p class="muted">No recommendations yet — add tasks with deadlines and revenue potential.</p>
        @endforelse
        <p class="small muted" style="margin:0;">Based only on verified task data. {{ $snapshot['cashflow']['verified'] ? 'Revenue at stake in open follow-ups: RM'.number_format($snapshot['cashflow']['revenue_at_stake_in_open_follow_ups'], 2).'.' : 'No verified cashflow data.' }}</p>
    </section>

    <section class="card">
        <div class="card-head"><h2>Communication Centre</h2><a class="small" href="{{ route('communications.index') }}">All →</a></div>
        <ul class="list">
            @forelse ($communications as $log)
                <li class="row">
                    <span class="badge">{{ $log->channel === 'email' ? 'Email' : 'WA' }} {{ $log->direction === 'inbound' ? '←' : '→' }}</span>
                    <div class="grow"><a href="{{ route('communications.show', $log) }}">{{ $log->subject ?: \Illuminate\Support\Str::limit(strip_tags((string) $log->body), 60) }}</a>
                        <div class="small muted">{{ str_replace('_', ' ', $log->type) }} · {{ $log->recipient_name ?? $log->recipient }} · {{ $log->created_at->diffForHumans() }}</div></div>
                    <span class="badge {{ Ui::commClass($log->status) }}">{{ $log->status }}</span>
                </li>
            @empty
                <li class="muted">No messages yet.</li>
            @endforelse
        </ul>
    </section>
</div>

<section class="card">
    <div class="card-head"><h2>System Health</h2>@if ($health['failed_communications'])<a class="badge red" href="{{ route('communications.index', ['status' => 'failed']) }}">{{ $health['failed_communications'] }} failed messages</a>@endif</div>
    <ul class="list health">
        @foreach (['whatsapp' => 'WhatsApp API', 'ceo_phone' => 'CEO WhatsApp', 'email' => 'Email', 'ai' => 'AI reasoning', 'scheduler' => 'Scheduler', 'queue' => 'Queue'] as $key => $label)
            <li><span><span class="dot {{ $health[$key]['ok'] ? 'ok' : '' }}"></span><b>{{ $label }}</b></span><span class="small muted">{{ $health[$key]['label'] }}</span></li>
        @endforeach
    </ul>
</section>
@endsection
