@if (empty($items))
    <p style="margin:0;color:#5b5b66;">{{ $empty }}</p>
@else
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;">
        @foreach ($items as $t)
            <tr>
                <td style="padding:6px 0;border-bottom:1px solid #eeeef1;">
                    <strong>{{ $t['title'] }}</strong> <span style="color:#8d8d97;">{{ $t['ref'] }}</span>
                    @if ($t['customer'])<br><span style="color:#5b5b66;">Pelanggan: {{ $t['customer'] }}</span>@endif
                </td>
                <td style="padding:6px 0;border-bottom:1px solid #eeeef1;text-align:right;white-space:nowrap;color:{{ $t['overdue'] ? '#d7141a' : '#5b5b66' }};">
                    {{ $t['status'] }}@if ($t['deadline'])<br>{{ $t['deadline'] }}@endif
                </td>
            </tr>
        @endforeach
    </table>
@endif
