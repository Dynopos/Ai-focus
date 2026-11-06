@extends('layouts.app')
@section('title', 'Decision Room')
@php use App\Support\Ui; @endphp
@section('content')
<div class="topbar">
    <div><h1>Decision Room</h1><p class="muted">The AI proposes. You decide. Nothing external happens without your approval.</p></div>
    <a class="btn" href="{{ route('approvals.create') }}">Log a Level 3 decision</a>
</div>
<div class="grid grid-3" style="margin-bottom:16px;">
    <div class="card small"><b>Level 1 · Autonomous</b><br><span class="muted">Analyse, prioritise, report, draft, recommend.</span></div>
    <div class="card small"><b>Level 2 · CEO approval</b><br><span class="muted">Customer messages, external emails, reassignments, sensitive records.</span></div>
    <div class="card small"><b>Level 3 · CEO only</b><br><span class="muted">Spending, contracts, hiring, financial &amp; strategic commitments. AI never executes.</span></div>
</div>
<div class="tabs">
    @php
        $tabCounts = [
            'pending' => $counts['pending'] ?? 0,
            'approved' => ($counts['approved'] ?? 0) + ($counts['executing'] ?? 0) + ($counts['acknowledged'] ?? 0),
            'executed' => $counts['executed'] ?? 0,
            'failed' => $counts['failed'] ?? 0,
            'closed' => ($counts['rejected'] ?? 0) + ($counts['expired'] ?? 0) + ($counts['cancelled'] ?? 0),
        ];
    @endphp
    @foreach (['pending' => 'Proposed', 'approved' => 'Approved', 'executed' => 'Executed', 'failed' => 'Failed', 'closed' => 'Rejected / expired'] as $key => $label)
        <a href="{{ route('approvals.index', ['tab' => $key]) }}" class="{{ $tab === $key ? 'active' : '' }}">{{ $label }} ({{ $tabCounts[$key] }})</a>
    @endforeach
</div>
<div class="card table-wrap">
    <table class="table">
        <thead><tr><th>Code</th><th>Request</th><th>Level</th><th>Status</th><th>Recipient</th><th>When</th></tr></thead>
        <tbody>
        @forelse ($approvals as $a)
            <tr>
                <td><b>{{ $a->code }}</b></td>
                <td><a href="{{ route('approvals.show', $a) }}">{{ $a->title }}</a><div class="small muted">{{ $a->action_type->label() }} · v{{ $a->version }}</div></td>
                <td>L{{ $a->authority_level->value }}</td>
                <td><span class="badge {{ Ui::approvalClass($a->status) }}">{{ $a->status->label() }}</span>@if ($a->error)<div class="small" style="color:var(--red);">{{ \Illuminate\Support\Str::limit($a->error, 80) }}</div>@endif</td>
                <td>{{ $a->recipient_name ?? '—' }}</td>
                <td class="small muted nowrap">{{ $a->created_at->diffForHumans() }}@if ($a->status->value === 'pending')<br>expires {{ $a->expires_at?->diffForHumans() }}@endif</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">Nothing here.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $approvals->links('pagination') }}
</div>
@endsection
