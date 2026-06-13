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
            'high'   => ['label' => 'High confidence',   'class' => 'bg-emerald-100 text-emerald-700'],
            'medium' => ['label' => 'Medium confidence', 'class' => 'bg-amber-100 text-amber-700'],
            'low'    => ['label' => 'Early estimate',    'class' => 'bg-orange-100 text-orange-700'],
            default  => ['label' => 'Rough estimate',    'class' => 'bg-gray-100 text-gray-600'],
        };
        $basis = ($p['based_on'] ?? 0) > 0
            ? 'Learned from '.$p['based_on'].' similar past document'.($p['based_on'] === 1 ? '' : 's').'.'
            : 'Estimated from each department’s service targets.';
    @endphp
    <div class="overflow-hidden rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white shadow-sm">
        <div class="flex items-start gap-4 p-6">
            <div class="shrink-0 rounded-xl bg-indigo-600/10 p-3">
                <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L23 12l-6.714 2.143L14 21l-2.286-6.857L5 12l6.714-2.143L14 3z"/>
                </svg>
            </div>
            <div class="flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-indigo-500">Predicted completion</p>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $confMeta['class'] }}">{{ $confMeta['label'] }}</span>
                </div>
                <p class="mt-1 text-2xl font-extrabold text-gray-900">
                    Likely ready by {{ $eta->format('M d, Y') }}
                </p>
                <p class="mt-1 text-sm text-gray-500">
                    about {{ $eta->diffForHumans(null, true) }} from now · {{ $basis }}
                </p>
            </div>
        </div>
    </div>
@endif
