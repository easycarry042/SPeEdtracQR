@props([
    'label' => '',
    'value' => 0,
    'icon' => 'list',     // kept for backward-compat; tiles are icon-free
    'accent' => 'green',  // baseline rule role: green | amber | red | bright | brass
    'sub' => null,        // optional sub-line under the value
])

@php
    $bars = [
        'green' => '',
        'bright' => 'bright',
        'amber' => 'amber',
        'red' => 'red',
        'brass' => 'brass',
    ];
    $bar = $bars[$accent] ?? '';
@endphp

<div {{ $attributes->merge(['class' => 'tile']) }}>
    <div class="k">{{ $label }}</div>
    <div class="v mono">{{ $value }}</div>
    @if($sub)<div class="sub">{{ $sub }}</div>@endif
    <div class="bar {{ $bar }}"></div>
</div>
