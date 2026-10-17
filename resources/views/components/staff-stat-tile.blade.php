@props([
    'label' => '',
    'value' => 0,
    'tone' => 'mid',   // deep | mid | bright | brightest | amber
    'icon' => 'clock', // doc | hourglass | clock | check | none
    'note' => null,    // optional line under the number (the admin row's deltas)
])

@php
    // Left-to-right gradients, as the frame draws them: the darkest tile carries
    // the oldest work, the lightest the finished work. The supervisor's row runs
    // to four tiles, so it uses the whole ramp; the staff row uses the first three.
    $tones = [
        'deep' => 'from-[#0a3a1e] to-[#2f7a3a]',
        'mid' => 'from-[#0f7233] to-[#2a9d4f]',
        'bright' => 'from-[#20a04a] to-[#3fc267]',
        'brightest' => 'from-[#0ab329] to-[#459955]',
        // Risk is the one count that must not read as more green: amber is what
        // the rest of the system uses for "watch this".
        'amber' => 'from-[#8a5a08] to-[#c08a1e]',
    ];
    $gradient = $tones[$tone] ?? $tones['mid'];

    // Hand-drawn line illustrations exported from the design, not stroke icons —
    // the loose freehand line is what keeps these tiles from reading as generic
    // dashboard chrome.
    $arts = [
        // The command centre's leading tile — the frame's stacked-paper drawing
        // for a plain total, which had to show as icon="none" until it existed.
        'doc' => ['file' => 'stat-total.svg', 'class' => 'h-[63px] w-[64px]'],
        'hourglass' => ['file' => 'stat-pending.svg', 'class' => 'h-[66.5px] w-[56px]'],
        'clock' => ['file' => 'stat-in-progress.svg', 'class' => 'h-[64px] w-[64px]'],
        'check' => ['file' => 'stat-completed.svg', 'class' => 'h-[64px] w-[64px]'],
    ];
    // icon="none" for a count the design has no drawing for — better an honest
    // blank than one of the three reused on two tiles in the same row.
    $art = $icon === 'none' ? null : ($arts[$icon] ?? $arts['clock']);
@endphp

<div {{ $attributes->merge(['class' => 'relative isolate flex min-h-[112px] items-center overflow-hidden rounded-[13px] bg-gradient-to-r '.$gradient.' px-[21.5px] py-[21.5px] text-white shadow-md xl:min-h-[139px]']) }}>
    <div class="min-w-0 flex-1">
        <p class="text-[16px] font-extrabold uppercase tracking-wide xl:text-[19px]">{{ $label }}</p>
        <p class="mt-[6px] text-[42px] font-extrabold leading-none xl:text-[58px]">{{ $value }}</p>

        @if($note)
            {{-- The command centre's period-over-period change. White at 80% on
                 every tone in the ramp still clears AA. --}}
            <p class="mt-[7px] text-[12.5px] font-semibold text-white/80">{{ $note }}</p>
        @endif
    </div>

    {{-- Decorative only: the label and number already say everything. --}}
    @if($art)
        <img src="{{ asset('images/staff/'.$art['file']) }}" alt="" aria-hidden="true"
             class="pointer-events-none ml-4 shrink-0 opacity-70 {{ $art['class'] }}">
    @endif
</div>
