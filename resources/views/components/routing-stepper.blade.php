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
    $canAct = $controls && $document->canBeAdvancedBy(auth()->user());
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
        <div x-data="{ gate: null, reason: '', claimDate: @js($document->claim_date?->toDateString() ?? '') }"
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
                        @click="gate = gate === 'advance' ? null : 'advance'">Advance →</button>
            @endif

            {{-- Return for revision / On hold live in the review modal on the
                 Requests hub; the stepper keeps only the line's own moves. --}}

            @if($returned)
                <form method="POST" action="{{ route('documents.status.set', $document) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="expected_status" value="{{ $document->status }}">
                    <input type="hidden" name="status" value="{{ DocumentStatus::InProgress->value }}">
                    <button type="submit" class="btn-step btn-step-fwd">Resume → In Progress</button>
                </form>
            @endif

        </div>

        {{-- ─── Stage-gate confirm panels ─────────────────────────────── --}}

        @if($hasNext)
            {{-- Advancing is a dialog now, and what it asks for is the claiming
                 day: the date the citizen comes to collect. It replaces the old
                 review note — the citizen sees this on their tracking page. --}}
            <div x-show="gate === 'advance'" x-cloak
                 class="gate-modal"
                 role="dialog" aria-modal="true" aria-label="Advance this request">
                <div class="gate-modal-card" @click.outside="gate = null">
                    <p class="gate-head">{{ $stage->label() }} <span aria-hidden="true">→</span> <b>{{ $next->label() }}</b></p>

                    @if($gateChecks !== [])
                        <ul class="gate-checks">
                            @foreach($gateChecks as $check)
                                @if(! $check['passed'] && $check['key'] === 'work_evidence')
                                    {{-- Live row: setting the claiming date records the step. --}}
                                    <li :class="claimDate ? 'ok' : 'no'" class="no">
                                        <svg x-show="claimDate" x-cloak width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5 9-10"/></svg>
                                        <svg x-show="! claimDate" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                                        <span>{{ $check['label'] }} — setting a claiming date below records this step</span>
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

                    <form method="POST" action="{{ route('documents.status.advance', $document) }}" style="display:flex;flex-direction:column;gap:10px;">
                        @csrf @method('PATCH')
                        <input type="hidden" name="expected_status" value="{{ $document->status }}">

                        <label class="gate-claim">
                            <span class="gate-claim-label">Claiming date (required)</span>
                            <input type="date" name="claim_date" x-model="claimDate" required
                                   min="{{ now()->toDateString() }}"
                                   value="{{ $document->claim_date?->toDateString() }}">
                            <span class="gate-claim-hint">
                                When the citizen can collect this. Shown on their tracking page —
                                pick the day the work will realistically be done.
                            </span>
                        </label>

                        {{-- Quick picks for the common promises. --}}
                        <div class="gate-claim-quick">
                            @foreach([['+3 days', 3], ['+1 week', 7], ['+2 weeks', 14]] as [$label, $days])
                                <button type="button" class="btn-step"
                                        @click="claimDate = @js(now()->addDays($days)->toDateString())">{{ $label }}</button>
                            @endforeach
                        </div>

                        @if($willEmailCitizen)
                            <p class="gate-mail">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18v12H3z"/><path stroke-linecap="round" stroke-linejoin="round" d="m3 7 9 6 9-6"/></svg>
                                The citizen will be emailed about this change.
                            </p>
                        @endif
                        @unless($gatePassed || $noteCanSatisfy)
                            <p class="gate-blocked">Resolve the unmet requirements above to continue.</p>
                        @endunless
                        <div class="gate-actions">
                            <button type="submit" class="btn-step btn-step-fwd"
                                    :disabled="! claimDate @unless($gatePassed || $noteCanSatisfy) || true @endunless">
                                Confirm — move to {{ $next->label() }}
                            </button>
                            <button type="button" class="btn-step btn-step-back" @click="gate = null">Cancel</button>
                        </div>
                    </form>
                </div>
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

        </div>
    @endif
@endif
