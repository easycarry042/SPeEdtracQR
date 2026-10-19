<x-app-layout title="Dashboard">
    @if($dept)
        {{-- Department context rides the title row rather than a full-width band:
             it is orientation, not content. --}}
        <x-slot name="pageActions">
            <span class="hidden min-w-0 items-center gap-2 rounded-full border border-hairline-strong bg-green-wash px-3 py-1 sm:inline-flex">
                <span class="truncate text-sm font-bold text-green-deep">
                    {{ $dept->name }} <span class="font-medium text-green">({{ $dept->code }})</span>
                </span>
            </span>
            @if($completionRate !== null)
                <span class="hidden shrink-0 items-center gap-1.5 text-sm md:inline-flex">
                    <span class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Completion</span>
                    <span class="font-bold text-green-deep">{{ $completionRate }}%</span>
                </span>
            @endif
        </x-slot>
    @endif

    <div class="page-shell page-shell-loose">

        {{-- Headline counts, on the same gradient ramp as the staff dashboard —
             darkest carries the oldest work, lightest the finished work.

             The four the frame names walk the queue from one end to the other:
             nobody has started it (Pending), nobody owns it (Unassigned), it is
             moving (In Progress), it is done (Completed). At Risk is not one of
             them — risk is not a stage, it cuts across all of them — and the
             named at-risk list now sits on each staff member's own dashboard,
             where the person who can advance the request will see it. --}}
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <x-staff-stat-tile label="Pending" :value="$pendingOnly" tone="deep" icon="hourglass" />
            <x-staff-stat-tile label="Unassigned" :value="$unassigned" tone="mid" icon="people" />
            <x-staff-stat-tile label="In Progress" :value="$inProgress" tone="bright" icon="clock" />
            <x-staff-stat-tile label="Completed" :value="$completed" tone="brightest" icon="check" />
        </div>

        {{-- No SLA routing slip: a supervisor assigns, the staff member
             advances, so the at-risk warning is on each staff member's own
             dashboard. Internal requests are on the Internal tab.

             One line from Reports instead of its charts: the queue below is
             this page's job, so the numbers stay a glance and a link. --}}
        @if($glance)
            @php
                $glanceOverdue = $glance['overdue']['value'];
                $glanceAvg = \App\Support\Duration::days($glance['avg_processing']['value']);
            @endphp
            <a href="{{ route('reports.index') }}" class="glance-strip" data-soft-nav-link>
                <span class="glance-label">Last 30 days</span>
                <span><b>{{ number_format($glance['total']['value']) }}</b> filed</span>
                <span><b>{{ number_format($glance['completed']['value']) }}</b> completed</span>
                <span>avg. <b>{{ $glanceAvg }}</b> to complete</span>
                <span @class(['is-late' => $glanceOverdue > 0])><b>{{ number_format($glanceOverdue) }}</b> overdue now</span>
                <span class="glance-link">Open reports →</span>
            </a>
        @endif

        {{-- ── Staff workload ──────────────────────────────────────────────────
             The department head's primary lens: who is carrying how much.
             ───────────────────────────────────────────────────────────────── --}}
        @if($staffWorkload->isNotEmpty())
        @php $maxActive = max(1, (int) $staffWorkload->max('active_count')); @endphp
        <section>
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <h2 class="st-section-title">Staff workload</h2>
                <span class="pill p-muted">Assignment distribution</span>
            </div>

            <div class="st-card">
                <div class="st-scroll">
                    <table class="st-table">
                        <thead>
                            <tr>
                                <th>Staff member</th>
                                <th>Active</th>
                                <th>Completed</th>
                                <th class="min-w-[170px]">Load</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($staffWorkload as $member)
                                <tr>
                                    <td>
                                        <a href="{{ route('staff.profile', $member) }}" class="st-name hover:underline">{{ $member->name }}</a>
                                    </td>
                                    <td class="font-semibold text-green-deep">{{ $member->active_count }}</td>
                                    <td class="text-ink-soft">{{ $member->completed_count }}</td>
                                    <td>
                                        <div class="h-2 w-32 overflow-hidden rounded-full bg-[#e6ece8]">
                                            <div class="h-2 rounded-full {{ $member->active_count > 0 ? 'bg-green' : 'bg-[#cdd9d2]' }}"
                                                 style="width:{{ (int) round(($member->active_count / $maxActive) * 100) }}%"></div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach

                            @if($staffWorkload->every(fn ($m) => $m->active_count === 0 && $m->completed_count === 0))
                                <tr class="st-empty">
                                    <td colspan="4">No assignments yet — assign the pending requests below.</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        @endif

        {{-- ── The office's requests ───────────────────────────────────────────
             Two queues on one table, as the Look Up desk drew them: Unassigned
             is the triage list (approve = assign), In Progress is the work in
             flight. The second tab is why the desk could be retired — without
             it a request vanished from this screen the moment it was assigned
             and did not reappear until it was completed.

             Both queues ride in one payload, so the Review modal resolves a row
             from either tab and the table can never disagree with it. Searched,
             filtered and paged in the browser for the same reason.
             ───────────────────────────────────────────────────────────────── --}}
        <section x-data="reviewPanel(@js([
            'requests' => $pendingPayload->concat($activePayload),
            'mode' => 'supervisor',
            'staff' => $assignableStaff,
            'assignBase' => url('/documents'),
            'denyBase' => url('/documents'),
            'openBase' => url('/documents'),
        ]))">
            <div x-data="{
                     ...window.stFilterMixin(),
                     tab: 'pending',
                     q: '',
                     page: 1,
                     perPage: 10,
                     typeOptions() {
                         return this.uniqueTypes(this.requests.map((r) => r.document_type));
                     },
                     /* Unassigned vs in flight, decided by the row itself so the
                        two tabs cannot drift apart from the modal's payload. */
                     inQueue(req, which) {
                         return which === 'pending' ? ! req.assigned_to : !! req.assigned_to;
                     },
                     queueCount(which) {
                         return this.requests.filter((r) => this.inQueue(r, which)).length;
                     },
                     get matches() {
                         const needle = this.q.trim().toLowerCase();

                         return this.requests.filter((r) => this.inQueue(r, this.tab)
                             && this.passesFilter(r.document_type, r.submitted_on)
                             && (! needle || [r.citizen_name, r.tracking_number, r.document_type, r.department]
                                 .some((field) => (field || '').toLowerCase().includes(needle))));
                     },
                     get pageCount() {
                         return Math.max(1, Math.ceil(this.matches.length / this.perPage));
                     },
                     get total() {
                         return this.matches.length;
                     },
                     get current() {
                         // Searching can shrink the list out from under the open page.
                         return Math.min(this.page, this.pageCount);
                     },
                     get pageItems() {
                         return this.matches.slice((this.current - 1) * this.perPage, this.current * this.perPage);
                     },
                     get pageList() {
                         const last = this.pageCount;

                         if (last <= 6) {
                             return Array.from({ length: last }, (_, i) => i + 1);
                         }

                         if (this.current > 5) {
                             return [1, 'gap', this.current - 1, this.current, this.current + 1, 'gap', last]
                                 .filter((p) => p === 'gap' || (p >= 1 &amp;&amp; p <= last));
                         }

                         return [1, 2, 3, 4, 5, 'gap', last];
                     },
                     go(page) {
                         this.page = Math.min(Math.max(1, page), this.pageCount);
                     },
                 }">

                {{-- Title and search share one line, as the frame draws them:
                     the heading sits left, the search and filter right. They
                     wrap to two lines on a narrow screen rather than squeezing
                     the search field down to nothing. --}}
                <div class="st-toolbar mb-4 flex-wrap justify-between gap-3">
                    <div class="st-tabs" role="tablist" aria-label="Request queues">
                        <button type="button" role="tab" @click="tab = 'pending'; page = 1"
                                :class="tab === 'pending' ? 'on' : ''" :aria-selected="tab === 'pending'">
                            Unassigned <span class="st-tab-count" x-text="queueCount('pending')"></span>
                        </button>
                        <button type="button" role="tab" @click="tab = 'active'; page = 1"
                                :class="tab === 'active' ? 'on' : ''" :aria-selected="tab === 'active'">
                            In Progress <span class="st-tab-count" x-text="queueCount('active')"></span>
                        </button>
                    </div>

                    <div class="flex min-w-0 flex-1 items-center justify-end gap-3">
                        <label for="requestSearch" class="sr-only">Search requests</label>
                        <div class="st-search">
                            <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                            <input id="requestSearch" type="search" x-model="q" @input="page = 1" data-kbd-search placeholder="Search">
                        </div>

                        @include('partials.staff-table-filter', ['id' => 'supervisorFilter'])
                    </div>
                </div>

                <div class="st-card">
                    <div class="st-scroll">
                        <table class="st-table">
                            <thead>
                                <tr>
                                    <th>Request Type</th>
                                    <th>Tracking ID</th>
                                    {{-- A department head's queue is their own office by
                                         definition, so the frame leaves this column out.
                                         An org-wide admin reads the same table across
                                         every office and would be lost without it. --}}
                                    @if($spansDepartments)
                                        <th>Department</th>
                                    @endif
                                    <th>Date</th>
                                    <th>Requested by</th>
                                    {{-- Only meaningful on the In Progress tab: on the
                                         Unassigned one it would be a column of dashes. --}}
                                    <th x-show="tab === 'active'">Handled by</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="req in pageItems" :key="req.id">
                                    <tr>
                                        <td>
                                            <div class="st-type">
                                                <span class="st-disc" aria-hidden="true">
                                                    <img src="{{ asset('images/staff/row-disc.svg') }}" alt="">
                                                    {{-- Alpine renders these rows, so the glyph is chosen in the
                                                         browser from the same keyword rules as <x-request-type-icon>. --}}
                                                    <img :src="window.requestTypeIcon(req.document_type)" alt="">
                                                </span>
                                                <div class="min-w-0">
                                                    <p class="st-name truncate" x-text="req.document_type"></p>
                                                    {{-- No pill on the Unassigned tab: every row there is
                                                         Pending, so the badge would be a column of the
                                                         same word. On the In Progress tab the stage is
                                                         the whole point of the row. --}}
                                                    <span x-show="tab === 'active'" class="pill mt-1 inline-block"
                                                          :class="pillClass(req)" x-text="req.status_label"></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="mono" x-text="req.tracking_number"></span></td>
                                        @if($spansDepartments)
                                            {{-- Department carries its own code (the THED ID) — one
                                                 column instead of two saying the same thing. --}}
                                            <td>
                                                <span x-text="req.department || 'Not yet routed'"></span>
                                                <span class="ml-1 mono text-xs text-ink-soft" x-show="req.thed_id" x-text="'· ' + req.thed_id"></span>
                                            </td>
                                        @endif
                                        <td class="mono" x-text="req.submitted_at"></td>
                                        <td x-text="req.citizen_name || '—'"></td>
                                        <td x-show="tab === 'active'" x-text="req.assigned_staff || '—'"></td>
                                        <td>
                                            <button type="button" @click="open(req)" class="st-action">
                                                <img src="{{ asset('images/staff/icon-eye.svg') }}" alt="">
                                                <span x-text="tab === 'active' ? 'View details' : 'Review'"></span>
                                            </button>
                                        </td>
                                    </tr>
                                </template>

                                <tr x-show="matches.length === 0" class="st-empty">
                                    <td :colspan="{{ $spansDepartments ? 6 : 5 }} + (tab === 'active' ? 1 : 0)">
                                        <div class="mx-auto flex max-w-sm flex-col items-center">
                                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-wash text-green-deep">
                                                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                                            </div>
                                            <p class="mt-3 text-sm font-semibold text-ink"
                                               x-text="q || filterCount() ? 'No requests match' : (tab === 'active' ? 'Nothing in progress' : 'No unassigned requests')"></p>
                                            <p class="mt-1 text-sm text-ink-soft"
                                               x-text="q || filterCount() ? 'Try another search, or loosen the filter.' : (tab === 'active' ? 'Requests your staff are working on appear here.' : 'New citizen submissions awaiting approval will appear here.')"></p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    @include('partials.staff-table-pager', ['label' => 'Request pages'])
                </div>
            </div>

            @include('partials.review-modal', ['mode' => 'supervisor'])
        </section>
    </div>

    @include('partials.request-type-icon-script')
</x-app-layout>
