@props([
    'label' => '',
    'value' => 0,
    'tone' => 'mid',   // deep | mid | bright — darkest tile first, lightest last
    'icon' => 'clock', // hourglass | clock | check
])

@php
    // Left-to-right gradients, as the frame draws them: the darkest tile carries
    // the oldest work, the lightest the finished work.
    $tones = [
        'deep' => 'from-[#0a3a1e] to-[#2f7a3a]',
        'mid' => 'from-[#0f7233] to-[#2a9d4f]',
        'bright' => 'from-[#20a04a] to-[#3fc267]',
    ];
    $gradient = $tones[$tone] ?? $tones['mid'];

    // Hand-drawn line illustrations exported from the design, not stroke icons —
    // the loose freehand line is what keeps these tiles from reading as generic
    // dashboard chrome.
    $arts = [
        'hourglass' => ['file' => 'stat-pending.svg', 'class' => 'h-[83px] w-[70px]'],
        'clock' => ['file' => 'stat-in-progress.svg', 'class' => 'h-[80px] w-[80px]'],
        'check' => ['file' => 'stat-completed.svg', 'class' => 'h-[80px] w-[80px]'],
    ];
    $art = $arts[$icon] ?? $arts['clock'];
@endphp

<div {{ $attributes->merge(['class' => 'relative isolate flex min-h-[140px] items-center overflow-hidden rounded-[16px] bg-gradient-to-r '.$gradient.' px-[27px] py-[27px] text-white shadow-md xl:min-h-[174px]']) }}>
    <div class="min-w-0 flex-1">
        <p class="text-[20px] font-extrabold uppercase tracking-wide xl:text-[24px]">{{ $label }}</p>
        <p class="mt-[6px] text-[52px] font-extrabold leading-none xl:text-[72px]">{{ $value }}</p>
    </div>

    {{-- Decorative only: the label and number already say everything. --}}
    <img src="{{ asset('images/staff/'.$art['file']) }}" alt="" aria-hidden="true"
         class="pointer-events-none ml-4 shrink-0 opacity-70 {{ $art['class'] }}">
</div>
