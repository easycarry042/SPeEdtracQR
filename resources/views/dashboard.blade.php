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
             darkest carries the oldest work, lightest the finished work. At Risk
             breaks the ramp on purpose: amber is what this system uses for
             "watch this", and it must not read as one more shade of done. --}}
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <x-staff-stat-tile label="Total Request" :value="$totalRequests" tone="deep" icon="none" />
            <x-staff-stat-tile label="Pending" :value="$pendingRequest" tone="mid" icon="hourglass" />
            <x-staff-stat-tile label="Completed" :value="$completed" tone="brightest" icon="check" />
            <x-staff-stat-tile label="At Risk" :value="$atRiskCount"
                               :tone="$atRiskCount > 0 ? 'amber' : 'bright'" icon="clock" />
        </div>

        @if(($internalAwaiting ?? 0) > 0)
            <a href="{{ route('requests.index') }}"
               class="flex items-center justify-between gap-4 rounded-2xl border border-hairline-strong bg-green-wash px-6 py-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-center gap-4">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-green-deep text-on-green">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0-4-4m4 4-4 4M16 17H4m0 0 4 4m-4-4 4-4"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-bold text-green-deep">
                            {{ $internalAwaiting }} internal request{{ $internalAwaiting !== 1 ? 's' : '' }} awaiting your office
                        </p>
                        <p class="text-xs text-ink-soft">Approve, return, or deny them on the Internal Requests page.</p>
                    </div>
                </div>
                <span class="text-sm font-semibold text-green">Open inbox →</span>
            </a>
        @endif

        @if($slip)
            <x-routing-slip :slip="$slip" />
        @endif

        {{-- ── At risk ─────────────────────────────────────────────────────────
             Same .st-card table as everywhere else; the amber belongs to the SLA
             bar and the heading count, not to the furniture around them.
             ───────────────────────────────────────────────────────────────── --}}
        @if($atRiskDocuments->isNotEmpty())
        <section x-data="{ showAll: false }">
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <h2 class="st-section-title">At-risk documents</h2>
                <span class="pill p-amber">{{ $atRiskCount }} need{{ $atRiskCount === 1 ? 's' : '' }} attention</span>
            </div>

            <div class="st-card">
                <div class="st-scroll">
                    <table class="st-table">
                        <thead>
                            <tr>
                                <th>Tracking ID</th>
                                <th>Type</th>
                                <th>Citizen</th>
                                <th>Stage</th>
                                <th class="min-w-[170px]">SLA usage</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($atRiskDocuments as $doc)
                                @php
                                    $rowIndex = $loop->index;
                                    $slaHours = $doc->statusEnum()->slaHours() ?? 0;
                                    $elapsed = $doc->sla_elapsed_hours ?? 0;
                                    $pct = $slaHours > 0 ? min(round(($elapsed / $slaHours) * 100), 100) : 0;
                                    $overdue = $doc->sla_overdue ?? false;
                                    $barColor = $overdue ? 'bg-status-red' : ($pct >= 75 ? 'bg-[#d8920f]' : 'bg-green');
                                    $remaining = $slaHours > 0 ? round($slaHours - $elapsed) : 0;
                                    $overBy = $slaHours > 0 ? round($elapsed - $slaHours) : 0;
                                @endphp
                                <tr @if($rowIndex >= 5) x-show="showAll" x-cloak @endif>
                                    <td>
                                        <a href="{{ route('track.show', $doc->tracking_number) }}" class="mono font-semibold text-green-deep hover:underline">{{ $doc->tracking_number }}</a>
                                    </td>
                                    <td>{{ $doc->document_type }}</td>
                                    <td>{{ $doc->citizen_name ?? '—' }}</td>
                                    <td><span class="pill {{ $overdue ? 'p-red' : 'p-amber' }}">{{ $doc->statusEnum()->label() }}</span></td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 w-24 shrink-0 overflow-hidden rounded-full bg-[#e6ece8]">
                                                <div class="h-2 rounded-full {{ $barColor }} transition-[width] duration-500" style="width:{{ $pct }}%"></div>
                                            </div>
                                            @if($overdue)
                                                <span class="whitespace-nowrap text-xs font-semibold text-status-red">+{{ $overBy }}h over</span>
                                            @else
                                                <span class="whitespace-nowrap text-xs font-semibold text-[#8a5a08]">~{{ $remaining }}h left</span>
                                            @endif
                                        </div>
                                        <p class="mt-0.5 text-xs text-ink-soft">{{ $slaHours }}h SLA · {{ $pct }}% used</p>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($atRiskDocuments->count() > 5)
                    <div class="st-pager justify-center">
                        <button type="button" @click="showAll = ! showAll" class="st-page-more">
                            <span x-show="! showAll">Show all {{ $atRiskDocuments->count() }} documents</span>
                            <span x-show="showAll" x-cloak>Show the top 5 only</span>
                        </button>
                    </div>
                @endif
            </div>
        </section>
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

        {{-- ── Pending requests ────────────────────────────────────────────────
             The same table the staff dashboard draws: searched, filtered and
             paged in the browser, since the whole queue already rides in the
             Alpine payload that drives the review modal.
             ───────────────────────────────────────────────────────────────── --}}
        <section x-data="reviewPanel(@js([
            'requests' => $pendingPayload,
            'mode' => 'supervisor',
            'staff' => $assignableStaff,
            'assignBase' => url('/documents'),
            'denyBase' => url('/documents'),
        ]))">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 class="st-section-title">Pending requests</h2>
            </div>

            <div x-data="{
                     ...window.stFilterMixin(),
                     q: '',
                     page: 1,
                     perPage: 10,
                     typeOptions() {
                         return this.uniqueTypes(this.requests.map((r) => r.document_type));
                     },
                     get matches() {
                         const needle = this.q.trim().toLowerCase();

                         return this.requests.filter((r) => this.passesFilter(r.document_type, r.submitted_on)
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

                <div class="st-toolbar mb-4">
                    <label for="requestSearch" class="sr-only">Search requests</label>
                    <div class="st-search">
                        <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                        <input id="requestSearch" type="search" x-model="q" @input="page = 1" data-kbd-search placeholder="Search">
                    </div>

                    @include('partials.staff-table-filter', ['id' => 'supervisorFilter'])
                </div>

                <div class="st-card">
                    <div class="st-scroll">
                        <table class="st-table">
                            <thead>
                                <tr>
                                    <th>Request Type</th>
                                    <th>Tracking ID</th>
                                    <th>Department</th>
                                    <th>Date</th>
                                    <th>Requested by</th>
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
                                                    <span class="pill p-muted mt-1 inline-block">Pending</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="mono" x-text="req.tracking_number"></span></td>
                                        {{-- Department carries its own code (the THED ID) — one
                                             column instead of two saying the same thing. --}}
                                        <td>
                                            <span x-text="req.department || 'Not yet routed'"></span>
                                            <span class="ml-1 mono text-xs text-ink-soft" x-show="req.thed_id" x-text="'· ' + req.thed_id"></span>
                                        </td>
                                        <td class="mono" x-text="req.submitted_at"></td>
                                        <td x-text="req.citizen_name || '—'"></td>
                                        <td>
                                            <button type="button" @click="open(req)" class="st-action">
                                                <img src="{{ asset('images/staff/icon-eye.svg') }}" alt="">
                                                <span>Review</span>
                                            </button>
                                        </td>
                                    </tr>
                                </template>

                                <tr x-show="matches.length === 0" class="st-empty">
                                    <td colspan="6">
                                        <div class="mx-auto flex max-w-sm flex-col items-center">
                                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-wash text-green-deep">
                                                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                                            </div>
                                            <p class="mt-3 text-sm font-semibold text-ink"
                                               x-text="q || filterCount() ? 'No requests match' : 'No pending requests'"></p>
                                            <p class="mt-1 text-sm text-ink-soft"
                                               x-text="q || filterCount() ? 'Try another search, or loosen the filter.' : 'New citizen submissions awaiting approval will appear here.'"></p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    @include('partials.staff-table-pager', ['label' => 'Pending request pages'])
                </div>
            </div>

            @include('partials.review-modal', ['mode' => 'supervisor'])
        </section>
    </div>

    {{-- Same keyword rules as the <x-request-type-icon> component, which serves
         the server-rendered lists. Kept in step by hand: the table above is
         built in the browser from the Alpine payload. --}}
    <script>
        window.REQUEST_TYPE_ICON_BASE = @json(asset('images/request-types'));

        window.requestTypeIcon = function (type) {
            const needle = (type || '').toLowerCase();
            const rules = [
                [['lei', 'flower', 'wreath'], 'color-triangle.svg'],
                [['plaza', 'hall', 'venue', 'court', 'gym', 'facility', 'building'], 'office-building.svg'],
                [['flag'], 'flag.png'],
                [['banner', 'tarpaulin', 'spider', 'streamer'], 'web.png'],
            ];

            for (const rule of rules) {
                if (rule[0].some(function (keyword) { return needle.includes(keyword); })) {
                    return window.REQUEST_TYPE_ICON_BASE + '/' + rule[1];
                }
            }

            return window.REQUEST_TYPE_ICON_BASE + '/task-list-pen.svg';
        };
    </script>
</x-app-layout>
