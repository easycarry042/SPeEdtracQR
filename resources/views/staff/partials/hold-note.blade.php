@if($doc->status === 'on_hold')
    <div class="hold-note">
        ⏸ On hold
        @if($doc->blocked_by)
            · waiting on {{ $doc->blocked_by }}
        @endif
        @if($doc->hold_until)
            · until {{ $doc->hold_until->format('M d, Y') }}
        @endif
        {{-- A hold pauses the SLA clock, so this marker is the only on-screen
             sign that a parked request has overrun its own deadline. --}}
        @if($doc->isHoldOverdue())
            <span class="hold-stalled">· {{ $doc->holdOverdueDays() }}d past due</span>
        @endif
        @if(! empty($doc->hold_reason))
            <span style="display:block;margin-top:2px;">{{ \Illuminate\Support\Str::limit($doc->hold_reason, 100) }}</span>
        @endif
    </div>
@endif
