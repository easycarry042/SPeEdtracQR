@props(['prediction' => null, 'document'])

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
    <div class="overflow-hidden rounded-xl border border-hairline bg-paper">
        <div class="flex items-start gap-4 p-6">
            <div class="shrink-0 rounded-lg bg-green-wash p-3 text-green-deep">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L23 12l-6.714 2.143L14 21l-2.286-6.857L5 12l6.714-2.143L14 3z"/>
                </svg>
            </div>
            <div class="flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-xs font-semibold tracking-wide text-ink-soft">Predicted completion</p>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $confMeta['class'] }}">{{ $confMeta['label'] }}</span>
                </div>
                <p class="mt-1 text-2xl font-semibold text-ink">
                    Likely ready by <span class="stamp">{{ $eta->format('M d, Y') }}</span>
                </p>
                <p class="mt-1 text-sm text-ink-soft">
                    about {{ $eta->diffForHumans(null, true) }} from now · {{ $basis }}
                </p>
            </div>
        </div>
    </div>
@endif
