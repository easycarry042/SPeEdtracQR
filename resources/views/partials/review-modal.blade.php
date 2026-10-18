{{--
    Shared Review modal. Driven by the surrounding Alpine `reviewPanel()` scope.
    Pass $mode = 'supervisor' (assign + approve) or 'staff' (approve = complete).
--}}
@php $mode = $mode ?? 'supervisor'; @endphp

<div x-show="active" x-cloak
     class="fixed inset-0 z-50 overflow-y-auto bg-black/40 px-4 py-[27px]"
     @keydown.escape.window="close()" @click.self="close()"
     role="dialog" aria-modal="true" aria-label="Review request">

    {{-- Two cards, as the Look Up desk laid them out: the request on the left,
         and a second card that slides in beside it holding the claiming-date
         calendar or the citizen conversation. `side` is which of those is open
         — the card only exists while one of them is.

         The desk was a separate screen that could do these two things but not
         accept an assignment, while this modal could accept but not adjust a
         date or answer the citizen. They are one modal now. --}}
    <div class="mx-auto flex w-full max-w-[1335px] flex-col items-start justify-center gap-[15px] lg:flex-row lg:items-stretch"
         @click.self="close()">

    {{-- Same card as the Look Up detail modal (.dm-*): a fixed size with a
         pinned header and footer, and a body that scrolls between them. --}}
    <div class="dm-card dm-card-review dm-fixed w-full max-w-[660px] lg:min-w-0 lg:flex-1" x-show="active" x-transition.opacity>
        <template x-if="active">
            <div class="flex h-full min-h-0 flex-col">
                {{-- Header bar: where the request stands, and close. --}}
                <div class="dm-head">
                    <div class="flex min-w-0 flex-wrap items-center gap-2">
                        <span class="pill" :class="active.needs_triage ? 'p-amber' : pillClass(active)"
                              x-text="active.needs_triage ? 'New assignment' : active.status_label"></span>
                        <span x-show="active.unread_messages > 0" x-cloak class="dm-chip-info">
                            <span x-text="active.unread_messages"></span> new message<span x-show="active.unread_messages > 1">s</span>
                        </span>
                    </div>
                    <button type="button" @click="close()" aria-label="Close" class="dm-close">
                        <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>

                <div class="dm-body">
                    {{-- Title + tracking number --}}
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="dm-title" x-text="active.document_type"></p>
                            <p class="dm-subtitle" x-text="active.citizen_name || '—'"></p>
                        </div>
                        @include('partials.qr-tile', ['record' => 'active'])
                    </div>

                    {{-- Progress: the four stages, moving as the actions below run. --}}
                    <ol class="dm-steps" aria-label="Progress">
                        <template x-for="(stage, i) in railStages" :key="stage.label">
                            <li class="dm-step" :class="'is-' + railState(i + 1)"
                                :aria-current="railState(i + 1) === 'current' ? 'step' : null">
                                <span class="dm-step-line" x-show="i > 0" aria-hidden="true"></span>
                                <span class="dm-step-dot" aria-hidden="true">
                                    <svg x-show="railState(i + 1) === 'done'" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 12.5 4 4 8-9"/></svg>
                                </span>
                                <span class="dm-step-label" x-text="stage.label"></span>
                            </li>
                        </template>
                    </ol>

                    {{-- Claiming date, and the two side panels. The date is what
                         the citizen opened their tracking page to find out, so it
                         is readable here and editable one click away — it used to
                         be reachable only from the Look Up desk. --}}
                    <div class="mt-6 flex flex-wrap items-stretch gap-2">
                        <div class="dm-date">
                            <p class="dm-date-label">Claiming Date</p>
                            <p class="dm-date-value" x-text="active.claim_date || active.requested_claim_date || 'Not set yet'"></p>
                        </div>

                        <button type="button" @click="toggleSide('date')"
                                class="dm-tile" :class="side === 'date' ? 'on' : ''" :aria-expanded="side === 'date'">
                            <svg fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5V12l3 2"/></svg>
                            <span>Adjust Date</span>
                        </button>

                        <button type="button" @click="toggleSide('messages')"
                                class="dm-tile relative" :class="side === 'messages' ? 'on' : ''" :aria-expanded="side === 'messages'">
                            <svg fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linejoin="round" d="M5 18.5V7a2.5 2.5 0 0 1 2.5-2.5h9A2.5 2.5 0 0 1 19 7v6.5a2.5 2.5 0 0 1-2.5 2.5H9l-4 2.5Z"/><path stroke-linecap="round" d="M9 10.5h.01M12 10.5h.01M15 10.5h.01"/></svg>
                            <span>Messages</span>
                            <span x-show="active.unread_messages > 0" x-cloak class="dm-tile-badge" x-text="active.unread_messages"></span>
                        </button>
                    </div>

                    @if($mode === 'staff')
                        {{-- Off-the-line states, with what the citizen or office is waiting on. --}}
                        <template x-if="active && !active.needs_triage && prog().isReturned">
                            <div class="dm-callout dm-callout-amber mt-5">
                                <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v6h6M3 13a9 9 0 1 0 3-7.7L3 8"/></svg>
                                <div>
                                    <p class="font-bold">Returned for revision</p>
                                    <template x-if="active.remarks"><p x-text="active.remarks"></p></template>
                                </div>
                            </div>
                        </template>
                        <template x-if="active && !active.needs_triage && prog().isHeld">
                            <div class="dm-callout dm-callout-amber mt-5">
                                <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6M14 9v6M5 5h14v14H5z"/></svg>
                                <div>
                                    <p>
                                        <span class="font-bold">On hold</span>
                                        <template x-if="active.blocked_by"><span> · waiting on <span x-text="active.blocked_by"></span></span></template>
                                        <template x-if="active.hold_until"><span> · until <span x-text="active.hold_until"></span></span></template>
                                        <template x-if="active.hold_overdue_days"><span class="hold-stalled"> · <span x-text="active.hold_overdue_days"></span>d past due</span></template>
                                    </p>
                                    <template x-if="active.hold_reason"><p x-text="active.hold_reason"></p></template>
                                </div>
                            </div>
                        </template>
                    @endif

                    {{-- More Info --}}
                    <section class="mt-7">
                        <h2 class="dm-section">More Info</h2>
                        <dl class="dm-info">
                            <template x-for="row in [
                                ['Request by', active.citizen_name],
                                ['Email', active.citizen_email],
                                ['Contact No.', active.citizen_contact],
                                ['Request Type', active.document_type],
                                ['Submitted', active.submitted_at],
                                ['Purpose', active.purpose],
                            ]" :key="row[0]">
                                <div class="dm-info-row" x-show="row[0] !== 'Purpose' || row[1]">
                                    <dt x-text="row[0]"></dt>
                                    <dd x-text="row[1] || '—'"></dd>
                                </div>
                            </template>
                        </dl>
                        <div x-show="active.description" class="dm-note">
                            <p class="dm-note-label">Description</p>
                            <p class="whitespace-pre-line" x-text="active.description"></p>
                        </div>
                    </section>

                    {{-- Requirement uploads: the citizen's evidence for each listed
                         requirement, each row linking to the authorized file stream. --}}
                    <section class="mt-7" x-show="active.requirements && active.requirements.length">
                        <h2 class="dm-section">Requirements for this request</h2>
                        <ul class="dm-files">
                            <template x-for="(req, i) in active.requirements" :key="i">
                                <li class="dm-file">
                                    <template x-if="req.has_file">
                                        <a :href="req.url" target="_blank" rel="noopener" class="dm-thumb">
                                            <template x-if="req.is_image"><img :src="req.url" alt=""></template>
                                            <template x-if="!req.is_image">
                                                <span class="dm-thumb-doc" :class="req.ext === 'pdf' && 'is-pdf'">
                                                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linejoin="round" d="M14 3.5H7.5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V8L14 3.5Z"/><path stroke-linejoin="round" d="M14 3.5V8h4.5"/></svg>
                                                    <span x-text="req.ext"></span>
                                                </span>
                                            </template>
                                        </a>
                                    </template>
                                    <template x-if="!req.has_file">
                                        <span class="dm-thumb is-missing" aria-hidden="true">
                                            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                                        </span>
                                    </template>

                                    <div class="min-w-0 flex-1">
                                        <p class="dm-file-name" x-text="req.label"></p>
                                        <p class="dm-file-meta">
                                            <span x-show="req.is_mandatory" class="font-semibold text-status-red">Required</span>
                                            <span x-show="!req.is_mandatory">Optional</span>
                                            <span x-show="!req.has_file"> · not uploaded</span>
                                        </p>
                                    </div>

                                    <a x-show="req.has_file" :href="req.url" target="_blank" rel="noopener" class="dm-file-open">View</a>
                                </li>
                            </template>
                        </ul>
                    </section>

                    <section class="mt-7" x-show="active.attachments && active.attachments.length">
                        <h2 class="dm-section">Attachments</h2>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <template x-for="(att, i) in active.attachments" :key="i">
                                <a :href="att.url" target="_blank" rel="noopener" class="dm-thumb dm-thumb-lg">
                                    <template x-if="att.is_image"><img :src="att.url" alt="Attachment"></template>
                                    <template x-if="!att.is_image">
                                        <span class="dm-thumb-doc" :class="att.ext === 'pdf' && 'is-pdf'">
                                            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linejoin="round" d="M14 3.5H7.5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V8L14 3.5Z"/><path stroke-linejoin="round" d="M14 3.5V8h4.5"/></svg>
                                            <span x-text="att.ext"></span>
                                        </span>
                                    </template>
                                </a>
                            </template>
                        </div>
                    </section>

                    {{-- Logs: the stage history, two entries deep until asked.
                         Ported from the Look Up desk — the one place this used
                         to be readable. --}}
                    <section class="mt-7">
                        <h2 class="dm-section">Logs</h2>

                        <template x-if="(active.logs || []).length === 0">
                            <p class="mt-2 text-[length:var(--fs-small)] text-ink-soft">Nothing recorded yet.</p>
                        </template>

                        <ul class="dm-logs">
                            <template x-for="(log, i) in (active.logs || [])" :key="i">
                                <li class="dm-log" x-show="i < 2 || allLogs">
                                    <span class="dm-log-dot" aria-hidden="true"></span>
                                    <span class="dm-log-event" x-text="log.event"></span>
                                    <span class="lookup-leader" aria-hidden="true"></span>
                                    <span class="dm-log-time" x-text="log.time"></span>
                                </li>
                            </template>
                        </ul>

                        <button type="button" x-show="(active.logs || []).length > 2" @click="allLogs = ! allLogs" class="dm-more">
                            <span x-text="allLogs ? 'View less' : 'View more'">View more</span>
                            <svg class="h-4 w-4 transition" :class="allLogs ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                        </button>
                    </section>

                    @if($mode === 'supervisor')
                        {{-- Only an unassigned request can be assigned. The
                             In Progress queue opens this same modal, and
                             offering "Assign to Staff" on work somebody is
                             already doing invites reassignment by accident. --}}
                        <template x-if="! active.assigned_to">
                            <section class="dm-panel-strong mt-7">
                                <label class="block text-[length:var(--fs-small)] font-bold text-green-deep" for="reviewAssignee">Assign to Staff <span class="text-status-red">*</span></label>
                                <select id="reviewAssignee" x-model="selectedStaff" class="st-filter-field mt-2">
                                    <option value="">Select a staff member…</option>
                                    <template x-for="member in staff" :key="member.id">
                                        <option :value="member.id" x-text="member.name"></option>
                                    </template>
                                </select>
                                <p class="mt-2 text-[length:var(--fs-caption)] text-ink-soft">Approving assigns the request to the selected staff member. They must <strong class="text-ink">accept it on their Requests page</strong> before work begins.</p>
                            </section>
                        </template>

                        <template x-if="active.assigned_to">
                            <section class="dm-panel-strong mt-7 text-[length:var(--fs-small)] text-ink">
                                <p>Being handled by <strong class="text-green-deep" x-text="active.assigned_staff || 'a staff member'"></strong>.
                                   You can adjust the claiming date and answer the citizen from here; the stage itself is theirs to advance.</p>
                            </section>
                        </template>
                    @elseif($mode === 'oversight')
                        {{-- The super admin's Requests page: they watch every
                             request and can nudge whoever owns it, but the
                             assignment is the office's and the stage is the
                             staff member's. --}}
                        <section class="dm-panel-strong mt-7 text-[length:var(--fs-small)] text-ink">
                            <template x-if="active.assigned_to">
                                <p>Being handled by <strong class="text-green-deep" x-text="active.assigned_staff || 'a staff member'"></strong>
                                   <span x-show="active.department" x-text="'at the ' + active.department"></span>.
                                   The stage is theirs to advance; use <strong>Notify</strong> if it needs a push.</p>
                            </template>
                            <template x-if="! active.assigned_to">
                                <p x-text="active.department
                                    ? `Not assigned yet. The ${active.department} supervisors assign it.`
                                    : 'This request reached no office: its request type has none. Give the type an office on the Request Types page.'"></p>
                            </template>
                        </section>
                    @else
                        {{-- Triage: this assignment is still awaiting Accept / Decline / Revision. --}}
                        <template x-if="active && active.needs_triage">
                            <section class="dm-panel-strong mt-7 space-y-2 text-[length:var(--fs-small)] text-ink">
                                <p class="font-bold text-green-deep">Review this assignment before deciding.</p>
                                <p>Check the citizen details and requirements above, then choose <strong>Accept</strong> to start work, <strong>Decline</strong> to send it back to the supervisor, or <strong>Request revision</strong> if the citizen must fix something first.</p>
                            </section>
                        </template>

                        <template x-if="active && !active.needs_triage && cockpitHint()">
                            <p class="mt-6 text-[length:var(--fs-small)] text-ink-soft" x-text="cockpitHint()"></p>
                        </template>
                    @endif

                </div>

                <div class="dm-foot dm-actions">
                    {{-- Stage-gate / concurrency failures surface here, inline — no alert(). --}}
                    <p x-show="gateError" x-cloak x-text="gateError" role="alert"
                       class="w-full rounded-[10px] bg-status-red-wash px-3 py-2 text-[length:var(--fs-small)] font-semibold text-status-red"></p>

                    <div class="ml-auto flex flex-wrap items-center justify-end gap-2">
                        <button type="button" @click="close()" class="cr-btn">
                            <span x-text="(active && active.needs_triage) || mode === 'oversight' ? 'Close' : 'Cancel'"></span>
                        </button>

                        @if($mode === 'oversight')
                            {{-- Opens the shared Notify dialog (admin/partials/notify-dialog). --}}
                            <template x-if="active && active.nudge_url">
                                <button type="button" class="cr-btn cr-btn-primary"
                                        @click="$dispatch('admin-notify', {
                                            tracking: active.tracking_number, type: active.document_type,
                                            citizen: active.citizen_name, purpose: active.purpose,
                                            stageLabel: active.status_label, assignee: active.assigned_staff,
                                            office: active.department, over: active.over_sla, nudgeUrl: active.nudge_url,
                                        })">
                                    Notify
                                </button>
                            </template>
                        @elseif($mode === 'supervisor')
                            <template x-if="! active.assigned_to">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <button type="button" @click="denyOpen = true" :disabled="submitting"
                                            class="cr-btn cr-btn-danger disabled:cursor-not-allowed disabled:opacity-50">
                                        Deny
                                    </button>
                                    <button type="button" @click="approve()" :disabled="!selectedStaff || submitting"
                                            class="cr-btn cr-btn-primary disabled:cursor-not-allowed disabled:opacity-50">
                                        <span x-show="!submitting">Approve</span>
                                        <span x-show="submitting">Approving…</span>
                                    </button>
                                </div>
                            </template>
                        @else
                            {{-- Triage decision --}}
                            <template x-if="active && active.needs_triage">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <button type="button" @click="revisionOpen = true" :disabled="submitting"
                                            class="cr-btn !border-status-amber-wash !bg-status-amber-wash !text-status-amber disabled:cursor-not-allowed disabled:opacity-50">
                                        Request revision
                                    </button>
                                    <button type="button" @click="declineOpen = true" :disabled="submitting"
                                            class="cr-btn cr-btn-danger disabled:cursor-not-allowed disabled:opacity-50">
                                        Decline
                                    </button>
                                    <button type="button" @click="acceptAssignment()" :disabled="submitting"
                                            class="cr-btn cr-btn-primary disabled:cursor-not-allowed disabled:opacity-50">
                                        <span x-show="!submitting">Accept</span>
                                        <span x-show="submitting">Accepting…</span>
                                    </button>
                                </div>
                            </template>

                            {{-- Working states: the next step, and sending it back to the citizen. --}}
                            <template x-if="active && !active.needs_triage && prog().primary.type !== 'none'">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <button type="button" x-show="prog().canReturn" @click="returnOpen = true"
                                            :disabled="submitting || active.can_advance === false"
                                            class="cr-btn !border-status-amber-wash !bg-status-amber-wash !text-status-amber disabled:cursor-not-allowed disabled:opacity-50">
                                        Return for revision
                                    </button>
                                    <button type="button" @click="runPrimary()" :disabled="submitting || active.can_advance === false"
                                            :title="active.can_advance === false ? 'You don\'t have permission to advance this document' : ''"
                                            class="cr-btn cr-btn-primary disabled:cursor-not-allowed disabled:opacity-50">
                                        <span x-show="!submitting" x-text="prog().primary.label"></span>
                                        <span x-show="submitting">Working…</span>
                                    </button>
                                </div>
                            </template>
                        @endif
                    </div>
                </div>

                @if($mode === 'supervisor')
                <div x-show="denyOpen" x-cloak
                     class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 px-4"
                     @keydown.escape.window="denyOpen = false" @click.self="denyOpen = false">
                    <div class="w-full max-w-md rounded-2xl border border-hairline bg-paper shadow-xl" x-show="denyOpen" x-transition>
                        <div class="border-b border-hairline px-6 py-4">
                            <h3 class="text-base font-bold text-ink">Deny request</h3>
                            <p class="mt-0.5 text-sm text-ink-soft">This rejects the request. You can add an optional reason for the record.</p>
                        </div>
                        <div class="px-6 py-5">
                            <label class="block text-sm font-semibold text-ink">Reason <span class="font-normal text-ink-soft">(optional)</span></label>
                            <textarea x-model="denyReason" rows="4" maxlength="1000" placeholder="e.g. Incomplete requirements, duplicate request…"
                                      class="mt-1 w-full rounded-lg border border-hairline-strong bg-paper px-3 py-2.5 text-sm shadow-sm focus:border-status-red focus:outline-none focus:ring-2 focus:ring-status-red/20"></textarea>
                        </div>
                        <div class="flex items-center justify-end gap-3 border-t border-hairline px-6 py-4">
                            <button type="button" @click="denyOpen = false" :disabled="submitting"
                                    class="cr-btn !px-5 !py-2.5 !text-sm disabled:opacity-50">
                                Cancel
                            </button>
                            <button type="button" @click="deny()" :disabled="submitting"
                                    class="cr-btn cr-btn-danger !border-status-red !bg-status-red !text-white hover:!bg-[#6e1f1f] !px-5 !py-2.5 !text-sm disabled:cursor-not-allowed disabled:opacity-50">
                                <span x-show="!submitting">Deny request</span>
                                <span x-show="submitting">Denying…</span>
                            </button>
                        </div>
                    </div>
                </div>
                @endif

                @if($mode === 'staff')
                <div x-show="declineOpen" x-cloak
                     class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 px-4"
                     @keydown.escape.window="declineOpen = false" @click.self="declineOpen = false">
                    <div class="w-full max-w-md rounded-2xl border border-hairline bg-paper shadow-xl" x-show="declineOpen" x-transition>
                        <div class="border-b border-hairline px-6 py-4">
                            <h3 class="text-base font-bold text-ink">Decline assignment</h3>
                            <p class="mt-0.5 text-sm text-ink-soft">This returns the request to the supervisor queue. You can add an optional note.</p>
                        </div>
                        <div class="px-6 py-5">
                            <label class="block text-sm font-semibold text-ink">Note <span class="font-normal text-ink-soft">(optional)</span></label>
                            <textarea x-model="declineReason" rows="3" maxlength="1000" placeholder="e.g. Not in my area of responsibility…"
                                      class="mt-1 w-full rounded-lg border border-hairline-strong bg-paper px-3 py-2.5 text-sm shadow-sm focus:border-status-red focus:outline-none focus:ring-2 focus:ring-status-red/20"></textarea>
                        </div>
                        <div class="flex items-center justify-end gap-3 border-t border-hairline px-6 py-4">
                            <button type="button" @click="declineOpen = false" :disabled="submitting"
                                    class="cr-btn !px-5 !py-2.5 !text-sm disabled:opacity-50">
                                Cancel
                            </button>
                            <button type="button" @click="declineAssignment()" :disabled="submitting"
                                    class="cr-btn cr-btn-danger !border-status-red !bg-status-red !text-white hover:!bg-[#6e1f1f] !px-5 !py-2.5 !text-sm disabled:cursor-not-allowed disabled:opacity-50">
                                <span x-show="!submitting">Decline assignment</span>
                                <span x-show="submitting">Declining…</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div x-show="revisionOpen" x-cloak
                     class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 px-4"
                     @keydown.escape.window="revisionOpen = false" @click.self="revisionOpen = false">
                    <div class="w-full max-w-md rounded-2xl border border-hairline bg-paper shadow-xl" x-show="revisionOpen" x-transition>
                        <div class="border-b border-hairline px-6 py-4">
                            <h3 class="text-base font-bold text-ink">Request revision</h3>
                            <p class="mt-0.5 text-sm text-ink-soft">Tell the citizen what needs to be fixed. The request will be marked Returned / For Revision.</p>
                        </div>
                        <div class="px-6 py-5">
                            <label class="block text-sm font-semibold text-ink">What needs to be revised? <span class="text-status-red">*</span></label>
                            <textarea x-model="revisionReason" rows="4" maxlength="1000" required placeholder="e.g. Missing barangay clearance, illegible ID photo…"
                                      class="mt-1 w-full rounded-lg border border-hairline-strong bg-paper px-3 py-2.5 text-sm shadow-sm focus:border-status-amber focus:outline-none focus:ring-2 focus:ring-status-amber/20"></textarea>
                        </div>
                        <div class="flex items-center justify-end gap-3 border-t border-hairline px-6 py-4">
                            <button type="button" @click="revisionOpen = false" :disabled="submitting"
                                    class="cr-btn !px-5 !py-2.5 !text-sm disabled:opacity-50">
                                Cancel
                            </button>
                            <button type="button" @click="requestRevision()" :disabled="submitting || !revisionReason.trim()"
                                    class="cr-btn !border-status-amber !bg-status-amber !text-white hover:!bg-[#6e4906] !px-5 !py-2.5 !text-sm disabled:cursor-not-allowed disabled:opacity-50">
                                <span x-show="!submitting">Send for revision</span>
                                <span x-show="submitting">Sending…</span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Cockpit: confirm an advance with a transition note (required for the review sign-off →Approved). --}}
                <div x-show="advanceOpen" x-cloak
                     class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 px-4"
                     @keydown.escape.window="advanceOpen = false" @click.self="advanceOpen = false">
                    <div class="w-full max-w-md rounded-2xl border border-hairline bg-paper shadow-xl" x-show="advanceOpen" x-transition>
                        <div class="border-b border-hairline px-6 py-4">
                            <h3 class="text-base font-bold text-ink" x-text="advanceTargetLabel() === 'Approved' ? 'Process this request' : 'Advance to ' + advanceTargetLabel()"></h3>
                            <p class="mt-0.5 text-sm text-ink-soft" x-text="advanceHint()"></p>
                        </div>
                        <div class="px-6 py-5">
                            <label class="block text-sm font-semibold text-ink">
                                <span x-text="active && active.status === 'in_review' ? 'Review note' : (advanceNoteRequired() ? 'Work note' : 'Note')"></span>
                                <span x-show="advanceNoteRequired()" class="text-status-red">*</span>
                                <span x-show="!advanceNoteRequired()" class="font-normal text-ink-soft">(optional)</span>
                            </label>
                            <textarea x-model="advanceNote" rows="3" maxlength="1000"
                                      @keydown.meta.enter="confirmAdvance()" @keydown.ctrl.enter="confirmAdvance()"
                                      :placeholder="active && active.status === 'in_review' ? 'e.g. Requirements verified against the checklist; clearances valid' : (advanceNoteRequired() ? 'e.g. Verified the submitted documents; started processing' : 'Anything worth recording about this step…')"
                                      class="mt-1 w-full rounded-lg border border-hairline-strong bg-paper px-3 py-2.5 text-sm shadow-sm focus:border-green focus:outline-none focus:ring-2 focus:ring-green/20"></textarea>
                        </div>
                        <div class="flex items-center justify-end gap-3 border-t border-hairline px-6 py-4">
                            <button type="button" @click="advanceOpen = false" :disabled="submitting"
                                    class="cr-btn !px-5 !py-2.5 !text-sm disabled:opacity-50">Cancel</button>
                            <button type="button" @click="confirmAdvance()" :disabled="submitting || (advanceNoteRequired() && !advanceNote.trim())"
                                    class="cr-btn cr-btn-primary !px-5 !py-2.5 !text-sm disabled:cursor-not-allowed disabled:opacity-50">
                                <span x-show="!submitting">Confirm — advance <span class="opacity-60">⌘↵</span></span>
                                <span x-show="submitting">Advancing…</span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Cockpit: move back a stage — the reason is part of the audit trail. --}}
                <div x-show="revertOpen" x-cloak
                     class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 px-4"
                     @keydown.escape.window="revertOpen = false" @click.self="revertOpen = false">
                    <div class="w-full max-w-md rounded-2xl border border-hairline bg-paper shadow-xl" x-show="revertOpen" x-transition>
                        <div class="border-b border-hairline px-6 py-4">
                            <h3 class="text-base font-bold text-ink">Move back a stage</h3>
                            <p class="mt-0.5 text-sm text-ink-soft">Moves the request one stage backwards. The reason is recorded in the document's history.</p>
                        </div>
                        <div class="px-6 py-5">
                            <label class="block text-sm font-semibold text-ink">Why is this moving back? <span class="text-status-red">*</span></label>
                            <textarea x-model="revertReason" rows="3" maxlength="1000" required placeholder="e.g. Advanced by mistake, review found an issue…"
                                      class="mt-1 w-full rounded-lg border border-hairline-strong bg-paper px-3 py-2.5 text-sm shadow-sm focus:border-green focus:outline-none focus:ring-2 focus:ring-green/20"></textarea>
                        </div>
                        <div class="flex items-center justify-end gap-3 border-t border-hairline px-6 py-4">
                            <button type="button" @click="revertOpen = false" :disabled="submitting"
                                    class="cr-btn !px-5 !py-2.5 !text-sm disabled:opacity-50">Cancel</button>
                            <button type="button" @click="confirmRevert()" :disabled="submitting || !revertReason.trim()"
                                    class="cr-btn cr-btn-primary !px-5 !py-2.5 !text-sm disabled:cursor-not-allowed disabled:opacity-50">
                                <span x-show="!submitting">Confirm — move back</span>
                                <span x-show="submitting">Moving…</span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Cockpit: return an in-progress request to the citizen for revision. --}}
                <div x-show="returnOpen" x-cloak
                     class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 px-4"
                     @keydown.escape.window="returnOpen = false" @click.self="returnOpen = false">
                    <div class="w-full max-w-md rounded-2xl border border-hairline bg-paper shadow-xl" x-show="returnOpen" x-transition>
                        <div class="border-b border-hairline px-6 py-4">
                            <h3 class="text-base font-bold text-ink">Return for revision</h3>
                            <p class="mt-0.5 text-sm text-ink-soft">Tell the citizen what needs to be fixed. The request moves to Returned / For Revision and the citizen can see this note.</p>
                        </div>
                        <div class="px-6 py-5">
                            <label class="block text-sm font-semibold text-ink">What needs to be revised? <span class="text-status-red">*</span></label>
                            <textarea x-model="returnReason" rows="4" maxlength="1000" required placeholder="e.g. Missing barangay clearance, illegible ID photo…"
                                      class="mt-1 w-full rounded-lg border border-hairline-strong bg-paper px-3 py-2.5 text-sm shadow-sm focus:border-status-amber focus:outline-none focus:ring-2 focus:ring-status-amber/20"></textarea>
                        </div>
                        <div class="flex items-center justify-end gap-3 border-t border-hairline px-6 py-4">
                            <button type="button" @click="returnOpen = false" :disabled="submitting"
                                    class="cr-btn !px-5 !py-2.5 !text-sm disabled:opacity-50">Cancel</button>
                            <button type="button" @click="returnForRevision()" :disabled="submitting || !returnReason.trim()"
                                    class="cr-btn !border-status-amber !bg-status-amber !text-white hover:!bg-[#6e4906] !px-5 !py-2.5 !text-sm disabled:cursor-not-allowed disabled:opacity-50">
                                <span x-show="!submitting">Return for revision</span>
                                <span x-show="submitting">Returning…</span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Cockpit: park a blocked request (SLA pauses while on hold). --}}
                <div x-show="holdOpen" x-cloak
                     class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 px-4"
                     @keydown.escape.window="holdOpen = false" @click.self="holdOpen = false">
                    <div class="w-full max-w-md rounded-2xl border border-hairline bg-paper shadow-xl" x-show="holdOpen" x-transition>
                        <div class="border-b border-hairline px-6 py-4">
                            <h3 class="text-base font-bold text-ink">Put on hold</h3>
                            <p class="mt-0.5 text-sm text-ink-soft">Use this when the request is blocked and waiting. The SLA timer pauses until you resume it.</p>
                        </div>
                        <div class="space-y-4 px-6 py-5">
                            <div>
                                <label class="block text-sm font-semibold text-ink">Reason <span class="text-status-red">*</span></label>
                                <textarea x-model="holdReason" rows="3" maxlength="1000" required placeholder="e.g. Waiting for the citizen to submit a clearance"
                                          class="mt-1 w-full rounded-lg border border-hairline-strong bg-paper px-3 py-2.5 text-sm shadow-sm focus:border-status-amber focus:outline-none focus:ring-2 focus:ring-status-amber/20"></textarea>
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-semibold text-ink">Who's blocking?</label>
                                    <select x-model="holdBlockedBy"
                                            class="mt-1 w-full rounded-lg border border-hairline-strong bg-paper px-3 py-2.5 text-sm shadow-sm focus:border-green focus:outline-none focus:ring-2 focus:ring-green/20">
                                        <option value="citizen">Citizen</option>
                                        <option value="external">External office</option>
                                        <option value="internal">Internal</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-ink">Follow-up date <span class="font-normal text-ink-soft">(optional)</span></label>
                                    <input type="date" x-model="holdUntil" min="{{ now()->toDateString() }}"
                                           class="mt-1 w-full rounded-lg border border-hairline-strong bg-paper px-3 py-2.5 text-sm shadow-sm focus:border-green focus:outline-none focus:ring-2 focus:ring-green/20">
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-end gap-3 border-t border-hairline px-6 py-4">
                            <button type="button" @click="holdOpen = false" :disabled="submitting"
                                    class="cr-btn !px-5 !py-2.5 !text-sm disabled:opacity-50">Cancel</button>
                            <button type="button" @click="putOnHold()" :disabled="submitting || !holdReason.trim()"
                                    class="cr-btn !border-status-amber !bg-status-amber !text-white hover:!bg-[#6e4906] !px-5 !py-2.5 !text-sm disabled:cursor-not-allowed disabled:opacity-50">
                                <span x-show="!submitting">Put on hold</span>
                                <span x-show="submitting">Holding…</span>
                            </button>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </template>
    </div>

    {{-- ── Side card: the claiming-date calendar, or the conversation ──────
         Both ported from the Look Up desk (Figma: STAFF LOOK UP 1321:1370 and
         1321:1750) so the staff member who accepted a request can also set its
         claiming date and answer the citizen without changing screens. --}}
    <div x-show="active && side" x-cloak x-transition.opacity
         class="dm-fixed flex w-full max-w-[660px] flex-col overflow-hidden rounded-[16px] bg-white shadow-[0_19px_48px_-16px_rgba(0,64,4,0.45)] lg:min-w-0 lg:flex-1"
         :aria-label="side === 'date' ? 'Adjust claiming date' : 'Messages'" role="region">
        <template x-if="active">
            <div class="flex h-full min-h-0 flex-col">

                {{-- Shared header --}}
                <div class="dm-head">
                    <h2 class="dm-section min-w-0 truncate">
                        <span x-show="side === 'date'">Adjust Claiming Date</span>
                        <span x-show="side === 'messages'">
                            Messages · <span class="font-semibold text-ink" x-text="active.citizen_name || 'Citizen'"></span>
                        </span>
                    </h2>

                    <button type="button" @click="side = null" aria-label="Close panel" class="dm-close shrink-0">
                        <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>

                {{-- ── Calendar ── --}}
                <div x-show="side === 'date'" class="flex min-h-0 flex-1 flex-col">
                    {{-- Where things stand before anything is picked. --}}
                    <div class="dp-context">
                        <div class="dp-context-item">
                            <span class="dp-key dp-key-requested" aria-hidden="true"></span>
                            <span>Citizen asked for</span>
                            <strong x-text="active.requested_claim_date || 'no date'"></strong>
                        </div>
                        <div class="dp-context-item" x-show="active.claim_date">
                            <span class="dp-key dp-key-set" aria-hidden="true"></span>
                            <span>Currently set</span>
                            <strong x-text="active.claim_date"></strong>
                        </div>
                    </div>

                    {{-- Month, with Previous / Today / Next as one control. --}}
                    <div class="dp-head">
                        <p class="dp-month" x-text="monthLabel()" aria-live="polite"></p>
                        <div class="bk-nav" role="group" aria-label="Change month">
                            <button type="button" @click="shiftMonth(-1)" aria-label="Previous month">
                                <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6"/></svg>
                            </button>
                            <button type="button" @click="calendarToToday()">Today</button>
                            <button type="button" @click="shiftMonth(1)" aria-label="Next month">
                                <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="dp-dows" aria-hidden="true">
                        <template x-for="(name, i) in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="name">
                            <span :class="i === 0 && 'is-sunday'" x-text="name"></span>
                        </template>
                    </div>

                    <div class="dp-grid" role="grid" aria-label="Claiming date">
                        <template x-for="(week, w) in calendarWeeks()" :key="'w' + w">
                            <div class="dp-week" role="row">
                                <template x-for="cell in week" :key="cell.iso">
                                    <div class="dp-cell" role="gridcell">
                                        <button type="button" @click="dateValue = cell.iso"
                                                :disabled="cell.past"
                                                class="dp-day"
                                                :class="{
                                                    'is-picked': dateValue === cell.iso,
                                                    'is-today': cell.today,
                                                    'is-outside': ! cell.inMonth,
                                                    'is-sunday': cell.sunday,
                                                    'is-requested': cell.iso === active.requested_claim_on,
                                                    'is-set': cell.iso === active.claim_on,
                                                }"
                                                :aria-pressed="dateValue === cell.iso"
                                                :aria-current="cell.today ? 'date' : null"
                                                :aria-label="cell.label
                                                    + (cell.iso === active.requested_claim_on ? ', requested by the citizen' : '')
                                                    + (cell.iso === active.claim_on ? ', currently set' : '')
                                                    + (cell.past ? ', unavailable' : '')">
                                            <span x-text="cell.day"></span>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>

                    <div class="dp-foot">
                        <p class="dp-picked">
                            <template x-if="dateValue">
                                <span>New claiming date <strong x-text="pickedLabel()"></strong></span>
                            </template>
                            <template x-if="! dateValue">
                                <span>Pick a day for the citizen to claim.</span>
                            </template>
                        </p>

                        <div class="flex shrink-0 items-center gap-2">
                            <button type="button" x-show="dateValue" @click="dateValue = ''" class="cr-btn">Clear</button>
                            <button type="button" @click="saveDate()" :disabled="submitting || ! dateValue" class="cr-btn cr-btn-primary disabled:opacity-40">
                                Apply
                            </button>
                        </div>
                    </div>
                </div>

                {{-- ── Conversation ── --}}
                <div x-show="side === 'messages'" class="flex min-h-0 flex-1 flex-col">
                    <div class="min-h-[256px] flex-1 space-y-[8px] overflow-y-auto px-[26.5px] py-[19px]" x-ref="thread">

                        {{-- Empty state, as the frame draws it. --}}
                        <div x-show="(active.messages || []).length === 0" class="flex h-full flex-col items-center justify-center text-center">
                            <img src="{{ asset('images/staff/chat-empty.svg') }}" alt=""
                                 style="width:80px; height:78.5px; max-width:none;">
                            <p class="mt-[14.5px] text-[20px] font-extrabold text-[#017209]">You are now chatting with the client</p>
                            <p class="text-[18px] font-medium text-black">Your conversation is secured</p>
                        </div>

                        <template x-for="(msg, i) in (active.messages || [])" :key="i">
                            <div>
                                {{-- Timestamp above the first message and whenever the
                                     clock moves on, the way a chat groups a burst. --}}
                                <p x-show="i === 0 || msg.time !== active.messages[i - 1].time"
                                   class="py-[8px] text-center text-[14px] font-medium text-black"
                                   x-text="msg.time"></p>

                                <div class="flex" :class="msg.from === 'citizen' ? 'justify-start' : 'justify-end'">
                                    <div class="max-w-[80%]">
                                        <p class="desk-bubble" :class="msg.from === 'citizen' ? 'from-citizen' : 'from-staff'"
                                           x-text="msg.body"></p>

                                        <template x-if="msg.attachment">
                                            <a :href="msg.attachment.url" target="_blank" rel="noopener"
                                               class="mt-[6px] flex items-center gap-[8px] rounded-[8px] border border-hairline bg-white px-[11px] py-[8px] text-[14px] font-medium text-ink transition hover:bg-green-wash"
                                               :class="msg.from === 'citizen' ? '' : 'justify-end'">
                                                <img src="{{ asset('images/staff/icon-file.svg') }}" alt=""
                                                     style="width:16px; height:16px; max-width:none;">
                                                <span class="min-w-0 truncate" x-text="msg.attachment.name"></span>
                                            </a>
                                        </template>

                                        {{-- Read receipt: the frame's double tick, dimmed
                                             until the citizen has actually opened it. --}}
                                        <p x-show="msg.from !== 'citizen'"
                                           class="mt-[4px] flex items-center justify-end gap-[4px] text-[14px] font-medium text-black">
                                            <span x-text="msg.read ? 'Read' : 'Sent'"></span>
                                            <span class="flex items-center" :class="msg.read ? '' : 'opacity-40'" aria-hidden="true">
                                                <img src="{{ asset('images/staff/icon-check.svg') }}" alt=""
                                                     style="width:14.5px; height:14.5px; max-width:none;">
                                                <img src="{{ asset('images/staff/icon-check.svg') }}" alt=""
                                                     class="-ml-[7px]" style="width:14.5px; height:14.5px; max-width:none;">
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="h-[2px] w-full bg-[#d9d9d9]"></div>

                    <template x-if="active.can_message">
                        <form @submit.prevent="sendMessage()" class="px-[23px] py-[14.5px]">
                            {{-- The file rides along with the message it belongs to,
                                 so it is staged here and posted on send. --}}
                            <div x-show="file" x-cloak
                                 class="mb-[8px] flex items-center gap-[8px] rounded-[8px] bg-green-wash px-[11px] py-[8px]">
                                <img src="{{ asset('images/staff/icon-file.svg') }}" alt=""
                                     style="width:16px; height:16px; max-width:none;">
                                <span class="min-w-0 flex-1 truncate text-[14px] font-medium text-ink" x-text="file?.name"></span>
                                <button type="button" @click="clearFile()"
                                        class="shrink-0 text-[14px] font-bold text-status-red hover:underline">Remove</button>
                            </div>

                            <div class="flex items-center gap-[16px]">
                                <input type="file" x-ref="attachment" class="sr-only"
                                       accept="{{ \App\Support\UploadRules::accept() }}"
                                       @change="pickFile($event)">

                                <button type="button" @click="$refs.attachment.click()"
                                        title="{{ \App\Support\UploadRules::hint() }}" aria-label="Attach a file"
                                        class="shrink-0 rounded-lg p-1 transition hover:bg-green-wash focus:outline-none focus-visible:ring-2 focus-visible:ring-green">
                                    <img src="{{ asset('images/staff/icon-plus.svg') }}" alt=""
                                         style="width:24px; height:24px; max-width:none;">
                                </button>

                                <label for="reviewMessage" class="sr-only">Write your message</label>
                                <input id="reviewMessage" type="text" x-model="draft" maxlength="5000"
                                       placeholder="Write your message..." class="desk-composer">

                                <button type="submit" :disabled="sending || ! draft.trim()"
                                        class="shrink-0 rounded-lg p-1 transition hover:bg-green-wash disabled:opacity-40"
                                        aria-label="Send message">
                                    <img src="{{ asset('images/staff/icon-send.svg') }}" alt=""
                                         style="width:24px; height:24px; max-width:none;">
                                </button>
                            </div>
                        </form>
                    </template>

                    <template x-if="! active.can_message">
                        <p class="px-[26.5px] py-[17.5px] text-[14px] text-ink-soft">
                            Only the assigned staff member or an admin can reply on this request.
                        </p>
                    </template>
                </div>
            </div>
        </template>
    </div>
    </div>
</div>

@once
<script>
    /** The four-stage bar's stages, from DocumentStatus::railStages(). */
    window.REVIEW_RAIL = @js(\App\Enums\DocumentStatus::railStages());

    window.reviewPanel = function (cfg) {
        return {
            railStages: window.REVIEW_RAIL,
            requests: cfg.requests || [],
            mode: cfg.mode || 'supervisor',
            staff: cfg.staff || [],
            flow: cfg.flow || [],
            assignBase: cfg.assignBase || '',
            denyBase: cfg.denyBase || cfg.assignBase || '',
            openBase: cfg.openBase || '',
            completeBase: cfg.completeBase || '',
            reloadUrl: cfg.reloadUrl || null,
            active: null,
            qrOpen: false,
            selectedStaff: '',
            submitting: false,
            denyOpen: false,
            denyReason: '',
            declineOpen: false,
            declineReason: '',
            revisionOpen: false,
            revisionReason: '',
            holdOpen: false,
            holdReason: '',
            holdBlockedBy: 'citizen',
            holdUntil: '',
            returnOpen: false,
            returnReason: '',
            advanceOpen: false,
            advanceNote: '',
            revertOpen: false,
            revertReason: '',
            gateError: '',
            tableQuery: '',

            // ─── Side panels, ported from the Look Up desk ───
            side: null,       // 'date' | 'messages' | null — the second card
            allLogs: false,
            dateValue: '',
            calYear: 0,
            calMonth: 0,      // 0-11, as Date reports it
            draft: '',
            file: null,       // staged attachment, posted with the message
            sending: false,

            csrf() {
                return document.querySelector('meta[name=csrf-token]')?.content;
            },

            /**
             * Rows matching the in-table search (citizen name, tracking number,
             * or request type). Filtering client-side keeps the queue instant —
             * this list is a triage queue, not a full archive.
             */
            visibleRequests() {
                const q = this.tableQuery.trim().toLowerCase();
                if (!q) return this.requests;

                return this.requests.filter((req) => [req.citizen_name, req.tracking_number, req.document_type, req.department]
                    .some((field) => (field || '').toLowerCase().includes(q)));
            },

            // ─── Cockpit: client-side status logic (mirrors DocumentStatus enum) ───

            /** Table pill colour band by status. */
            pillClass(req) {
                if (req.needs_triage) return 'p-muted';
                if (req.status === 'approved' || req.status === 'completed') return 'p-green';
                if (req.status === 'returned' || req.status === 'denied') return 'p-red';
                if (req.status === 'on_hold') return 'p-amber';
                return 'p-amber';
            },

            stageIndex(status) {
                return this.flow.findIndex((s) => s.value === status);
            },

            labelFor(status) {
                const s = this.flow.find((f) => f.value === status);
                if (s) return s.label;
                return { returned: 'Returned / For Revision', on_hold: 'On Hold', denied: 'Denied' }[status] || status;
            },

            /** The single source of truth for what the cockpit can do at this stage. */
            prog() {
                const st = this.active ? this.active.status : null;
                const idx = this.stageIndex(st);
                const isReturned = st === 'returned';
                const isHeld = st === 'on_hold';
                const isTerminal = st === 'completed' || st === 'denied';

                // On hold: keep the stepper on the stage it paused at.
                const displayStatus = isHeld ? (this.active?.status_before_hold || st) : st;
                const dispIdx = this.stageIndex(displayStatus);
                const displayPosition = dispIdx === -1 ? 0 : dispIdx + 1;

                let primary;
                if (isReturned) primary = { type: 'resume', label: 'Resume — back to In Progress' };
                else if (isHeld) primary = { type: 'unhold', label: 'Resume — lift hold' };
                else if (st === 'approved') primary = { type: 'approve', label: 'Approve & complete' };
                else if (idx >= 0 && idx < this.flow.length - 2) primary = { type: 'advance', label: this.advanceLabel(this.flow[idx + 1]) };
                else primary = { type: 'none', label: null };

                return {
                    displayPosition,
                    isReturned, isHeld, isTerminal,
                    canRevert: idx > 0,
                    canReturn: !isReturned && !isHeld && !isTerminal && idx >= 0,
                    canHold: !isHeld && !isTerminal && idx >= 0,
                    primary,
                };
            },

            /**
             * Stage of the active request on the four-stage bar — mirrors
             * DocumentStatus::railStage(), but runs here so the bar moves the
             * moment an action changes the status, without a reload.
             */
            railStage() {
                const at = (status) => this.railStages.findIndex((stage) => stage.statuses.includes(status)) + 1;
                const st = this.active ? this.active.status : null;

                if (st === 'returned') return at('in_review');
                if (st === 'on_hold' || st === 'denied') return at(this.active.status_before_hold) || at('in_review');

                return at(st) || 1;
            },

            /** 'done' | 'current' | 'todo' for the stage at `position`. */
            railState(position) {
                const current = this.railStage();
                if (position < current || (position === current && this.active?.status === 'completed')) return 'done';

                return position === current ? 'current' : 'todo';
            },

            /**
             * Button label for moving to `next`. Approving the review moves the
             * request onto the bar's "Processing" stage, so that step is simply
             * "Process"; earlier steps keep their stage name.
             */
            advanceLabel(next) {
                return next.value === 'approved' ? 'Process' : 'Advance to ' + next.label;
            },

            isDone(i) { const p = this.prog(); return !p.isReturned && (i + 1) < p.displayPosition; },
            isCurrent(i) { const p = this.prog(); return !p.isReturned && (i + 1) === p.displayPosition; },
            nodeClass(i) { return this.isDone(i) ? 'done' : (this.isCurrent(i) ? 'now' : 'todo'); },

            cockpitHint() {
                if (this.active && this.active.can_advance === false) {
                    return 'You\'re not able to move this request forward — it\'s assigned to you but your role doesn\'t include the advance-documents permission. Ask an admin to reassign it or grant that permission.';
                }
                const p = this.prog();
                switch (p.primary.type) {
                    case 'approve': return 'This request is at Approved. Approve it to mark it Completed and move it to your History.';
                    case 'advance': return 'Move this request to the next stage, or return it to the citizen if something needs fixing.';
                    case 'resume': return 'This request was returned for revision. Resume it to continue working.';
                    case 'unhold': return 'This request is on hold and its SLA is paused. Resume to lift the hold and continue.';
                    default: return '';
                }
            },

            /** Update the active request (and its table row — same object) in place. */
            applyResult(newStatus, extra = {}) {
                if (!this.active) return;
                this.active.status = newStatus;
                this.active.status_label = this.labelFor(newStatus);
                if (newStatus !== 'pending') this.active.needs_triage = false;
                Object.assign(this.active, extra);
            },

            /**
             * PATCH a status endpoint and update in place — modal stays open.
             * Every call carries expected_status (optimistic concurrency: the
             * server refuses if someone else moved the document first) and
             * failures render inline via gateError, never alert().
             */
            patchStatus(path, extra = {}, data = {}) {
                if (!this.active || this.submitting) return Promise.resolve();
                this.submitting = true;
                this.gateError = '';
                // IMPORTANT: send JSON, never FormData — PHP does not parse
                // multipart bodies on PATCH, so FormData fields arrive empty.
                const payload = { expected_status: this.active.status, ...data };
                const opts = {
                    method: 'PATCH',
                    headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                };
                return fetch(`${this.openBase}/${this.active.id}/${path}`, opts)
                    .then((r) => r.json().then((d) => ({ ok: r.ok, d })))
                    .then(({ ok, d }) => {
                        this.submitting = false;
                        if (ok) return this.applyResult(d.status, extra);
                        // Laravel 422s carry per-field errors; show every unmet gate.
                        this.gateError = d.errors
                            ? Object.values(d.errors).flat().join(' ')
                            : (d.message || 'Could not update the status. Please try again.');
                    })
                    .catch(() => { this.submitting = false; this.gateError = 'Network error. Please try again.'; });
            },

            runPrimary() {
                switch (this.prog().primary.type) {
                    // Fewer clicks: the confirm panel exists to collect a note, so
                    // when this stage does not require one, advance straight away
                    // instead of asking the same person to confirm their own click.
                    case 'advance': return this.advanceNoteRequired() ? this.openAdvance() : this.confirmAdvance();
                    case 'approve': return this.approve();
                    case 'resume': return this.setStatus('in_progress');
                    case 'unhold': return this.patchStatus('unhold', { blocked_by: null, hold_reason: null, hold_until: null, status_before_hold: null });
                }
            },

            // ─── Advance: confirm panel with a transition note ───
            /**
             * A note is required when (a) →Approved: the review sign-off, or
             * (b) →In Review with nothing on file: the note becomes the work
             * record that satisfies the stage gate.
             */
            advanceNoteRequired() {
                if (!this.active) return false;
                if (this.active.status === 'in_review') return true;
                return this.active.status === 'in_progress' && !this.active.has_work_evidence;
            },
            advanceHint() {
                if (!this.active) return '';
                if (this.active.status === 'in_review') return 'This is the review sign-off — record what was checked and why it passes.';
                if (this.active.status === 'in_progress' && !this.active.has_work_evidence) return 'Nothing is on file for this request yet — your note becomes its work record.';
                return 'Moves the request to the next stage. Add a note for the record if useful.';
            },
            advanceTargetLabel() {
                const idx = this.stageIndex(this.active ? this.active.status : null);
                return idx >= 0 && this.flow[idx + 1] ? this.flow[idx + 1].label : '';
            },
            openAdvance() { this.advanceNote = ''; this.gateError = ''; this.advanceOpen = true; },
            /**
             * Applies the advance. Called from the confirm panel, and directly by
             * runPrimary() for stages that need no note — in which case there is
             * no panel state to reset beyond the (empty) note itself.
             */
            confirmAdvance() {
                if (this.advanceNoteRequired() && !this.advanceNote.trim()) return;
                const hadNote = !!this.advanceNote.trim();
                const data = hadNote ? { note: this.advanceNote.trim() } : {};
                this.advanceOpen = false;
                // A submitted note is stored as a staff work note on the file.
                return this.patchStatus('status/advance', hadNote ? { has_work_evidence: true } : {}, data)
                    .then(() => { this.advanceNote = ''; });
            },

            // ─── Move back: always carries a reason for the audit trail ───
            revertStatus() { this.revertReason = ''; this.gateError = ''; this.revertOpen = true; },
            confirmRevert() {
                if (!this.revertReason.trim()) return;
                const data = { reason: this.revertReason.trim() };
                this.revertOpen = false;
                return this.patchStatus('status/revert', {}, data).then(() => { this.revertReason = ''; });
            },

            setStatus(value, reason) {
                const data = { status: value };
                if (reason) data.reason = reason;
                return this.patchStatus('status', {}, data);
            },

            returnForRevision() {
                if (!this.returnReason.trim()) return;
                const reason = this.returnReason.trim();
                this.returnOpen = false;
                this.setStatus('returned', reason).then(() => { this.returnReason = ''; if (this.active) this.active.remarks = reason; });
            },

            putOnHold() {
                if (!this.holdReason.trim()) return;
                const prev = this.active ? this.active.status : null;
                const data = {
                    hold_reason: this.holdReason.trim(),
                    blocked_by: this.holdBlockedBy,
                };
                if (this.holdUntil) data.hold_until = this.holdUntil;
                const extra = {
                    blocked_by: this.holdBlockedBy,
                    hold_reason: this.holdReason.trim(),
                    hold_until: this.holdUntil || null,
                    status_before_hold: prev,
                };
                this.holdOpen = false;
                this.patchStatus('hold', extra, data).then(() => { this.holdReason = ''; this.holdUntil = ''; this.holdBlockedBy = 'citizen'; });
            },

            open(req) {
                this.active = req;
                this.qrOpen = false;
                this.side = null;
                this.allLogs = false;
                this.dateValue = '';
                this.draft = '';
                this.clearFile();

                // Land the calendar on the date already committed to, else on
                // the one the citizen asked for, else on this month.
                const anchor = this.parseDate(req?.claim_on)
                    || this.parseDate(req?.requested_claim_on)
                    || new Date();

                this.calYear = anchor.getFullYear();
                this.calMonth = anchor.getMonth();

                this.selectedStaff = '';
                this.denyOpen = false;
                this.denyReason = '';
                this.declineOpen = false;
                this.declineReason = '';
                this.revisionOpen = false;
                this.revisionReason = '';
                this.holdOpen = false;
                this.returnOpen = false;
                this.advanceOpen = false;
                this.advanceNote = '';
                this.revertOpen = false;
                this.revertReason = '';
                this.gateError = '';
            },

            openById(id) {
                const req = this.requests.find((r) => r.id === id);
                if (req) this.open(req);
            },

            /* ── Side panels ─────────────────────────────────────────────────
               Everything below came from the Look Up desk's own Alpine scope
               when the desk was folded into this modal and deleted; see
               ReviewModalMergeTest for what had to survive the move.
               ───────────────────────────────────────────────────────────── */

            /** Open one of the two side cards, or close it if already up. */
            toggleSide(which) {
                this.side = this.side === which ? null : which;

                if (this.side === 'messages') {
                    this.readMessages();
                    this.$nextTick(() => this.scrollThread());
                }
            },

            /** The claiming date the citizen sees on their tracking page. */
            saveDate() {
                if (!this.active || !this.dateValue) {
                    this.gateError = 'Pick the claiming date for the citizen.';

                    return;
                }

                this.submitting = true;
                this.gateError = '';

                fetch(`${this.openBase}/${this.active.id}/claim-date`, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': this.csrf(),
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ claim_date: this.dateValue }),
                }).then(async (r) => {
                    if (r.ok) {
                        this.afterSuccess();

                        return;
                    }

                    const data = await r.json().catch(() => ({}));
                    this.gateError = data.message || 'That date could not be saved.';
                    this.submitting = false;
                }).catch(() => {
                    this.gateError = 'That date could not be saved.';
                    this.submitting = false;
                });
            },

            /* ── Calendar ───────────────────────────────────────────────── */

            /** Local YYYY-MM-DD. toISOString() would shift the day by the UTC offset. */
            isoOf(date) {
                const pad = (n) => String(n).padStart(2, '0');

                return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
            },

            parseDate(text) {
                if (!text) return null;

                const parsed = new Date(text);

                return isNaN(parsed.getTime()) ? null : parsed;
            },

            monthLabel() {
                return new Date(this.calYear, this.calMonth, 1)
                    .toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
            },

            /** Jump the calendar back to this month. */
            calendarToToday() {
                const now = new Date();
                this.calYear = now.getFullYear();
                this.calMonth = now.getMonth();
            },

            /** "Friday, August 7, 2026" for the picked day. */
            pickedLabel() {
                const picked = this.parseDate(this.dateValue + 'T00:00:00');

                return picked ? picked.toLocaleDateString(undefined, { dateStyle: 'full' }) : '';
            },

            shiftMonth(delta) {
                const moved = new Date(this.calYear, this.calMonth + delta, 1);

                this.calYear = moved.getFullYear();
                this.calMonth = moved.getMonth();
            },

            /**
             * The month as weeks of seven days, padded out to whole weeks with
             * the neighbouring months' days — only as many rows as the month
             * actually spans, so a 5-week month does not draw an empty sixth.
             */
            calendarWeeks() {
                const first = new Date(this.calYear, this.calMonth, 1);
                const daysInMonth = new Date(this.calYear, this.calMonth + 1, 0).getDate();
                const weeks = Math.ceil((first.getDay() + daysInMonth) / 7);
                const today = this.isoOf(new Date());
                const out = [];

                for (let w = 0; w < weeks; w++) {
                    const row = [];

                    for (let d = 0; d < 7; d++) {
                        const day = new Date(this.calYear, this.calMonth, 1 - first.getDay() + (w * 7) + d);

                        row.push({
                            day: day.getDate(),
                            iso: this.isoOf(day),
                            label: day.toLocaleDateString(undefined, { dateStyle: 'long' }),
                            inMonth: day.getMonth() === this.calMonth,
                            sunday: day.getDay() === 0,
                            today: this.isoOf(day) === today,
                            // A claiming date in the past is not a commitment
                            // anyone can keep, so those days are not offerable.
                            past: this.isoOf(day) < today,
                        });
                    }

                    out.push(row);
                }

                return out;
            },

            /* ── Conversation ───────────────────────────────────────────── */

            scrollThread() {
                const thread = this.$refs.thread;

                if (thread) {
                    thread.scrollTop = thread.scrollHeight;
                }
            },

            /** Opening the thread is what clears the request's unread badge. */
            readMessages() {
                if (!this.active || !this.active.unread_messages) return;

                this.active.unread_messages = 0;

                fetch(`${this.openBase}/${this.active.id}/comments/read`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf(), Accept: 'application/json' },
                }).catch(() => {});
            },

            pickFile(event) {
                this.file = event.target.files[0] || null;
                this.gateError = '';
            },

            clearFile() {
                this.file = null;

                // The input only exists while the composer is rendered.
                if (this.$refs.attachment) {
                    this.$refs.attachment.value = '';
                }
            },

            sendMessage() {
                const body = this.draft.trim();

                if (!this.active || !body || this.sending) return;

                this.sending = true;
                this.gateError = '';

                // Multipart throughout, with or without a file: one path to
                // maintain, and the browser sets the boundary for us (so the
                // Content-Type header must NOT be set by hand here).
                const payload = new FormData();
                payload.append('body', body);
                payload.append('visibility', 'public');

                if (this.file) {
                    payload.append('attachment', this.file);
                }

                fetch(`${this.openBase}/${this.active.id}/comments`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf(), Accept: 'application/json' },
                    body: payload,
                }).then(async (r) => {
                    const data = await r.json().catch(() => ({}));

                    if (!r.ok) {
                        // 422 reports per-field messages; the file rules are
                        // the ones staff actually trip over.
                        this.gateError = (data.errors && Object.values(data.errors)[0][0])
                            || data.message
                            || 'That message could not be sent.';
                        this.sending = false;

                        return;
                    }

                    // Append rather than reload: the queue behind the modal has
                    // not changed, and a reload would close what staff are in.
                    this.active.messages = (this.active.messages || []).concat([data.comment]);
                    this.draft = '';
                    this.clearFile();
                    this.sending = false;
                    this.$nextTick(() => this.scrollThread());
                }).catch(() => {
                    this.gateError = 'That message could not be sent.';
                    this.sending = false;
                });
            },

            afterSuccess() {
                if (this.reloadUrl) {
                    window.location.href = this.reloadUrl;
                } else {
                    window.location.reload();
                }
            },

            /**
             * Supervisor resolutions (assign / deny) settle IN PLACE: the row
             * leaves the pending list reactively, the modal closes, and errors
             * render inline — no full page reload.
             */
            resolveActive() {
                const id = this.active ? this.active.id : null;
                this.close();
                if (id !== null) this.requests = this.requests.filter((r) => r.id !== id);
            },

            approve() {
                if (!this.active || this.submitting) return;
                if (this.mode === 'supervisor' && !this.selectedStaff) return;
                if (this.mode === 'staff' && this.active.status !== 'approved') return;

                let url, opts;
                const headers = { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' };

                if (this.mode === 'supervisor') {
                    const fd = new FormData();
                    fd.append('assigned_to', this.selectedStaff);
                    url = `${this.assignBase}/${this.active.id}/assign-approve`;
                    opts = { method: 'POST', headers, body: fd };
                } else {
                    url = `${this.completeBase}/${this.active.id}/review/complete`;
                    opts = { method: 'PATCH', headers };
                }

                this.submitting = true;
                fetch(url, opts).then((r) => {
                    this.submitting = false;
                    if (r.ok) {
                        // Supervisor: the request is assigned — drop it in place.
                        // Staff completing a review still reloads (KPIs/History move).
                        if (this.mode === 'supervisor') this.resolveActive();
                        else this.afterSuccess();
                        return;
                    }
                    r.json().then((d) => {
                        this.gateError = d.errors ? Object.values(d.errors).flat().join(' ') : (d.message || 'Could not complete the action. Please try again.');
                    }).catch(() => {
                        this.gateError = 'Could not complete the action. Please try again.';
                    });
                }).catch(() => {
                    this.submitting = false;
                    this.gateError = 'Network error. Please try again.';
                });
            },

            close() {
                // Esc steps back one layer: the QR viewer, then the side card,
                // then the modal itself.
                if (this.qrOpen) {
                    this.qrOpen = false;

                    return;
                }

                if (this.side) {
                    this.side = null;

                    return;
                }

                this.active = null;
                this.submitting = false;
                this.denyOpen = false;
                this.denyReason = '';
                this.declineOpen = false;
                this.declineReason = '';
                this.revisionOpen = false;
                this.revisionReason = '';
                this.holdOpen = false;
                this.returnOpen = false;
                this.advanceOpen = false;
                this.advanceNote = '';
                this.revertOpen = false;
                this.revertReason = '';
                this.gateError = '';
                this.allLogs = false;
                this.dateValue = '';
                this.draft = '';
                this.sending = false;
                this.clearFile();
            },

            postAction(url, opts) {
                this.submitting = true;
                return fetch(url, opts).then((r) => {
                    if (r.ok) {
                        this.afterSuccess();
                        return;
                    }
                    this.submitting = false;
                    return r.json().then((d) => {
                        alert(d.message || 'Could not complete the action. Please try again.');
                    }).catch(() => {
                        alert('Could not complete the action. Please try again.');
                    });
                }).catch(() => {
                    this.submitting = false;
                    alert('Network error. Please try again.');
                });
            },

            acceptAssignment() {
                if (!this.active || this.submitting || !this.active.needs_triage) return;
                this.submitting = true;
                // Accept in place so the staff member drops straight into the cockpit
                // (Pending → In Progress) without a page reload.
                fetch(`${this.openBase}/${this.active.id}/assignment/accept`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                }).then((r) => r.json().then((d) => ({ ok: r.ok, d })))
                    .then(({ ok, d }) => {
                        this.submitting = false;
                        if (ok) {
                            this.active.needs_triage = false;
                            this.applyResult(d.status || 'in_progress');
                        } else {
                            alert(d.message || 'Could not accept this assignment. Please try again.');
                        }
                    })
                    .catch(() => { this.submitting = false; alert('Network error. Please try again.'); });
            },

            declineAssignment() {
                if (!this.active || this.submitting) return;
                const fd = new FormData();
                if (this.declineReason.trim()) {
                    fd.append('reason', this.declineReason.trim());
                }
                this.postAction(`${this.openBase}/${this.active.id}/assignment/decline`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                    body: fd,
                });
            },

            requestRevision() {
                if (!this.active || this.submitting || !this.revisionReason.trim()) return;
                const fd = new FormData();
                fd.append('reason', this.revisionReason.trim());
                this.postAction(`${this.openBase}/${this.active.id}/assignment/revision`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                    body: fd,
                });
            },

            deny() {
                if (!this.active || this.submitting) return;

                const fd = new FormData();
                if (this.denyReason.trim()) {
                    fd.append('reason', this.denyReason.trim());
                }

                this.submitting = true;
                fetch(`${this.denyBase}/${this.active.id}/deny`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                    body: fd,
                }).then((r) => {
                    if (r.ok) {
                        // Denied requests leave the pending queue in place.
                        this.submitting = false;
                        this.resolveActive();
                        return;
                    }
                    this.submitting = false;
                    alert('Could not deny the request. Please try again.');
                }).catch(() => {
                    this.submitting = false;
                    alert('Network error. Please try again.');
                });
            },
        };
    };
</script>
@endonce
