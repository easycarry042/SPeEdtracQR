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
        $next = $stage->next();
        $hasNext = $next !== null && $next !== DocumentStatus::Completed;
        $hasPrev = $stage->previous() !== null;

        // Stage-gate data for the confirm panel: server-computed checklist, so
        // the UI and DocumentStatusController can never disagree. (Fully
        // qualified — a `use` inside this conditional @php block is a fatal.)
        $gateChecks = $hasNext ? \App\Support\StatusGate::checks($document, $next) : [];
        $gatePassed = collect($gateChecks)->every(fn ($c) => $c['passed']);
        $noteRequired = $hasNext && \App\Support\StatusGate::noteRequired($next);
        // When the ONLY unmet requirement is work evidence, a typed note
        // satisfies it (the server stores it as a staff work note).
        $failingKeys = collect($gateChecks)->reject(fn ($c) => $c['passed'])->pluck('key')->all();
        $noteCanSatisfy = $failingKeys === ['work_evidence'];
        $willEmailCitizen = $hasNext
            && config('tracking.notify_citizen.enabled', true)
            && ($document->notify_citizen ?? true)
            && $document->citizen_email
            && config('tracking.notify_citizen.stages.'.$next->value, false);
    @endphp

    {{-- Server-side gate failures (unmet requirements, stale page, missing note). --}}
    @if($errors->hasAny(['status', 'note', 'reason', 'expected_status']))
        <div class="gate-errors" role="alert">
            @foreach($errors->getMessages() as $field => $messages)
                @if(in_array($field, ['status', 'note', 'reason', 'expected_status'], true))
                    @foreach($messages as $message)
                        <p>{{ $message }}</p>
                    @endforeach
                @endif
            @endforeach
        </div>
    @endif

    @if($held)
        {{-- Blocked: the only forward action is to lift the hold and resume. --}}
        <div class="step-controls" style="display:flex;flex-wrap:wrap;gap:8px;margin:10px 2px 2px;">
            <form method="POST" action="{{ route('documents.status.unhold', $document) }}">
                @csrf @method('PATCH')
                <button type="submit" class="btn-step btn-step-fwd">Resume / Un-hold →</button>
            </form>
        </div>
    @else
        <div x-data="{ gate: null, note: '', reason: '' }"
             @keydown.escape.window="gate = null"
             style="margin:10px 2px 2px;">
        <div class="step-controls" style="display:flex;flex-wrap:wrap;gap:8px;">
            @if($hasPrev)
                <button type="button" class="btn-step btn-step-back"
                        :class="gate === 'back' && 'is-open'"
                        @click="gate = gate === 'back' ? null : 'back'; reason = ''">← Move back</button>
            @endif

            @if($hasNext)
                <button type="button" class="btn-step btn-step-fwd"
                        :class="gate === 'advance' && 'is-open'"
                        @click="gate = gate === 'advance' ? null : 'advance'; note = ''">Advance →</button>
            @endif

            @if(! $returned && ! $stage->isTerminal())
                <button type="button" class="btn-step btn-step-return"
                        :class="gate === 'return' && 'is-open'"
                        @click="gate = gate === 'return' ? null : 'return'; reason = ''">Return for revision</button>
            @endif

            @if($returned)
                <form method="POST" action="{{ route('documents.status.set', $document) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="expected_status" value="{{ $document->status }}">
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

        {{-- ─── Stage-gate confirm panels ─────────────────────────────── --}}

        @if($hasNext)
            <div x-show="gate === 'advance'" x-cloak class="gate-panel">
                <p class="gate-head">{{ $stage->label() }} <span aria-hidden="true">→</span> <b>{{ $next->label() }}</b></p>

                @if($gateChecks !== [])
                    <ul class="gate-checks">
                        @foreach($gateChecks as $check)
                            @if(! $check['passed'] && $check['key'] === 'work_evidence')
                                {{-- Live row: typing a note below satisfies this requirement. --}}
                                <li :class="note.trim() ? 'ok' : 'no'" class="no">
                                    <svg x-show="note.trim()" x-cloak width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5 9-10"/></svg>
                                    <svg x-show="! note.trim()" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                                    <span>{{ $check['label'] }} — or add a note below</span>
                                </li>
                            @else
                                <li class="{{ $check['passed'] ? 'ok' : 'no' }}">
                                    @if($check['passed'])
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5 9-10"/></svg>
                                    @else
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                                    @endif
                                    <span>{{ $check['label'] }}<span class="sr-only">{{ $check['passed'] ? ' — met' : ' — not met' }}</span></span>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                @endif

                <form method="POST" action="{{ route('documents.status.advance', $document) }}" style="display:flex;flex-direction:column;gap:8px;">
                    @csrf @method('PATCH')
                    <input type="hidden" name="expected_status" value="{{ $document->status }}">
                    <label>{{ $noteRequired ? 'Review note (required)' : ($noteCanSatisfy ? 'Work note (required — nothing on file yet)' : 'Note (optional)') }}
                        <textarea name="note" rows="2" maxlength="1000" x-model="note" @if($noteRequired || $noteCanSatisfy) required @endif
                                  placeholder="{{ $noteRequired ? 'What was checked and why this passes review' : ($noteCanSatisfy ? 'e.g. Verified the submitted documents; started processing' : 'Anything worth recording about this step') }}"></textarea>
                    </label>
                    @if($willEmailCitizen)
                        <p class="gate-mail">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18v12H3z"/><path stroke-linecap="round" stroke-linejoin="round" d="m3 7 9 6 9-6"/></svg>
                            The citizen will be emailed about this change.
                        </p>
                    @endif
                    @unless($gatePassed)
                        @if($noteCanSatisfy)
                            <p class="gate-blocked" x-show="! note.trim()">Add a work note above — or attach a file to this document first.</p>
                        @else
                            <p class="gate-blocked">Resolve the unmet requirements above to continue.</p>
                        @endif
                    @endunless
                    <div class="gate-actions">
                        <button type="submit" class="btn-step btn-step-fwd"
                                :disabled="{{ $gatePassed ? 'false' : ($noteCanSatisfy ? '! note.trim()' : 'true') }} || ({{ $noteRequired ? 'true' : 'false' }} && ! note.trim())">
                            Confirm — move to {{ $next->label() }}
                        </button>
                        <button type="button" class="btn-step btn-step-back" @click="gate = null">Cancel</button>
                    </div>
                </form>
            </div>
        @endif

        @if($hasPrev)
            <div x-show="gate === 'back'" x-cloak class="gate-panel">
                <p class="gate-head"><b>{{ $stage->previous()->label() }}</b> <span aria-hidden="true">←</span> {{ $stage->label() }}</p>
                <form method="POST" action="{{ route('documents.status.revert', $document) }}" style="display:flex;flex-direction:column;gap:8px;">
                    @csrf @method('PATCH')
                    <input type="hidden" name="expected_status" value="{{ $document->status }}">
                    <label>Reason (required)
                        <textarea name="reason" rows="2" maxlength="1000" x-model="reason" required
                                  placeholder="Why is this moving back a stage?"></textarea>
                    </label>
                    <div class="gate-actions">
                        <button type="submit" class="btn-step btn-step-fwd" :disabled="! reason.trim()">Confirm — move back</button>
                        <button type="button" class="btn-step btn-step-back" @click="gate = null">Cancel</button>
                    </div>
                </form>
            </div>
        @endif

        @if(! $returned && ! $stage->isTerminal())
            <div x-show="gate === 'return'" x-cloak class="gate-panel">
                <p class="gate-head">{{ $stage->label() }} <span aria-hidden="true">→</span> <b>{{ DocumentStatus::Returned->label() }}</b></p>
                <form method="POST" action="{{ route('documents.status.set', $document) }}" style="display:flex;flex-direction:column;gap:8px;">
                    @csrf @method('PATCH')
                    <input type="hidden" name="expected_status" value="{{ $document->status }}">
                    <input type="hidden" name="status" value="{{ DocumentStatus::Returned->value }}">
                    <label>What must the citizen fix? (required)
                        <textarea name="reason" rows="2" maxlength="1000" x-model="reason" required
                                  placeholder="e.g. The uploaded barangay clearance is expired — a current one is needed"></textarea>
                    </label>
                    <div class="gate-actions">
                        <button type="submit" class="btn-step btn-step-return" :disabled="! reason.trim()">Confirm — return for revision</button>
                        <button type="button" class="btn-step btn-step-back" @click="gate = null">Cancel</button>
                    </div>
                </form>
            </div>
        @endif
        </div>
    @endif
@endif
