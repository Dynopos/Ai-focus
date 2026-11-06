@if (empty($items))
    <p style="margin:0;color:#5b5b66;">Tiada keputusan menunggu.</p>
@else
    @foreach ($items as $a)
        <div style="padding:6px 0;border-bottom:1px solid #eeeef1;font-size:13px;">
            <strong>{{ $a['code'] }}</strong> — {{ $a['title'] }} <span style="color:#5b5b66;">({{ $a['action'] }}, tamat {{ $a['expires'] }})</span>
        </div>
    @endforeach
@endif
