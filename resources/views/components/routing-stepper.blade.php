@props(['document', 'chain' => null, 'compact' => false])

@php
    $routingChain = $chain ?? $document->getRoutingChain();
    $currentId = (int) $document->current_department_id;
@endphp

@if($routingChain->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'overflow-x-auto pb-1']) }}>
        <div class="flex items-center gap-1 min-w-max">
            @foreach($routingChain as $i => $step)
                @php
                    $isCurrent = (int) $step->id === $currentId && $document->status !== 'completed';
                    $isDone = false;
                    if ($document->status === 'completed') {
                        $isDone = true;
                    } else {
                        foreach ($routingChain as $j => $s) {
                            if ((int) $s->id === $currentId && $j > $i) {
                                $isDone = true;
                                break;
                            }
                        }
                    }
                    $nodeSize = $compact ? 'h-6 w-6 text-xs' : 'h-7 w-7 text-xs';
                    $labelClass = $compact ? 'max-w-[72px] text-[10px]' : 'max-w-[88px] text-xs';
                @endphp

                <div class="flex flex-col items-center gap-1">
                    <div class="flex {{ $nodeSize }} items-center justify-center rounded-full font-bold
                        {{ $isCurrent ? 'bg-emerald-600 text-white ring-2 ring-emerald-300' : ($isDone ? 'bg-emerald-200 text-emerald-700' : 'bg-gray-100 text-gray-400') }}">
                        @if($isDone)
                            <svg class="{{ $compact ? 'h-3.5 w-3.5' : 'h-4 w-4' }}" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                            </svg>
                        @else
                            {{ $i + 1 }}
                        @endif
                    </div>
                    <span class="{{ $labelClass }} truncate text-center font-medium leading-tight
                        {{ $isCurrent ? 'text-emerald-700 font-bold' : ($isDone ? 'text-emerald-500' : 'text-gray-400') }}">
                        {{ $step->name }}
                    </span>
                </div>

                @if(!$loop->last)
                    <div class="mb-4 h-0.5 {{ $compact ? 'w-6' : 'w-8' }} shrink-0 {{ $isDone ? 'bg-emerald-300' : 'bg-gray-200' }}"></div>
                @endif
            @endforeach
        </div>
    </div>
@endif
