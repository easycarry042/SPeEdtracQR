@props([
    'stages' => [],   // ordered array of department labels for this document's route
    'current' => 1,   // 1-based index of the active desk (count+1 when fully released)
])

{{--
    Civic Record horizontal routing stepper. Shared by the dashboard routing
    slip (<x-routing-slip>) and the Movements list rows so both render an
    identical "physical routing slip" stamp row. Styling: .mvsteps / .mvstep /
    .node.done|.now|.todo / .reached in resources/css/app.css.
--}}
<div class="mvsteps">
    @foreach ($stages as $i => $label)
        @php
            $pos = $i + 1;
            $state = $pos < $current ? 'done' : ($pos === $current ? 'now' : 'todo');
            $reached = $pos <= $current;
        @endphp
        <div @class(['mvstep', 'reached' => $reached])>
            <div class="node {{ $state }}">
                @if ($state === 'done')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg>
                @else
                    {{ $pos }}
                @endif
            </div>
            <div @class(['cap', 'cur' => $state === 'now'])>{{ $label }}</div>
        </div>
    @endforeach
</div>
