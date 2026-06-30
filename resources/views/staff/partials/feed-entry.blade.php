@php
    $isManual = $entry['kind'] === 'manual';
    $typeLabel = ucfirst($entry['type']);
@endphp
<div @class(['sp-card', 'manual' => $isManual, 'auto' => ! $isManual])>
    <div class="sp-card-head">
        <div style="display:flex;align-items:center;gap:8px;">
            @if($isManual)
                <span class="sp-avatar sp-avatar-sm">{{ collect(explode(' ', $entry['author']))->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') }}</span>
            @else
                <span class="sp-sysico" title="System">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 18v4M4.9 4.9l2.8 2.8M16.3 16.3l2.8 2.8M2 12h4M18 12h4M4.9 19.1l2.8-2.8M16.3 7.7l2.8-2.8"/></svg>
                </span>
            @endif
            <span style="font-size:13px;font-weight:700;color:var(--green-deep);">{{ $entry['author'] }}</span>
            <span class="sp-typetag {{ $isManual ? 'm-'.$entry['type'] : 'm-system' }}">{{ $isManual ? $typeLabel : 'System' }}</span>
        </div>
        <span style="font-size:12px;color:var(--ink-soft);">{{ optional($entry['at'])->diffForHumans() }}</span>
    </div>

    <p class="sp-card-body">{{ $entry['body'] }}</p>

    @if($entry['document'])
        <a href="{{ $entry['document']['url'] }}" class="sp-docchip">
            <span class="code">{{ $entry['document']['tracking_number'] }}</span>
            <span style="color:var(--ink-soft);font-size:12px;">{{ $entry['document']['document_type'] }}</span>
            <x-status-badge :status="$entry['document']['status']" />
        </a>
    @endif
</div>
