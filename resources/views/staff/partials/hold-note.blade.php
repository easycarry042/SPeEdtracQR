@if($doc->status === 'on_hold')
    <span class="gs-hold">
        <span>On hold</span>
        @if($doc->blocked_by)
            <span aria-hidden="true">·</span><span>waiting on {{ $doc->blocked_by }}</span>
        @endif
        @if($doc->hold_until)
            <span aria-hidden="true">·</span>
            <time class="gs-num" datetime="{{ $doc->hold_until->toIso8601String() }}">until {{ $doc->hold_until->format('M j, Y') }}</time>
        @endif
        {{-- A hold pauses the SLA clock, so this marker is the only on-screen
             sign that a parked request has overrun its own deadline. --}}
        @if($doc->isHoldOverdue())
            <span aria-hidden="true">·</span>
            <strong class="gs-num" style="font-weight:600;">{{ $doc->holdOverdueDays() }} days past due</strong>
        @endif
        @if(! empty($doc->hold_reason))
            <span class="gs-hold-reason">{{ \Illuminate\Support\Str::limit($doc->hold_reason, 100) }}</span>
        @endif
    </span>
@endif
