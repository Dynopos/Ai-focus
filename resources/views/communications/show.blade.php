@extends('layouts.app')
@section('title', 'Message #'.$log->id)
@php use App\Support\Ui; $tz = auth()->user()->business->timezone; @endphp
@section('content')
<div class="topbar">
    <div><p class="muted small">Message #{{ $log->id }} · {{ $log->channel }} · {{ $log->direction }} · {{ str_replace('_', ' ', $log->type) }}</p><h1>{{ $log->subject ?? ($log->direction === 'inbound' ? 'From '.($log->recipient_name ?? $log->recipient) : 'To '.($log->recipient_name ?? $log->recipient)) }}</h1></div>
    <div class="actions">
        <span class="badge {{ Ui::commClass($log->status) }}">{{ $log->status }}</span>
        @if ($log->isRetryable() && ! ($log->approval_id && $log->type === 'customer_message'))<form method="POST" action="{{ route('communications.retry', $log) }}">@csrf<button class="btn primary" type="submit">Retry</button></form>@endif
        @if ($log->approval)<a class="btn" href="{{ route('approvals.show', $log->approval) }}">Decision {{ $log->approval->code }}</a>@endif
    </div>
</div>
<div class="grid grid-2">
    <section class="card">
        <h2>Content</h2>
        @if ($log->channel === 'email' && $log->direction === 'outbound')
            <iframe title="Email preview" sandbox srcdoc="{{ $log->body }}" style="width:100%;height:640px;border:1px solid var(--border);border-radius:8px;background:#fff;"></iframe>
        @else
            <div class="preview">{{ $log->body }}</div>
        @endif
    </section>
    <section class="card decision">
        <h2>Delivery</h2>
        <dl>
            <dt>Recipient</dt><dd>{{ $log->recipient_name }} <span class="muted">{{ $log->recipient }}</span></dd>
            <dt>Provider</dt><dd>{{ $log->provider ?? '—' }}</dd>
            <dt>Provider message ID</dt><dd><code class="small">{{ $log->provider_message_id ?? '—' }}</code></dd>
            <dt>Attempts</dt><dd>{{ $log->attempts }}</dd>
            <dt>Created</dt><dd>{{ $log->created_at->timezone($tz)->format('d M Y H:i:s') }}</dd>
            <dt>Sent</dt><dd>{{ $log->sent_at?->timezone($tz)->format('d M Y H:i:s') ?? '—' }}</dd>
            <dt>Error</dt><dd style="color:var(--red);">{{ $log->error ?? '—' }}</dd>
            <dt>Idempotency key</dt><dd><code class="small">{{ $log->idempotency_key ?? '—' }}</code></dd>
            <dt>Provider response</dt><dd><code class="small" style="word-break:break-all;">{{ $log->provider_response ? json_encode($log->provider_response) : '—' }}</code></dd>
        </dl>
    </section>
</div>
@endsection
