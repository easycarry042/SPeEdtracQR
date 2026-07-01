{{--
    Shared Review modal. Driven by the surrounding Alpine `reviewPanel()` scope.
    Pass $mode = 'supervisor' (assign + approve) or 'staff' (approve = complete).
--}}
@php $mode = $mode ?? 'supervisor'; @endphp

<div x-show="active" x-cloak
     class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 px-4 py-8"
     @keydown.escape.window="close()" @click.self="close()">
    <div class="w-full max-w-2xl rounded-2xl border border-gray-200 bg-white shadow-xl"
         x-show="active" x-transition>
        <template x-if="active">
            <div>
                <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-4">
                    <div class="min-w-0">
                        <p class="font-mono text-lg font-extrabold text-emerald-950" x-text="active.tracking_number"></p>
                        <p class="mt-0.5 text-sm text-gray-600" x-text="active.document_type"></p>
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold"
                          :class="active.needs_triage ? 'bg-sky-100 text-sky-800' : 'bg-gray-100 text-gray-700'"
                          x-text="active.needs_triage ? 'New assignment' : active.status_label"></span>
                </div>

                <div class="max-h-[55vh] space-y-5 overflow-y-auto px-6 py-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Citizen</p>
                            <p class="mt-1 text-sm font-semibold text-gray-800" x-text="active.citizen_name || '—'"></p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Submitted</p>
                            <p class="mt-1 text-sm text-gray-800" x-text="active.submitted_at || '—'"></p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Email</p>
                            <p class="mt-1 text-sm text-gray-800" x-text="active.citizen_email || '—'"></p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Contact</p>
                            <p class="mt-1 text-sm text-gray-800" x-text="active.citizen_contact || '—'"></p>
                        </div>
                    </div>

                    <div x-show="active.purpose">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Purpose</p>
                        <p class="mt-1 text-sm text-gray-800" x-text="active.purpose"></p>
                    </div>
                    <div x-show="active.description">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Description</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-gray-800" x-text="active.description"></p>
                    </div>

                    <div x-show="active.attachments && active.attachments.length">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Attachments</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <template x-for="(att, i) in active.attachments" :key="i">
                                <a :href="att.url" target="_blank" rel="noopener"
                                   class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-lg bg-gray-50 ring-1 ring-gray-200 transition hover:ring-emerald-400">
                                    <template x-if="att.is_image">
                                        <img :src="att.url" alt="Attachment" class="h-full w-full object-cover">
                                    </template>
                                    <template x-if="!att.is_image">
                                        <span class="flex flex-col items-center gap-1 text-gray-500">
                                            <svg class="h-5 w-5" :class="att.ext === 'pdf' ? 'text-red-500' : 'text-emerald-600'" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5"/></svg>
                                            <span class="text-[9px] font-bold uppercase" x-text="att.ext"></span>
                                        </span>
                                    </template>
                                </a>
                            </template>
                        </div>
                    </div>

                    @if($mode === 'supervisor')
                        <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4">
                            <label class="block text-sm font-semibold text-emerald-900">Assign to Staff <span class="text-red-500">*</span></label>
                            <select x-model="selectedStaff"
                                    class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                                <option value="">Select a staff member…</option>
                                <template x-for="member in staff" :key="member.id">
                                    <option :value="member.id" x-text="member.name"></option>
                                </template>
                            </select>
                            <p class="mt-2 text-xs text-emerald-800/80">Approving assigns the request to the selected staff member. They must <strong>accept it on their Requests page</strong> before work begins.</p>
                        </div>
                    @else
                        <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-sm text-emerald-900">
                            <template x-if="active && active.needs_triage">
                                <div class="space-y-2">
                                    <p class="font-semibold text-emerald-950">Review this assignment before deciding.</p>
                                    <p>Check the citizen details and attachments above, then choose <strong>Accept</strong> to start work, <strong>Decline</strong> to send it back to the supervisor, or <strong>Request revision</strong> if the citizen must fix something first.</p>
                                    <p class="text-xs text-emerald-800/70">You can also <a :href="'/track/' + active.tracking_number" target="_blank" rel="noopener" class="font-semibold underline">open the full track page</a> for the complete timeline.</p>
                                </div>
                            </template>
                            <template x-if="active && !active.needs_triage && active.status === 'approved'">
                                <p>This request is at <strong>Approved</strong>. Use <strong>Approve</strong> below to mark it <strong>Completed</strong> and move it to your History.</p>
                            </template>
                            <template x-if="active && !active.needs_triage && active.status !== 'approved'">
                                <p>Advance this request on the <a :href="'/track/' + active.tracking_number" class="font-semibold underline">Track page</a> until it reaches <strong>Approved</strong>, then return here to approve it for completion.</p>
                            </template>
                        </div>
                    @endif
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
                    <button type="button" @click="close()"
                            class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                        <span x-text="active && active.needs_triage ? 'Close' : 'Cancel'"></span>
                    </button>

                    @if($mode === 'supervisor')
                        <button type="button" @click="denyOpen = true" :disabled="submitting"
                                class="rounded-xl border border-red-200 bg-red-50 px-5 py-2.5 text-sm font-semibold text-red-700 transition enabled:hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50">
                            Deny
                        </button>
                        <button type="button" @click="approve()" :disabled="!selectedStaff || submitting"
                                class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition enabled:hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50">
                            <span x-show="!submitting">Approve</span>
                            <span x-show="submitting">Approving…</span>
                        </button>
                    @else
                        <template x-if="active && active.needs_triage">
                            <div class="flex flex-wrap items-center justify-end gap-3">
                                <button type="button" @click="revisionOpen = true" :disabled="submitting"
                                        class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-2.5 text-sm font-semibold text-amber-800 transition enabled:hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-50">
                                    Request revision
                                </button>
                                <button type="button" @click="declineOpen = true" :disabled="submitting"
                                        class="rounded-xl border border-red-200 bg-red-50 px-5 py-2.5 text-sm font-semibold text-red-700 transition enabled:hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50">
                                    Decline
                                </button>
                                <button type="button" @click="acceptAssignment()" :disabled="submitting"
                                        class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition enabled:hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50">
                                    <span x-show="!submitting">Accept</span>
                                    <span x-show="submitting">Accepting…</span>
                                </button>
                            </div>
                        </template>
                        <template x-if="active && active.status === 'approved'">
                            <div>
                                <button type="button" @click="approve()" :disabled="submitting"
                                        class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition enabled:hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50">
                                    <span x-show="!submitting">Approve</span>
                                    <span x-show="submitting">Approving…</span>
                                </button>
                            </div>
                        </template>
                    @endif
                </div>

                @if($mode === 'supervisor')
                <div x-show="denyOpen" x-cloak
                     class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 px-4"
                     @keydown.escape.window="denyOpen = false" @click.self="denyOpen = false">
                    <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white shadow-xl" x-show="denyOpen" x-transition>
                        <div class="border-b border-gray-100 px-6 py-4">
                            <h3 class="text-base font-bold text-gray-900">Deny request</h3>
                            <p class="mt-0.5 text-sm text-gray-500">This rejects the request. You can add an optional reason for the record.</p>
                        </div>
                        <div class="px-6 py-5">
                            <label class="block text-sm font-semibold text-gray-700">Reason <span class="font-normal text-gray-400">(optional)</span></label>
                            <textarea x-model="denyReason" rows="4" maxlength="1000" placeholder="e.g. Incomplete requirements, duplicate request…"
                                      class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm shadow-sm focus:border-red-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-red-500/20"></textarea>
                        </div>
                        <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
                            <button type="button" @click="denyOpen = false" :disabled="submitting"
                                    class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 disabled:opacity-50">
                                Cancel
                            </button>
                            <button type="button" @click="deny()" :disabled="submitting"
                                    class="rounded-xl bg-red-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition enabled:hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50">
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
                    <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white shadow-xl" x-show="declineOpen" x-transition>
                        <div class="border-b border-gray-100 px-6 py-4">
                            <h3 class="text-base font-bold text-gray-900">Decline assignment</h3>
                            <p class="mt-0.5 text-sm text-gray-500">This returns the request to the supervisor queue. You can add an optional note.</p>
                        </div>
                        <div class="px-6 py-5">
                            <label class="block text-sm font-semibold text-gray-700">Note <span class="font-normal text-gray-400">(optional)</span></label>
                            <textarea x-model="declineReason" rows="3" maxlength="1000" placeholder="e.g. Not in my area of responsibility…"
                                      class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm shadow-sm focus:border-red-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-red-500/20"></textarea>
                        </div>
                        <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
                            <button type="button" @click="declineOpen = false" :disabled="submitting"
                                    class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 disabled:opacity-50">
                                Cancel
                            </button>
                            <button type="button" @click="declineAssignment()" :disabled="submitting"
                                    class="rounded-xl bg-red-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition enabled:hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50">
                                <span x-show="!submitting">Decline assignment</span>
                                <span x-show="submitting">Declining…</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div x-show="revisionOpen" x-cloak
                     class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 px-4"
                     @keydown.escape.window="revisionOpen = false" @click.self="revisionOpen = false">
                    <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white shadow-xl" x-show="revisionOpen" x-transition>
                        <div class="border-b border-gray-100 px-6 py-4">
                            <h3 class="text-base font-bold text-gray-900">Request revision</h3>
                            <p class="mt-0.5 text-sm text-gray-500">Tell the citizen what needs to be fixed. The request will be marked Returned / For Revision.</p>
                        </div>
                        <div class="px-6 py-5">
                            <label class="block text-sm font-semibold text-gray-700">What needs to be revised? <span class="text-red-500">*</span></label>
                            <textarea x-model="revisionReason" rows="4" maxlength="1000" required placeholder="e.g. Missing barangay clearance, illegible ID photo…"
                                      class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm shadow-sm focus:border-amber-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/20"></textarea>
                        </div>
                        <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
                            <button type="button" @click="revisionOpen = false" :disabled="submitting"
                                    class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 disabled:opacity-50">
                                Cancel
                            </button>
                            <button type="button" @click="requestRevision()" :disabled="submitting || !revisionReason.trim()"
                                    class="rounded-xl bg-amber-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition enabled:hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50">
                                <span x-show="!submitting">Send for revision</span>
                                <span x-show="submitting">Sending…</span>
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

            csrf() {
                return document.querySelector('meta[name=csrf-token]')?.content;
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
                if (this.mode === 'staff' && req.status === 'in_progress' && !req.needs_triage) {
                    this.markInReview(req);
                }
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

            markInReview(req) {
                fetch(`${this.openBase}/${req.id}/review/open`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrf(),
                        'Accept': 'application/json',
                    },
                }).then((r) => {
                    if (!r.ok) return;
                    req.status = 'in_review';
                    req.status_label = 'In Review';
                    if (this.active) this.active.status_label = 'In Review';
                }).catch(() => {});
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
                    if (r.ok) {
                        this.afterSuccess();
                        return;
                    }
                    this.submitting = false;
                    r.json().then((d) => {
                        alert(d.message || 'Could not complete the action. Please try again.');
                    }).catch(() => {
                        alert('Could not complete the action. Please try again.');
                    });
                }).catch(() => {
                    this.submitting = false;
                    alert('Network error. Please try again.');
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
                this.postAction(`${this.openBase}/${this.active.id}/assignment/accept`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf(), 'Accept': 'application/json' },
                });
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
                        this.afterSuccess();
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
