@extends('layouts.app')
@section('title', 'Audit Log')
@section('content')
<div class="topbar"><div><h1>Audit Log</h1><p class="muted">Who did what, when, and through which channel.</p></div></div>
<div class="card table-wrap">
    <table class="table">
        <thead><tr><th>When</th><th>Event</th><th>Actor</th><th>Channel</th><th>Details</th></tr></thead>
        <tbody>
        @forelse ($logs as $log)
            <tr>
                <td class="small nowrap">{{ $log->created_at->timezone(auth()->user()->business->timezone)->format('d M Y H:i:s') }}</td>
                <td><b>{{ $log->event }}</b>@if ($log->subject_type)<div class="small muted">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</div>@endif</td>
                <td class="small">{{ $log->user?->name ?? ucfirst($log->actor) }}</td>
                <td class="small">{{ $log->channel }}</td>
                <td class="small"><code style="word-break:break-all;">{{ $log->data ? \Illuminate\Support\Str::limit(json_encode($log->data, JSON_UNESCAPED_UNICODE), 160) : '' }}</code></td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">No activity yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $logs->links('pagination') }}
</div>
@endsection
