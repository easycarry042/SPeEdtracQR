@props([
    'label' => '',
    'value' => 0,
    'tone' => 'mid',   // deep | mid | bright — darkest tile first, lightest last
    'icon' => 'clock', // hourglass | clock | check
])

@php
    $tones = [
        'deep' => 'from-[#0a3a1e] to-[#136a33]',
        'mid' => 'from-[#136a33] to-[#2a9d4f]',
        'bright' => 'from-[#2a9d4f] to-[#63c97a]',
    ];
    $gradient = $tones[$tone] ?? $tones['mid'];

    $glyphs = [
        'hourglass' => 'M7 3h10M7 21h10M8 3c0 4 8 5 8 9s-8 5-8 9',
        'clock' => 'M12 7v5l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
        'check' => 'M4 13l5 5L20 6',
    ];
    $glyph = $glyphs[$icon] ?? $glyphs['clock'];
@endphp

<div {{ $attributes->merge(['class' => 'relative isolate overflow-hidden rounded-2xl bg-gradient-to-br '.$gradient.' p-6 text-on-green shadow-md']) }}>
    <p class="text-sm font-bold uppercase tracking-wide">{{ $label }}</p>
    <p class="mt-2 text-4xl font-extrabold leading-none">{{ $value }}</p>

    {{-- Decorative only: the label and number already say everything. --}}
    <svg class="pointer-events-none absolute -bottom-2 right-4 -z-10 h-24 w-24 opacity-40"
         fill="none" stroke="currentColor" stroke-width="1.4" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $glyph }}"/>
    </svg>
</div>
