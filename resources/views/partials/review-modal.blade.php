{{--
    Shared Review modal. Driven by the surrounding Alpine `reviewPanel()` scope.
    Pass $mode = 'supervisor' (assign + approve) or 'staff' (approve = complete).
--}}
@php $mode = $mode ?? 'supervisor'; @endphp

<div x-show="active" x-cloak
     class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 px-4 py-8"
     @keydown.escape.window="close()" @click.self="close()">
    <div class="w-full max-w-2xl rounded-2xl border border-hairline bg-paper shadow-xl"
         x-show="active" x-transition>
        <template x-if="active">
            <div>
                <div class="flex items-start justify-between gap-4 border-b border-hairline px-6 py-4">
                    <div class="min-w-0">
                        <p class="mono text-lg font-extrabold text-green-deep" x-text="active.tracking_number"></p>
                        <p class="mt-0.5 text-sm text-ink-soft" x-text="active.document_type"></p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <span x-show="active.unread_messages > 0" x-cloak
                              class="inline-flex items-center gap-1 rounded-full bg-[#e7f0fb] px-2.5 py-0.5 text-[11px] font-bold text-[#1d4e89]">
                            <span x-text="active.unread_messages"></span> new message<span x-show="active.unread_messages > 1">s</span>
                        </span>
                        <span class="pill"
                              :class="active.needs_triage ? 'p-amber' : 'p-muted'"
                              x-text="active.needs_triage ? 'New assignment' : active.status_label"></span>
                    </div>
                </div>

                <div class="max-h-[55vh] space-y-5 overflow-y-auto px-6 py-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Citizen</p>
                            <p class="mt-1 text-sm font-semibold text-ink" x-text="active.citizen_name || '—'"></p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Submitted</p>
                            <p class="mt-1 text-sm text-ink" x-text="active.submitted_at || '—'"></p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Email</p>
                            <p class="mt-1 text-sm text-ink" x-text="active.citizen_email || '—'"></p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Contact</p>
                            <p class="mt-1 text-sm text-ink" x-text="active.citizen_contact || '—'"></p>
                        </div>
                    </div>

                    <div x-show="active.purpose">
                        <p class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Purpose</p>
                        <p class="mt-1 text-sm text-ink" x-text="active.purpose"></p>
                    </div>
                    <div x-show="active.description">
                        <p class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Description</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-ink" x-text="active.description"></p>
                    </div>

                    <div x-show="active.attachments && active.attachments.length">
                        <p class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Attachments</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <template x-for="(att, i) in active.attachments" :key="i">
                                <a :href="att.url" target="_blank" rel="noopener"
                                   class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-lg bg-green-wash ring-1 ring-hairline transition hover:ring-green-bright">
                                    <template x-if="att.is_image">
                                        <img :src="att.url" alt="Attachment" class="h-full w-full object-cover">
                                    </template>
                                    <template x-if="!att.is_image">
                                        <span class="flex flex-col items-center gap-1 text-ink-soft">
                                            <svg class="h-5 w-5" :class="att.ext === 'pdf' ? 'text-status-red' : 'text-green'" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5"/></svg>
                                            <span class="text-[9px] font-bold uppercase" x-text="att.ext"></span>
                                        </span>
                                    </template>
                                </a>
                            </template>
                        </div>
                    </div>

                    {{-- Requirement uploads: the citizen's evidence for each listed
                         requirement, each row linking to the authorized file stream. --}}
                    <div x-show="active.requirements && active.requirements.length">
                        <p class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Requirements for this request</p>
                        <ul class="mt-2 space-y-2">
                            <template x-for="(req, i) in active.requirements" :key="i">
                                <li class="flex items-center gap-3 rounded-[10px] border border-hairline bg-paper px-3 py-2">
                                    <template x-if="req.has_file">
                                        <a :href="req.url" target="_blank" rel="noopener"
                                           class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-green-wash ring-1 ring-hairline transition hover:ring-green-bright">
                                            <template x-if="req.is_image">
                                                <img :src="req.url" alt="" class="h-full w-full object-cover">
                                            </template>
                                            <template x-if="!req.is_image">
                                                <span class="flex flex-col items-center gap-0.5 text-ink-soft">
                                                    <svg class="h-4 w-4" :class="req.ext === 'pdf' ? 'text-status-red' : 'text-green'" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5"/></svg>
                                                    <span class="text-[8px] font-bold uppercase" x-text="req.ext"></span>
                                                </span>
                                            </template>
                                        </a>
                                    </template>
                                    <template x-if="!req.has_file">
                                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-hairline/40 text-ink-soft">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                                        </span>
                                    </template>

                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-ink" x-text="req.label"></p>
                                        <p class="text-xs text-ink-soft">
                                            <span x-show="req.is_mandatory" class="font-semibold text-status-red">Required</span>
                                            <span x-show="!req.is_mandatory">Optional</span>
                                            <span x-show="!req.has_file"> · not uploaded</span>
                                        </p>
                                    </div>

                                    <a x-show="req.has_file" :href="req.url" target="_blank" rel="noopener"
                                       class="shrink-0 text-xs font-semibold text-green hover:underline">View</a>
                                </li>
                            </template>
                        </ul>
                    </div>

                    @if($mode === 'supervisor')
                        <div class="rounded-[10px] border border-hairline-strong bg-green-wash/40 p-4">
                            <label class="block text-sm font-semibold text-green-deep">Assign to Staff <span class="text-status-red">*</span></label>
                            <select x-model="selectedStaff"
                                    class="mt-2 w-full rounded-lg border border-hairline-strong bg-paper px-3 py-2.5 text-sm shadow-sm focus:border-green focus:outline-none focus:ring-2 focus:ring-green/20">
                                <option value="">Select a staff member…</option>
                                <template x-for="member in staff" :key="member.id">
                                    <option :value="member.id" x-text="member.name"></option>
                                </template>
                            </select>
                            <p class="mt-2 text-xs text-ink-soft">Approving assigns the request to the selected staff member. They must <strong class="text-ink">accept it on their Requests page</strong> before work begins.</p>
                        </div>
                    @else
                        {{-- Triage: this assignment is still awaiting Accept / Decline / Revision. --}}
                        <template x-if="active && active.needs_triage">
                            <div class="rounded-[10px] border border-hairline-strong bg-green-wash/40 p-4 text-sm text-ink space-y-2">
                                <p class="font-semibold text-green-deep">Review this assignment before deciding.</p>
                                <p>Check the citizen details and attachments above, then choose <strong>Accept</strong> to start work, <strong>Decline</strong> to send it back to the supervisor, or <strong>Request revision</strong> if the citizen must fix something first.</p>
                                <p class="text-xs text-ink-soft">You can also <a :href="'/track/' + active.tracking_number" target="_blank" rel="noopener" class="font-semibold underline">open the full track page</a> for the complete timeline.</p>
                            </div>
                        </template>

                        {{-- Cockpit: once accepted, the whole status lifecycle is driven here. --}}
                        <template x-if="active && !active.needs_triage">
                            <div class="space-y-4">
                                {{-- Returned side-state banner --}}
                                <template x-if="prog().isReturned">
                                    <div class="step-returned" style="width:100%;align-items:flex-start;">
                                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" style="margin-top:1px;flex:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v6h6M3 13a9 9 0 1 0 3-7.7L3 8"/></svg>
                                        <div>
                                            <span>Returned for revision</span>
                                            <template x-if="active.remarks"><div style="font-weight:400;margin-top:2px;" x-text="active.remarks"></div></template>
                                        </div>
                                    </div>
                                </template>

                                {{-- On-hold side-state banner --}}
                                <template x-if="prog().isHeld">
                                    <div class="step-hold" style="width:100%;">
                                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6M14 9v6M5 5h14v14H5z"/></svg>
                                        <div>
                                            <span style="font-weight:700;">On hold</span>
                                            <template x-if="active.blocked_by"><span style="opacity:.85;"> · waiting on <span x-text="active.blocked_by"></span></span></template>
                                            <template x-if="active.hold_until"><span style="opacity:.85;"> · until <span x-text="active.hold_until"></span></span></template>
                                            <template x-if="active.hold_reason"><div style="font-weight:400;margin-top:2px;" x-text="active.hold_reason"></div></template>
                                        </div>
                                    </div>
                                </template>

                                {{-- Stepper — identical vocabulary to the public/track view --}}
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Status progress</p>
                                    <div class="steps" style="margin:10px 2px 4px;">
                                        <template x-for="(s, i) in flow" :key="s.value">
                                            <div style="display:contents">
                                                <div class="stp">
                                                    <div class="node" :class="nodeClass(i)">
                                                        <template x-if="isDone(i)"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5 9-10"/></svg></template>
                                                        <template x-if="!isDone(i)"><span x-text="i + 1"></span></template>
                                                    </div>
                                                    <div class="cap" :class="isCurrent(i) ? 'cur' : ''" x-text="s.label"></div>
                                                </div>
                                                <div class="seg" :class="isDone(i) ? 'done' : ''" x-show="i < flow.length - 1"></div>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <p class="text-sm text-ink-soft" x-text="cockpitHint()"></p>

                            </div>
                        </template>
                    @endif
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3 border-t border-hairline px-6 py-4">
                    {{-- Stage-gate / concurrency failures surface here, inline — no alert(). --}}
                    <p x-show="gateError" x-cloak x-text="gateError" role="alert"
                       class="w-full rounded-lg border border-status-red-wash bg-status-red-wash px-3 py-2 text-sm font-semibold text-status-red"></p>

                    <button type="button" @click="close()" class="cr-btn !px-5 !py-2.5 !text-sm">
                        <span x-text="active && active.needs_triage ? 'Close' : 'Cancel'"></span>
                    </button>

                    @if($mode === 'supervisor')
                        <button type="button" @click="denyOpen = true" :disabled="submitting"
                                class="cr-btn cr-btn-danger !px-5 !py-2.5 !text-sm disabled:cursor-not-allowed disabled:opacity-50">
                            Deny
                        </button>
                        <button type="button" @click="approve()" :disabled="!selectedStaff || submitting"
                                class="cr-btn cr-btn-primary !px-5 !py-2.5 !text-sm disabled:cursor-not-allowed disabled:opacity-50">
                            <span x-show="!submitting">Approve</span>
                            <span x-show="submitting">Approving…</span>
                        </button>
                    @else
                        {{-- Triage decision --}}
                        <template x-if="active && active.needs_triage">
                            <div class="flex flex-wrap items-center justify-end gap-3">
                                <button type="button" @click="revisionOpen = true" :disabled="submitting"
                                        class="cr-btn !border-status-amber-wash !bg-status-amber-wash !text-status-amber !px-5 !py-2.5 !text-sm disabled:cursor-not-allowed disabled:opacity-50">
                                    Request revision
                                </button>
                                <button type="button" @click="declineOpen = true" :disabled="submitting"
                                        class="cr-btn cr-btn-danger !px-5 !py-2.5 !text-sm disabled:cursor-not-allowed disabled:opacity-50">
                                    Decline
                                </button>
                                <button type="button" @click="acceptAssignment()" :disabled="submitting"
                                        class="cr-btn cr-btn-primary !px-5 !py-2.5 !text-sm disabled:cursor-not-allowed disabled:opacity-50">
                                    <span x-show="!submitting">Accept</span>
                                    <span x-show="submitting">Accepting…</span>
                                </button>
                            </div>
                        </template>

                        {{-- Working states: one primary Next action + secondary actions under More --}}
                        <template x-if="active && !active.needs_triage && prog().primary.type !== 'none'">
                            <div class="flex flex-wrap items-center justify-end gap-3">
                                <div class="relative" x-show="prog().canRevert || prog().canReturn || prog().canHold">
                                    <button type="button" @click="moreOpen = !moreOpen" :disabled="submitting || active.can_advance === false"
                                            class="cr-btn !px-4 !py-2.5 !text-sm disabled:cursor-not-allowed disabled:opacity-50">More ▾</button>
                                    <div x-show="moreOpen" x-cloak x-transition.opacity
                                         @click.outside="moreOpen = false" @keydown.escape.window="moreOpen = false"
                                         class="absolute bottom-full right-0 z-[70] mb-2 w-60 rounded-xl border border-hairline bg-paper p-1.5 shadow-xl">
                                        <button type="button" x-show="prog().canRevert" @click="moreOpen = false; revertStatus()"
                                                class="block w-full rounded-lg px-3 py-2 text-left text-sm text-ink hover:bg-green-wash">← Move back a stage</button>
                                        <button type="button" x-show="prog().canReturn" @click="moreOpen = false; returnOpen = true"
                                                class="block w-full rounded-lg px-3 py-2 text-left text-sm text-status-amber hover:bg-status-amber-wash">Return for revision</button>
                                        <button type="button" x-show="prog().canHold" @click="moreOpen = false; holdOpen = true"
                                                class="block w-full rounded-lg px-3 py-2 text-left text-sm text-ink hover:bg-green-wash">Put on hold</button>
                                    </div>
                                </div>
                                <button type="button" @click="runPrimary()" :disabled="submitting || active.can_advance === false"
                                        :title="active.can_advance === false ? 'You don\'t have permission to advance this document' : ''"
                                        class="cr-btn cr-btn-primary !px-5 !py-2.5 !text-sm disabled:cursor-not-allowed disabled:opacity-50">
                                    <span x-show="!submitting" x-text="prog().primary.label"></span>
                                    <span x-show="submitting">Working…</span>
                                </button>
                            </div>
                        </template>
                    @endif
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
                            <h3 class="text-base font-bold text-ink">Advance to <span x-text="advanceTargetLabel()"></span></h3>
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
</div>

@once
<script>
    window.reviewPanel = function (cfg) {
        return {
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
            selectedStaff: '',
            submitting: false,
            denyOpen: false,
            denyReason: '',
            declineOpen: false,
            declineReason: '',
            revisionOpen: false,
            revisionReason: '',
            moreOpen: false,
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
                else if (idx >= 0 && idx < this.flow.length - 2) primary = { type: 'advance', label: 'Advance to ' + this.flow[idx + 1].label };
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
                    case 'advance': return 'Click the button to move this request to the next stage. Secondary actions are under More.';
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
                this.selectedStaff = '';
                this.denyOpen = false;
                this.denyReason = '';
                this.declineOpen = false;
                this.declineReason = '';
                this.revisionOpen = false;
                this.revisionReason = '';
                this.moreOpen = false;
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
                this.active = null;
                this.submitting = false;
                this.denyOpen = false;
                this.denyReason = '';
                this.declineOpen = false;
                this.declineReason = '';
                this.revisionOpen = false;
                this.revisionReason = '';
                this.moreOpen = false;
                this.holdOpen = false;
                this.returnOpen = false;
                this.advanceOpen = false;
                this.advanceNote = '';
                this.revertOpen = false;
                this.revertReason = '';
                this.gateError = '';
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
