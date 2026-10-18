@props([
    'document',
    'size' => 'md',        // 'md' for cards and pages · 'sm' for table rows
    'surface' => 'staff',  // 'staff' | 'citizen' — which palette re-skins the rail
    'sideState' => true,   // name the off-the-line states under the bar
])

@php
    use App\Enums\DocumentStatus;

    // The four-stage rail (.dm-steps), as the Review modal on the dashboards
    // draws it. That modal computes its own states in Alpine because its rows
    // arrive as a JSON payload; every server-rendered surface comes through
    // this component instead, so none of them can drift into a
    // different-looking bar or a different stage count.
    $rail = DocumentStatus::railStages();
    $stage = $document->statusEnum();

    // railStage() places the side states too: Returned reads as back in review,
    // a hold or a denial sits where the request stopped.
    $current = $stage->railStage(
        DocumentStatus::tryFrom((string) $document->status_before_hold)
    );

    $isCompleted = $stage === DocumentStatus::Completed;

    // Off-the-line states, named under the bar rather than drawn on it.
    $side = match ($stage) {
        DocumentStatus::Returned => 'Returned for revision',
        DocumentStatus::OnHold => 'On hold',
        DocumentStatus::Denied => 'Denied',
        default => null,
    };
@endphp

<ol {{ $attributes->class([
        'dm-steps',
        'dm-steps-sm' => $size === 'sm',
        'dm-steps-cz' => $surface === 'citizen',
    ]) }} aria-label="Progress">
    @foreach($rail as $i => $s)
        @php
            $position = $i + 1;

            // A completed request has nothing left in progress, so its final
            // stage reads done rather than current — same rule as Look Up's
            // stageState().
            $state = ($position < $current || ($position === $current && $isCompleted))
                ? 'done'
                : ($position === $current ? 'current' : 'todo');
        @endphp

        <li class="dm-step is-{{ $state }}" @if($state === 'current') aria-current="step" @endif>
            {{-- The segment is drawn leading into each dot, so the first stage
                 has none. --}}
            @if($i > 0)
                <span class="dm-step-line" aria-hidden="true"></span>
            @endif

            <span class="dm-step-dot" aria-hidden="true">
                @if($state === 'done')
                    <svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m6 12.5 4 4 8-9"/>
                    </svg>
                @endif
            </span>

            <span class="dm-step-label">{{ $s['label'] }}</span>
        </li>
    @endforeach
</ol>

@if($sideState && $side)
    <p class="dm-side-state">{{ $side }}</p>
@endif
