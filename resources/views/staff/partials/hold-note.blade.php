@if($doc->status === 'on_hold')
    <div class="hold-note">
        ⏸ On hold
        @if($doc->blocked_by)
            · waiting on {{ $doc->blocked_by }}
        @endif
        @if($doc->hold_until)
            · until {{ $doc->hold_until->format('M d, Y') }}
        @endif
        @if(! empty($doc->hold_reason))
            <span style="display:block;margin-top:2px;">{{ \Illuminate\Support\Str::limit($doc->hold_reason, 100) }}</span>
        @endif
    </div>
@endif
