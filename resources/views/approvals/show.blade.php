@extends('layouts.app')
@section('title', 'Decision '.$approval->code)
@php use App\Support\Ui; $tz = auth()->user()->business->timezone; $canDecide = auth()->user()->can('decide', $approval); @endphp
@section('content')
<div class="topbar">
    <div>
        <p class="muted small">Decision {{ $approval->code }} · {{ $approval->authority_level->label() }} · version {{ $approval->version }} · requested by {{ $approval->requested_by }} {{ $approval->created_at->diffForHumans() }}</p>
        <h1>{{ $approval->title }}</h1>
        <div class="actions">
            <span class="badge {{ Ui::approvalClass($approval->status) }}">{{ $approval->status->label() }}{{ $approval->status->value === 'approved' ? ' · queued for execution' : '' }}</span>
            <span class="badge">{{ $approval->action_type->label() }}</span>
            @if ($approval->task)<a class="badge" href="{{ route('tasks.show', $approval->task) }}">{{ $approval->task->reference() }}</a>@endif
        </div>
    </div>
</div>

<div class="grid grid-2">
    <section class="card decision">
        <dl>
            <dt>Business situation</dt><dd>{{ $approval->situation ?? '—' }}</dd>
            <dt>Analysis</dt><dd>{{ $approval->analysis ?? '—' }}</dd>
            <dt>Recommended action</dt><dd>{{ $approval->recommendation ?? '—' }}</dd>
            <dt>Expected impact</dt><dd>{{ $approval->expected_impact ?? '—' }}</dd>
            @if ($approval->recipient_name)<dt>Recipient</dt><dd>{{ $approval->recipient_name }} <span class="muted">{{ $approval->recipient_address }}</span></dd>@endif
            <dt>Expires</dt><dd>{{ $approval->expires_at?->timezone($tz)->format('d M Y H:i') ?? '—' }}</dd>
        </dl>
        @if ($approval->preview)
            <h3 style="margin-top:16px;">Preview (exactly what will be sent / done)</h3>
            <div class="preview">{{ $approval->preview }}</div>
        @endif
    </section>

    <div>
        @if ($approval->isPending() && $canDecide)
            <section class="card" style="margin-bottom:16px;">
                <h2>CEO Decision</h2>
                @if ($approval->authority_level->value === 3)
                    <p class="small muted">Level 3: approving records your decision only. The AI will not spend, sign, hire or commit anything.</p>
                @endif
                <form method="POST" action="{{ route('approvals.approve', $approval) }}" class="field">
                    @csrf
                    <input type="hidden" name="version" value="{{ $approval->version }}">
                    <div class="field"><input type="text" name="note" placeholder="Note (optional)"></div>
                    <div class="actions">
                        <button class="btn ok" type="submit">Approve v{{ $approval->version }}</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('approvals.reject', $approval) }}" class="actions">
                    @csrf
                    <input type="text" name="note" placeholder="Reason (optional)" style="flex:1;">
                    <button class="btn" type="submit">Reject</button>
                </form>
            </section>

            @if (array_key_exists('message', $approval->payload))
                <section class="card" style="margin-bottom:16px;">
                    <h2>Modify draft</h2>
                    <form method="POST" action="{{ route('approvals.modify', $approval) }}">
                        @csrf
                        @if (array_key_exists('subject', $approval->payload))
                            <div class="field"><label for="subject">Subject</label><input id="subject" type="text" name="subject" value="{{ $approval->payload['subject'] }}"></div>
                        @endif
                        <div class="field"><label for="message">Message</label><textarea id="message" name="message" required>{{ $approval->payload['message'] }}</textarea></div>
                        <button class="btn" type="submit">Save as v{{ $approval->version + 1 }}</button>
                        <p class="hint">Saving creates a new version; earlier approvals (e.g. from WhatsApp) will no longer match.</p>
                    </form>
                </section>
            @endif
        @elseif ($approval->isPending())
            <section class="card" style="margin-bottom:16px;"><p class="muted">Waiting for the CEO to decide.</p></section>
        @endif

        @if ($approval->status->value === 'failed' && $canDecide)
            <section class="card" style="margin-bottom:16px;border-color:var(--red);">
                <h2>Execution failed</h2>
                <p style="color:var(--red);">{{ $approval->error }}</p>
                <form method="POST" action="{{ route('approvals.retry', $approval) }}">@csrf<button class="btn primary" type="submit">Retry</button></form>
                <p class="hint">Retries reuse the same idempotency key: a message that actually went out is never sent twice.</p>
            </section>
        @endif

        <section class="card">
            <h2>Audit trail</h2>
            <div class="decision" style="border:0;"><dl>
                <dt>Decided by</dt><dd>{{ $approval->decidedBy?->name ?? '—' }} @if ($approval->decided_via)<span class="muted">via {{ $approval->decided_via }} (v{{ $approval->decided_version }})</span>@endif</dd>
                <dt>Decided at</dt><dd>{{ $approval->decided_at?->timezone($tz)->format('d M Y H:i:s') ?? '—' }}</dd>
                <dt>Note</dt><dd>{{ $approval->decision_note ?? '—' }}</dd>
                <dt>Executed at</dt><dd>{{ $approval->executed_at?->timezone($tz)->format('d M Y H:i:s') ?? '—' }}</dd>
                <dt>Result</dt><dd><code class="small">{{ $approval->execution_result ? json_encode($approval->execution_result) : '—' }}</code></dd>
                <dt>Payload hash</dt><dd><code class="small">{{ substr($approval->payload_hash, 0, 16) }}…</code></dd>
            </dl></div>
            @if ($approval->communications->isNotEmpty())
                <h3 style="margin-top:12px;">Messages</h3>
                <ul class="list small">
                    @foreach ($approval->communications as $log)
                        <li><a href="{{ route('communications.show', $log) }}">#{{ $log->id }} {{ str_replace('_', ' ', $log->type) }}</a> → {{ $log->recipient_name ?? $log->recipient }} <span class="badge {{ Ui::commClass($log->status) }}">{{ $log->status }}</span></li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</div>
@endsection
