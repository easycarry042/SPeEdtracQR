{{-- `citizen` dresses the card for the public tracking page: the citizen card
     surface and a display-font date. The staff desk keeps its registry look,
     where the monospace .stamp date matches the rest of the record. --}}
@props(['prediction' => null, 'document', 'citizen' => false])

@php
    $p = $prediction;
    $show = $p && ($p['available'] ?? false)
        && ($document->status ?? null) !== 'completed'
        && ! empty($p['eta']);
@endphp

@if($show)
    @php
        $eta = $p['eta'];
        $confMeta = match($p['confidence']) {
            'high'   => ['label' => 'High confidence',   'class' => 'bg-green-wash text-green'],
            'medium' => ['label' => 'Medium confidence', 'class' => 'bg-status-amber-wash text-status-amber'],
            'low'    => ['label' => 'Early estimate',    'class' => 'bg-status-amber-wash text-status-amber'],
            default  => ['label' => 'Rough estimate',    'class' => 'bg-green-wash text-ink-soft'],
        };
        $basis = ($p['based_on'] ?? 0) > 0
            ? 'Learned from '.$p['based_on'].' similar past document'.($p['based_on'] === 1 ? '' : 's').'.'
            : 'Estimated from each department’s service targets.';
    @endphp
    <div @class(['overflow-hidden', 'cz-card' => $citizen, 'rounded-xl border border-hairline bg-paper' => ! $citizen])>
        <div class="flex items-start gap-4 p-5 sm:p-6">
            <div @class(['shrink-0 p-3', 'rounded-[16px] bg-[#eef8ea] text-[#01721a]' => $citizen, 'rounded-lg bg-green-wash text-green-deep' => ! $citizen])>
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L23 12l-6.714 2.143L14 21l-2.286-6.857L5 12l6.714-2.143L14 3z"/>
                </svg>
            </div>
            <div class="flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p @class(['font-semibold tracking-wide', 'text-[14px] text-[#4b5a50]' => $citizen, 'text-xs text-ink-soft' => ! $citizen])>Predicted completion</p>
                    <span @class(['rounded-full px-2 py-0.5 font-semibold', $confMeta['class'], 'text-[12px]' => $citizen, 'text-[10px]' => ! $citizen])>{{ $confMeta['label'] }}</span>
                </div>
                @if($citizen)
                    <p class="mt-1 font-display text-[26px] font-black leading-tight text-[#004004] sm:text-[30px]">
                        Likely ready by {{ $eta->format('M d, Y') }}
                    </p>
                @else
                    <p class="mt-1 text-2xl font-semibold text-ink">
                        Likely ready by <span class="stamp">{{ $eta->format('M d, Y') }}</span>
                    </p>
                @endif
                <p @class(['mt-1', 'text-[15px] text-[#4b5a50]' => $citizen, 'text-sm text-ink-soft' => ! $citizen])>
                    about {{ $eta->diffForHumans(null, true) }} from now · {{ $basis }}
                </p>
            </div>
        </div>
    </div>
@endif
