{{-- Reusable Civic Record donut. $data = ['total'=>int, 'slices'=>[['label','value','color','pct','offset']]] --}}
<div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
    <svg viewBox="0 0 140 140" width="120" height="120" style="flex:none" role="img" aria-label="Distribution">
        <g transform="rotate(-90 70 70)" fill="none" stroke-width="20">
            @if ($data['total'] > 0)
                @foreach ($data['slices'] as $s)
                    <circle cx="70" cy="70" r="52" pathLength="100"
                            stroke="{{ $s['color'] }}"
                            stroke-dasharray="{{ $s['pct'] }} {{ 100 - $s['pct'] }}"
                            stroke-dashoffset="{{ $s['offset'] }}"/>
                @endforeach
            @else
                <circle cx="70" cy="70" r="52" stroke="#eef2ef"/>
            @endif
        </g>
        <text x="70" y="68" text-anchor="middle" class="mono" font-size="22" font-weight="600" fill="#16211b">{{ $data['total'] }}</text>
        <text x="70" y="86" text-anchor="middle" font-size="10" fill="#5b6b62">{{ $caption ?? '' }}</text>
    </svg>
    <div style="flex:1;min-width:140px;">
        @foreach ($data['slices'] as $s)
            <div class="legrow">
                <span class="sw" style="background:{{ $s['color'] }}"></span>
                <span class="ln">{{ $s['label'] }}</span>
                <span class="lv">{{ $s['value'] }}</span>
                <span class="lp">{{ round($s['pct']) }}%</span>
            </div>
        @endforeach
    </div>
</div>
