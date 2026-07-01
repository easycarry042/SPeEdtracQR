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
                    <span class="shrink-0 rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700" x-text="active.status_label"></span>
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
                            <p class="mt-2 text-xs text-emerald-800/80">Approving assigns the request and moves it to <strong>In Progress</strong>.</p>
                        </div>
                    @else
                        <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-sm text-emerald-900">
                            Approving marks this request <strong>Completed</strong> and moves it to your History.
                        </div>
                    @endif
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
                    <button type="button" @click="close()"
                            class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                        Cancel
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
                        <button type="button" @click="approve()" :disabled="submitting"
                                class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition enabled:hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50">
                            <span x-show="!submitting">Approve</span>
                            <span x-show="submitting">Approving…</span>
                        </button>
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

            csrf() {
                return document.querySelector('meta[name=csrf-token]')?.content;
            },

            open(req) {
                this.active = req;
                this.selectedStaff = '';
                this.denyOpen = false;
                this.denyReason = '';
                // Staff: opening the review transitions the request to "In Review".
                if (this.mode === 'staff' && req.status !== 'in_review') {
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
                    alert('Could not complete the action. Please try again.');
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
