@props(['document', 'chain' => null, 'compact' => false])

@php
    $routingChain = $chain ?? $document->getRoutingChain();
    $currentId = (int) $document->current_department_id;
@endphp

@if($routingChain->isNotEmpty())
    {{-- Routing slip: stamped step markers along the document's journey.
         done = filled deep-green w/ brass check · now = white w/ brass ring · todo = hollow. --}}
    <div {{ $attributes->merge(['class' => 'steps']) }} style="margin:8px 2px 4px;">
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
                $node = $isDone ? 'done' : ($isCurrent ? 'now' : 'todo');
            @endphp

            <div class="stp">
                <div class="node {{ $node }}">
                    @if($isDone)
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5 9-10"/>
                        </svg>
                    @else
                        {{ $i + 1 }}
                    @endif
                </div>
                <div class="cap {{ $isCurrent ? 'cur' : '' }}">{{ $step->name }}</div>
            </div>

            @if(!$loop->last)
                <div class="seg {{ $isDone ? 'done' : '' }}"></div>
            @endif
        @endforeach
    </div>
@endif
