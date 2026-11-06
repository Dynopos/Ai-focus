@if (empty($items))
    <p style="margin:0;color:#5b5b66;">Tiada tugasan terbuka.</p>
@else
    @foreach ($items as $i => $p)
        <div style="padding:8px 0;border-bottom:1px solid #eeeef1;">
            <span style="display:inline-block;width:22px;height:22px;line-height:22px;border-radius:11px;background:#d7141a;color:#fff;text-align:center;font-size:12px;font-weight:bold;">{{ $i + 1 }}</span>
            <strong>{{ $p['title'] }}</strong> <span style="color:#8d8d97;">{{ $p['ref'] }}</span>
            @if ($p['reasons'])<div style="font-size:12px;color:#5b5b66;margin-left:28px;">{{ implode(' · ', $p['reasons']) }}</div>@endif
        </div>
    @endforeach
@endif
