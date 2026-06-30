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
    $held    = $stage === DocumentStatus::OnHold;

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

@if($held)
    {{-- Off-line side state: blocked/waiting. Amber treatment, SLA paused. --}}
    <div class="step-hold" role="status" style="margin:8px 2px 4px;">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6M14 9v6M5 5h14v14H5z"/>
        </svg>
        <div>
            <span style="font-weight:700;">On Hold</span>
            @if($document->blocked_by)<span style="opacity:.85;"> · waiting on {{ $document->blocked_by }}</span>@endif
            @if($document->hold_until)<span style="opacity:.85;"> · until {{ $document->hold_until->format('M d, Y') }}</span>@endif
            @if($document->hold_reason)<div style="font-weight:400;margin-top:2px;">{{ $document->hold_reason }}</div>@endif
        </div>
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

    @if($held)
        {{-- Blocked: the only forward action is to lift the hold and resume. --}}
        <div class="step-controls" style="display:flex;flex-wrap:wrap;gap:8px;margin:10px 2px 2px;">
            <form method="POST" action="{{ route('documents.status.unhold', $document) }}">
                @csrf @method('PATCH')
                <button type="submit" class="btn-step btn-step-fwd">Resume / Un-hold →</button>
            </form>
        </div>
    @else
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

            {{-- Park a blocked ticket (waiting on citizen / external / internal). --}}
            @unless($stage->isTerminal())
                <div x-data="{ holdOpen: false }" style="display:inline-block;">
                    <button type="button" class="btn-step btn-step-hold" @click="holdOpen = !holdOpen">⏸ On hold</button>
                    <div x-show="holdOpen" x-cloak class="step-hold-form" style="margin-top:8px;">
                        <form method="POST" action="{{ route('documents.status.hold', $document) }}" style="display:flex;flex-direction:column;gap:8px;">
                            @csrf @method('PATCH')
                            <label style="font-size:11px;color:var(--ink-soft);">Reason (required)
                                <textarea name="hold_reason" rows="2" required maxlength="1000"
                                          placeholder="e.g. Waiting for the citizen to submit a clearance"></textarea>
                            </label>
                            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                                <label style="font-size:11px;color:var(--ink-soft);">Who's blocking
                                    <select name="blocked_by" required>
                                        <option value="citizen">Citizen</option>
                                        <option value="external">External office</option>
                                        <option value="internal">Internal</option>
                                    </select>
                                </label>
                                <label style="font-size:11px;color:var(--ink-soft);">Follow-up date (optional)
                                    <input type="date" name="hold_until" min="{{ now()->toDateString() }}">
                                </label>
                            </div>
                            <div>
                                <button type="submit" class="btn-step btn-step-hold">Put on hold</button>
                                <button type="button" class="btn-step btn-step-back" @click="holdOpen = false">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endunless
        </div>
    @endif
@endif
