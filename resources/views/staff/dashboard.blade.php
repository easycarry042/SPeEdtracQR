{{-- Not fixed-height. This page stacks three blocks now — the tiles, the
     at-risk list and the work queue — and the fixed-height shell gives the
     viewport exactly once with no page scrollbar, so the queue's own 320px
     floor pushed the bottom of the page somewhere nothing could reach it.
     The supervisor dashboard has always scrolled; this one matches it. --}}
<x-app-layout title="Dashboard">
    <div class="page-shell page-shell-loose"
         x-data="reviewPanel(@js([
            'requests' => $requestPayload,
            'mode' => 'staff',
            'openBase' => url('/documents'),
            'completeBase' => url('/documents'),
            'flow' => $flow,
         ]))">

        {{-- Headline counts --}}
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <x-staff-stat-tile label="Pending" :value="$pendingCount" tone="deep" icon="hourglass" />
            <x-staff-stat-tile label="In Progress" :value="$inProgressCount" tone="mid" icon="clock" />
            <x-staff-stat-tile label="Completed" :value="$completedCount" tone="bright" icon="check" />
        </div>

        {{-- ── At risk ─────────────────────────────────────────────────────────
             The assignee's own work running out of stage SLA, soonest first.
             Above the queue because it is the part of the queue that cannot
             wait; the amber belongs to the SLA bar and the heading count, not
             to the furniture around them.

             The tracking number opens the same Review modal the queue below
             uses — these rows are a subset of the same payload, so the row can
             be acted on where it is read rather than hunted for again.
             ───────────────────────────────────────────────────────────────── --}}
        @if($atRisk->isNotEmpty())
        {{-- .st-card-compact keeps this card sized to its rows and caps its own
             scroll, so "Show all" on a long list does not bury the work queue
             under it. --}}
        <section class="mt-6" x-data="{ showAll: false }">
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <h2 class="st-section-title">At-risk requests</h2>
                <span class="pill p-amber">{{ $atRiskCount }} need{{ $atRiskCount === 1 ? 's' : '' }} attention</span>
            </div>

            <div class="st-card st-card-compact">
                <div class="st-scroll">
                    <table class="st-table">
                        <thead>
                            <tr>
                                <th>Tracking ID</th>
                                <th>Request Type</th>
                                <th>Requested by</th>
                                <th class="min-w-[248px]">Stage</th>
                                <th class="min-w-[170px]">SLA usage</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($atRisk as $doc)
                                @php
                                    $budget = (int) $doc->sla_budget_hours;
                                    $used = \App\Support\SlaWatch::percentUsed($doc);
                                    $overdue = (bool) $doc->sla_overdue;
                                    $barColor = $overdue ? 'bg-status-red' : ($used >= 75 ? 'bg-[#d8920f]' : 'bg-green');
                                    $remaining = (int) $doc->sla_remaining_hours;
                                    $overBy = (int) $doc->sla_elapsed_hours - $budget;
                                @endphp
                                <tr @if($loop->index >= 5) x-show="showAll" x-cloak @endif>
                                    <td>
                                        <button type="button" @click="openById({{ $doc->id }})"
                                                class="mono font-semibold text-green-deep hover:underline">
                                            {{ $doc->tracking_number }}
                                        </button>
                                    </td>
                                    <td>{{ $doc->document_type }}</td>
                                    <td>{{ $doc->citizen_name ?? '—' }}</td>
                                    <td>
                                        {{-- The same four-stage rail Look Up draws, so the stage a
                                             staff member reads here is the stage the citizen is
                                             shown on their tracking page. --}}
                                        <x-stage-bar :document="$doc" size="sm" />
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 w-24 shrink-0 overflow-hidden rounded-full bg-[#e6ece8]">
                                                <div class="h-2 rounded-full {{ $barColor }} transition-[width] duration-500" style="width:{{ $used }}%"></div>
                                            </div>
                                            @if($overdue)
                                                <span class="whitespace-nowrap text-xs font-semibold text-status-red">+{{ $overBy }}h over</span>
                                            @else
                                                <span class="whitespace-nowrap text-xs font-semibold text-[#8a5a08]">~{{ $remaining }}h left</span>
                                            @endif
                                        </div>
                                        <p class="mt-0.5 text-xs text-ink-soft">{{ $budget }}h SLA · {{ $used }}% used</p>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($atRisk->count() > 5)
                    <div class="st-pager justify-center">
                        <button type="button" @click="showAll = ! showAll" class="st-page-more">
                            <span x-show="! showAll">Show all {{ $atRisk->count() }} requests</span>
                            <span x-show="showAll" x-cloak>Show the top 5 only</span>
                        </button>
                    </div>
                @endif
            </div>
        </section>
        @endif

        {{-- Two tabs. Active filters and paginates in the browser: the whole
             assigned set is already in the Alpine payload that drives the review
             modal. Completed is every request this staff member has finished,
             so it is fetched a page at a time instead (completed-tab-script). --}}
        @include('partials.completed-tab-script')
        <section class="mt-6"
                 x-data="{
                     ...window.stFilterMixin(),
                     ...window.stCompletedMixin(@js(route('history.data'))),
                     tab: new URLSearchParams(location.search).get('tab') === 'done' ? 'done' : 'active',
                     q: '',
                     page: 1,
                     perPage: 10,
                     init() {
                         this.watchDone();
                     },
                     typeOptions() {
                         return this.tab === 'done'
                             ? this.done.types
                             : this.uniqueTypes(this.requests.map((r) => r.document_type));
                     },
                     get matches() {
                         const needle = this.q.trim().toLowerCase();

                         return this.requests.filter((r) => this.passesFilter(r.document_type, r.submitted_on)
                             && (! needle || [r.citizen_name, r.tracking_number, r.document_type]
                                 .some((field) => (field || '').toLowerCase().includes(needle))));
                     },
                     get pageCount() {
                         return this.tab === 'done' ? this.done.lastPage : Math.max(1, Math.ceil(this.matches.length / this.perPage));
                     },
                     get total() {
                         return this.tab === 'done' ? this.done.total : this.matches.length;
                     },
                     get current() {
                         // Searching can shrink the list out from under the open page.
                         return this.tab === 'done' ? this.done.page : Math.min(this.page, this.pageCount);
                     },
                     get pageItems() {
                         return this.tab === 'done'
                             ? this.done.items
                             : this.matches.slice((this.current - 1) * this.perPage, this.current * this.perPage);
                     },
                     get pageList() {
                         if (this.tab === 'done') {
                             return this.donePageList();
                         }

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
                         const target = Math.min(Math.max(1, page), this.pageCount);

                         if (this.tab === 'done') {
                             this.loadDone(target);

                             return;
                         }

                         this.page = target;
                     },

                 }">

            {{-- Tabs, then search --}}
            <div class="st-toolbar mb-4 flex-wrap justify-between gap-3">
                <div class="st-tabs" role="tablist" aria-label="Your requests">
                    <button type="button" role="tab" @click="tab = 'active'; page = 1"
                            :class="tab === 'active' ? 'on' : ''" :aria-selected="tab === 'active'">
                        Active <span class="st-tab-count" x-text="requests.length"></span>
                    </button>
                    <button type="button" role="tab" @click="tab = 'done'"
                            :class="tab === 'done' ? 'on' : ''" :aria-selected="tab === 'done'">
                        Completed <span class="st-tab-count">{{ $completedCount }}</span>
                    </button>
                </div>

                <div class="flex min-w-0 flex-1 items-center justify-end gap-3">
                    <label for="requestSearch" class="sr-only">Search requests</label>
                    <div class="st-search">
                        <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                        <input id="requestSearch" type="search" x-model="q" @input="page = 1" placeholder="Search">
                    </div>

                    @include('partials.staff-table-filter', ['id' => 'dashFilter'])
                </div>
            </div>

            <div class="st-card">
                <div class="st-scroll">
                    <table class="st-table">
                        <thead>
                            <tr>
                                <th>Request Type</th>
                                <th>Tracking ID</th>
                                <th x-text="tab === 'done' ? 'Completed' : 'Date'">Date</th>
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
                                                {{-- Triage state stays on the row: it is the
                                                     difference between "read this now" and "later". --}}
                                                <span class="pill mt-1 inline-block" :class="pillClass(req)"
                                                      x-text="req.needs_triage ? 'New assignment' : req.status_label"></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="mono" x-text="req.tracking_number"></span>
                                        {{-- The citizen is waiting on an answer. --}}
                                        <span x-show="req.unread_messages > 0" x-cloak
                                              class="ml-1 inline-flex items-center gap-1 rounded-full bg-[#e7f0fb] px-2 py-0.5 text-[12.5px] font-bold text-[#1d4e89]"
                                              :title="req.unread_messages + ' unread message(s) from the citizen'">
                                            <span class="h-1.5 w-1.5 rounded-full bg-[#1d4e89]"></span>
                                            <span x-text="req.unread_messages"></span> new
                                        </span>
                                    </td>
                                    <td class="mono" x-text="tab === 'done' ? req.completed_at : req.submitted_at"></td>
                                    <td x-text="req.citizen_name || '—'"></td>
                                    <td>
                                        <button type="button" @click="open(req)" class="st-action"
                                                :class="req.needs_triage && 'text-green-deep'">
                                            <img src="{{ asset('images/staff/icon-eye.svg') }}" alt="">
                                            <span x-text="tab === 'done' ? 'View details' : (req.needs_triage ? 'Review assignment' : 'Review')"></span>
                                        </button>
                                    </td>
                                </tr>
                            </template>

                            <tr x-show="tab === 'done' && done.loading && ! done.loaded" class="st-empty">
                                <td colspan="5"><p class="text-sm text-ink-soft">Loading completed requests…</p></td>
                            </tr>

                            <tr x-show="tab === 'done' ? (done.loaded || done.failed) && pageItems.length === 0 : matches.length === 0" class="st-empty">
                                <td colspan="5">
                                    <div class="mx-auto flex max-w-sm flex-col items-center">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-wash text-green-deep">
                                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                                        </div>
                                        <p class="mt-3 text-sm font-semibold text-ink"
                                           x-text="tab === 'done' && done.failed ? 'Completed requests could not be loaded' : (q || filterCount() ? 'No requests match' : (tab === 'done' ? 'Nothing completed yet' : 'No assigned requests'))"></p>
                                        <p class="mt-1 text-sm text-ink-soft"
                                           x-text="tab === 'done' && done.failed ? 'Check your connection and open the tab again.' : (q || filterCount() ? 'Try another search, or loosen the filter.' : (tab === 'done' ? 'Requests you finish appear here, newest first.' : 'Requests your supervisor assigns to you will appear here.'))"></p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @include('partials.staff-table-pager', ['label' => 'Requests pages'])
            </div>
        </section>

        @include('partials.request-type-icon-script')

        @include('partials.review-modal', ['mode' => 'staff'])
    </div>
</x-app-layout>
