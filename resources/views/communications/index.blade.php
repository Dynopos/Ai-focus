@extends('layouts.app')
@section('title', 'Communication Centre')
@php use App\Support\Ui; $tz = auth()->user()->business->timezone; @endphp
@section('content')
<div class="topbar"><div><h1>Communication Centre</h1><p class="muted">Every email and WhatsApp message, with delivery status and provider response.</p></div></div>
<form method="GET" class="actions" style="margin-bottom:12px;">
    <select name="channel" style="max-width:160px;"><option value="">All channels</option><option value="email" @selected(request('channel') === 'email')>Email</option><option value="whatsapp" @selected(request('channel') === 'whatsapp')>WhatsApp</option></select>
    <select name="direction" style="max-width:160px;"><option value="">In &amp; out</option><option value="outbound" @selected(request('direction') === 'outbound')>Outbound</option><option value="inbound" @selected(request('direction') === 'inbound')>Inbound</option></select>
    <select name="status" style="max-width:160px;"><option value="">Any status</option>@foreach (['queued', 'sent', 'delivered', 'read', 'failed', 'skipped', 'received'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach</select>
    <button class="btn" type="submit">Filter</button>
</form>
<div class="card table-wrap">
    <table class="table">
        <thead><tr><th>When</th><th>Channel</th><th>Type</th><th>To / from</th><th>Content</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($logs as $log)
            <tr>
                <td class="small nowrap">{{ $log->created_at->timezone($tz)->format('d M H:i') }}</td>
                <td><span class="badge">{{ $log->channel === 'email' ? 'Email' : 'WhatsApp' }} {{ $log->direction === 'inbound' ? '←' : '→' }}</span></td>
                <td class="small">{{ str_replace('_', ' ', $log->type) }}</td>
                <td class="small">{{ $log->recipient_name ?? '' }}<div class="muted">{{ $log->recipient }}</div></td>
                <td class="small"><a href="{{ route('communications.show', $log) }}">{{ $log->subject ?: \Illuminate\Support\Str::limit(strip_tags((string) $log->body), 70) }}</a>@if ($log->error)<div style="color:var(--red);">{{ \Illuminate\Support\Str::limit($log->error, 90) }}</div>@endif</td>
                <td><span class="badge {{ Ui::commClass($log->status) }}">{{ $log->status }}</span>@if ($log->attempts > 1)<div class="small muted">{{ $log->attempts }} attempts</div>@endif</td>
                <td>@if ($log->isRetryable() && ! ($log->approval_id && $log->type === 'customer_message'))<form method="POST" action="{{ route('communications.retry', $log) }}">@csrf<button class="btn small" type="submit">Retry</button></form>@endif</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No messages yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $logs->links('pagination') }}
</div>
@endsection
