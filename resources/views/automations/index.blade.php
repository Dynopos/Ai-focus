@extends('layouts.app')
@section('title', 'Automations')
@section('content')
<div class="topbar"><div><h1>Automations</h1><p class="muted">Scheduled briefings and reminders, run by the server queue (not your browser).</p></div></div>
@unless ($masterSwitch)
    <div class="flash err">The server master switch is OFF (<code>VICE_CEO_AUTOMATIONS_ENABLED=false</code>). Nothing will be sent on schedule until an administrator turns it on. "Send now" still works for previews.</div>
@endunless
<form method="POST" action="{{ route('automations.update') }}" class="card" style="margin-bottom:16px;">
    @csrf @method('PUT')
    <div class="table-wrap">
    <table class="table">
        <thead><tr><th>Automation</th><th>On</th><th>Time</th><th>Channel</th><th>Respect quiet hours</th><th>Last run</th><th></th></tr></thead>
        <tbody>
        @foreach ($automations as $a)
            <tr>
                <td><b>{{ $a->label() }}</b></td>
                <td><input type="hidden" name="automations[{{ $a->id }}][enabled]" value="0"><input type="checkbox" name="automations[{{ $a->id }}][enabled]" value="1" @checked($a->enabled) aria-label="Enable {{ $a->label() }}"></td>
                <td><input type="time" name="automations[{{ $a->id }}][send_time]" value="{{ $a->send_time }}" required style="max-width:130px;"></td>
                <td><select name="automations[{{ $a->id }}][channel]" style="max-width:140px;"><option value="email" @selected($a->channel === 'email')>Email</option><option value="whatsapp" @selected($a->channel === 'whatsapp')>WhatsApp</option></select></td>
                <td><input type="hidden" name="automations[{{ $a->id }}][respect_quiet_hours]" value="0"><input type="checkbox" name="automations[{{ $a->id }}][respect_quiet_hours]" value="1" @checked($a->respect_quiet_hours) aria-label="Respect quiet hours"></td>
                <td class="small muted">{{ $a->last_run_at?->diffForHumans() ?? 'never' }}</td>
                <td><button class="btn small" type="submit" form="run-{{ $a->id }}">Send now</button></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    <div class="grid grid-3" style="margin-top:12px;">
        <div class="field"><label for="timezone">Timezone</label><input id="timezone" type="text" name="timezone" value="{{ $business->timezone }}" required></div>
        <div class="field"><label for="quiet_hours_start">Quiet hours start</label><input id="quiet_hours_start" type="time" name="quiet_hours_start" value="{{ $business->quiet_hours_start ? substr($business->quiet_hours_start, 0, 5) : '' }}"></div>
        <div class="field"><label for="quiet_hours_end">Quiet hours end</label><input id="quiet_hours_end" type="time" name="quiet_hours_end" value="{{ $business->quiet_hours_end ? substr($business->quiet_hours_end, 0, 5) : '' }}"></div>
    </div>
    <button class="btn primary" type="submit">Save</button>
</form>
@foreach ($automations as $a)
    <form id="run-{{ $a->id }}" method="POST" action="{{ route('automations.run', $a) }}">@csrf</form>
@endforeach
<section class="card">
    <h2>Scheduled activity</h2>
    <div class="table-wrap">
    <table class="table">
        <thead><tr><th>Date</th><th>Automation</th><th>Status</th><th>Note</th></tr></thead>
        <tbody>
        @forelse ($runs as $run)
            <tr><td class="small">{{ $run->run_date->format('d M Y') }}</td><td>{{ \App\Models\Automation::DEFAULTS[$run->automation_key]['label'] ?? $run->automation_key }}</td><td><span class="badge {{ $run->status === 'completed' ? 'ok' : ($run->status === 'failed' ? 'red' : 'warn') }}">{{ $run->status }}</span></td><td class="small muted">{{ $run->note }}</td></tr>
        @empty
            <tr><td colspan="4" class="muted">No scheduled runs yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</section>
@endsection
