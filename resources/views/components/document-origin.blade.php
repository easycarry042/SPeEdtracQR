@props([
    'source' => null,   // 'walk_in' | 'online'
    'by' => null,       // creator name (null for online / system)
    'at' => null,       // created_at
])

@php
    $online = $source === 'online';
    $label = $online ? 'Online' : 'Walk-in';
    $pill = $online ? 'p-amber' : 'p-muted';
@endphp

<span {{ $attributes->merge(['class' => 'origin-line']) }}>
    <span class="pill {{ $pill }}">{{ $label }}</span>
    <span class="origin-who">{{ $online ? 'Submitted online by citizen' : 'by '.($by ?: '—') }}</span>
    @if($at)
        <span class="origin-date">· {{ \Illuminate\Support\Carbon::parse($at)->format('M d, Y') }}</span>
    @endif
</span>
