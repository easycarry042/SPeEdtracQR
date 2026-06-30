@props([
    'document',
    'chain' => null,     // deprecated (routing-era); ignored
    'compact' => false,
    'controls' => false, // render inline Advance / Move back / Return controls
])

@php
    use App\Enums\DocumentStatus;

    $flow    = DocumentStatus::flow();
    $stage   = $document->statusEnum();
    $current = $stage->position();          // 1-based; 0 when Returned (off the line)
    $returned = $stage === DocumentStatus::Returned;

    // Who may operate the controls: the assigned staff member, or an admin.
    $user   = auth()->user();
    $isAdmin = $user?->can('manage system') || $user?->can('assign documents');
    $canAct = $controls && $user && ($isAdmin || (
        $user->can('advance documents')
        && $document->assigned_to !== null
        && (int) $document->assigned_to === (int) $user->id
    ));
@endphp

@if($returned)
    {{-- Off-line side state: clear warning treatment. --}}
    <div class="step-returned" role="status" style="margin:8px 2px 4px;">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v6h6M3 13a9 9 0 1 0 3-7.7L3 8"/>
        </svg>
        <span>{{ $stage->label() }}</span>
    </div>
@endif

{{-- Status progress: stamped stage markers along the document's lifecycle.
     done = filled deep-green w/ brass check · now = active · todo = hollow. --}}
<div {{ $attributes->merge(['class' => 'steps']) }} style="margin:8px 2px 4px;">
    @foreach($flow as $i => $s)
        @php
            $pos = $i + 1;
            $isDone = ! $returned && $pos < $current;
            $isCurrent = ! $returned && $pos === $current;
            $node = $isDone ? 'done' : ($isCurrent ? 'now' : 'todo');
        @endphp

        <div class="stp">
            <div class="node {{ $node }}">
                @if($isDone)
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5 9-10"/>
                    </svg>
                @else
                    {{ $pos }}
                @endif
            </div>
            <div class="cap {{ $isCurrent ? 'cur' : '' }}">{{ $s->label() }}</div>
        </div>

        @if(!$loop->last)
            <div class="seg {{ $isDone ? 'done' : '' }}"></div>
        @endif
    @endforeach
</div>

@if($canAct)
    @php
        $hasNext = $stage->next() !== null;
        $hasPrev = $stage->previous() !== null;
    @endphp
    <div class="step-controls" style="display:flex;flex-wrap:wrap;gap:8px;margin:10px 2px 2px;">
        @if($hasPrev)
            <form method="POST" action="{{ route('documents.status.revert', $document) }}">
                @csrf @method('PATCH')
                <button type="submit" class="btn-step btn-step-back">← Move back</button>
            </form>
        @endif

        @if($hasNext)
            <form method="POST" action="{{ route('documents.status.advance', $document) }}">
                @csrf @method('PATCH')
                <button type="submit" class="btn-step btn-step-fwd">Advance →</button>
            </form>
        @endif

        @if(! $returned && ! $stage->isTerminal())
            <form method="POST" action="{{ route('documents.status.set', $document) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="{{ DocumentStatus::Returned->value }}">
                <button type="submit" class="btn-step btn-step-return">Return for revision</button>
            </form>
        @endif

        @if($returned)
            <form method="POST" action="{{ route('documents.status.set', $document) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="{{ DocumentStatus::InProgress->value }}">
                <button type="submit" class="btn-step btn-step-fwd">Resume → In Progress</button>
            </form>
        @endif
    </div>
@endif
