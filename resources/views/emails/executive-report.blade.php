@php
    $c = $snapshot['counts'];
    $red = '#d7141a';
@endphp
<!doctype html>
<html lang="ms">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f2f2f4;font-family:Arial,Helvetica,sans-serif;color:#1b1b1f;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f2f4;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="max-width:640px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;">
    <tr><td style="background:#0d0d10;padding:20px 24px;border-bottom:4px solid {{ $red }};">
        <table role="presentation" width="100%"><tr>
            <td style="vertical-align:middle;width:56px;"><img src="{{ url('/images/dyno-focus-logo-256.png') }}" width="48" height="48" alt="Dyno Focus" style="display:block;border-radius:8px;"></td>
            <td style="vertical-align:middle;color:#ffffff;">
                <div style="font-size:12px;letter-spacing:2px;color:#c9c9cf;">DYNO FOCUS · AI VICE CEO</div>
                <div style="font-size:20px;font-weight:bold;">{{ $moment === 'evening' ? 'Laporan Petang' : 'Executive Briefing Pagi' }}</div>
                <div style="font-size:13px;color:#c9c9cf;">{{ $business->name }} · {{ $date }}</div>
            </td>
        </tr></table>
    </td></tr>

    <tr><td style="padding:24px;">
        <h2 style="font-size:16px;margin:0 0 8px;color:{{ $red }};">1. Ringkasan eksekutif</h2>
        <p style="margin:0 0 20px;line-height:1.55;">{!! nl2br(e($summary)) !!}</p>

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
            <tr>
                @foreach ([['Terbuka', $c['open']], ['Lewat', $c['overdue']], [$moment === 'evening' ? 'Selesai hari ini' : 'Tunggu CEO', $moment === 'evening' ? $c['completed_today'] : $c['waiting_for_ceo']], ['Kelulusan', $c['pending_approvals']]] as [$label, $value])
                    <td style="background:#f6f6f8;border-radius:8px;padding:12px;text-align:center;width:25%;">
                        <div style="font-size:22px;font-weight:bold;">{{ $value }}</div>
                        <div style="font-size:12px;color:#5b5b66;">{{ $label }}</div>
                    </td>
                    @if (! $loop->last)<td style="width:8px;"></td>@endif
                @endforeach
            </tr>
        </table>

        @if ($moment === 'evening')
            <h2 style="font-size:16px;margin:0 0 8px;color:{{ $red }};">2. Tugasan selesai hari ini</h2>
            @include('emails.partials.task-list', ['items' => $snapshot['completed_today'], 'empty' => 'Tiada tugasan ditanda selesai hari ini.'])

            <h2 style="font-size:16px;margin:20px 0 8px;color:{{ $red }};">3. Tugasan masih pending</h2>
            @include('emails.partials.task-list', ['items' => array_slice($snapshot['still_pending'], 0, 12), 'empty' => 'Tiada.'])

            <h2 style="font-size:16px;margin:20px 0 8px;color:{{ $red }};">4. Status follow-up pelanggan</h2>
            @include('emails.partials.task-list', ['items' => $snapshot['customer_follow_ups'], 'empty' => 'Tiada follow-up pelanggan terbuka.'])

            <h2 style="font-size:16px;margin:20px 0 8px;color:{{ $red }};">5. Keputusan diperlukan daripada CEO</h2>
            @include('emails.partials.approvals', ['items' => $snapshot['pending_approvals']])

            <h2 style="font-size:16px;margin:20px 0 8px;color:{{ $red }};">6. Risiko &amp; halangan</h2>
            @include('emails.partials.task-list', ['items' => $snapshot['overdue'], 'empty' => 'Tiada tugasan lewat.'])
            @if ($c['failed_actions'] > 0)
                <p style="margin:8px 0 0;color:{{ $red }};">{{ $c['failed_actions'] }} tindakan gagal dilaksanakan — semak Decision Room.</p>
            @endif

            <h2 style="font-size:16px;margin:20px 0 8px;color:{{ $red }};">7. Cadangan keutamaan esok</h2>
            @include('emails.partials.priorities', ['items' => $snapshot['top_priorities']])
        @else
            <h2 style="font-size:16px;margin:0 0 8px;color:{{ $red }};">2. Tiga keutamaan utama</h2>
            @include('emails.partials.priorities', ['items' => $snapshot['top_priorities']])

            <h2 style="font-size:16px;margin:20px 0 8px;color:{{ $red }};">3. Follow-up pelanggan tertunggak</h2>
            @include('emails.partials.task-list', ['items' => $snapshot['customer_follow_ups'], 'empty' => 'Tiada follow-up pelanggan terbuka.'])

            <h2 style="font-size:16px;margin:20px 0 8px;color:{{ $red }};">4. Menunggu kelulusan CEO</h2>
            @include('emails.partials.approvals', ['items' => $snapshot['pending_approvals']])

            <h2 style="font-size:16px;margin:20px 0 8px;color:{{ $red }};">5. Tugasan lewat</h2>
            @include('emails.partials.task-list', ['items' => $snapshot['overdue'], 'empty' => 'Tiada tugasan lewat.'])

            <h2 style="font-size:16px;margin:20px 0 8px;color:{{ $red }};">6. Cashflow</h2>
            @if ($snapshot['cashflow']['verified'])
                <p style="margin:0;">Potensi jualan dalam follow-up terbuka: <strong>RM{{ number_format($snapshot['cashflow']['revenue_at_stake_in_open_follow_ups'], 2) }}</strong><br><span style="font-size:12px;color:#5b5b66;">{{ $snapshot['cashflow']['note'] }}</span></p>
            @else
                <p style="margin:0;color:#5b5b66;">{{ $snapshot['cashflow']['note'] }}</p>
            @endif

            <h2 style="font-size:16px;margin:20px 0 8px;color:{{ $red }};">7. Tindakan disyorkan hari ini</h2>
        @endif

        @if ($moment !== 'evening')
            @forelse ($recommendations as $rec)
                <div style="border-left:3px solid {{ $red }};background:#fafafb;padding:10px 12px;margin-bottom:10px;">
                    <div style="font-weight:bold;margin-bottom:4px;">{{ $rec['title'] }}</div>
                    <div style="font-size:13px;line-height:1.5;">
                        <strong>Situasi:</strong> {{ $rec['situation'] }}<br>
                        <strong>Analisis:</strong> {{ $rec['analysis'] }}<br>
                        <strong>Tindakan:</strong> {{ $rec['action'] }}<br>
                        <strong>Impak dijangka:</strong> {{ $rec['impact'] }}
                    </div>
                </div>
            @empty
                <p style="margin:0;color:#5b5b66;">Tiada cadangan khusus hari ini.</p>
            @endforelse
        @endif

        <p style="margin:24px 0 0;text-align:center;">
            <a href="{{ $dashboardUrl }}" style="display:inline-block;background:{{ $red }};color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:bold;">Buka Executive Dashboard</a>
        </p>
    </td></tr>
    <tr><td style="background:#0d0d10;color:#8d8d97;font-size:11px;padding:14px 24px;text-align:center;">
        Think Like a CEO. Act Like a Vice CEO. · You Lead. We Execute.<br>
        Data daripada pangkalan data tugasan Dyno Focus pada {{ $snapshot['generated_at'] }}. AI tidak menghantar apa-apa kepada pelanggan tanpa kelulusan anda.
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
