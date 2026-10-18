<x-app-layout title="Requests">
    {{-- Every citizen request in the city and where it stands. The same table,
         tabs, search, filter and Review modal as the supervisor dashboard, so
         one request reads the same on every screen — but read-only: the office
         assigns and the staff member advances. The modal offers Notify instead. --}}
    <div class="page-shell page-shell-loose">

        @if (session('status'))
            <div class="dm-callout dm-callout-green" role="status">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p>{{ session('status') }}</p>
            </div>
        @endif

        <section x-data="reviewPanel(@js([
            'requests' => $requests,
            'mode' => 'oversight',
            'staff' => [],
            'openBase' => url('/documents'),
        ]))">
            <div x-data="{
                     ...window.stFilterMixin(),
                     tab: 'open',
                     q: '',
                     page: 1,
                     perPage: 15,
                     tabs: [
                         { key: 'open', label: 'Open' },
                         { key: 'unassigned', label: 'Unassigned' },
                         { key: 'overdue', label: 'Overdue' },
                         { key: 'completed', label: 'Completed' },
                         { key: 'denied', label: 'Denied' },
                     ],
                     closed(req) { return req.status === 'completed' || req.status === 'denied'; },
                     inQueue(req, which) {
                         return {
                             open: ! this.closed(req),
                             unassigned: ! this.closed(req) && ! req.assigned_to,
                             overdue: !! req.over_sla,
                             completed: req.status === 'completed',
                             denied: req.status === 'denied',
                         }[which];
                     },
                     queueCount(which) {
                         return this.requests.filter((r) => this.inQueue(r, which)).length;
                     },
                     typeOptions() {
                         return this.uniqueTypes(this.requests.map((r) => r.document_type));
                     },
                     get matches() {
                         const needle = this.q.trim().toLowerCase();

                         return this.requests.filter((r) => this.inQueue(r, this.tab)
                             && this.passesFilter(r.document_type, r.submitted_on)
                             && (! needle || [r.citizen_name, r.tracking_number, r.document_type, r.department, r.assigned_staff]
                                 .some((field) => (field || '').toLowerCase().includes(needle))));
                     },
                     get pageCount() { return Math.max(1, Math.ceil(this.matches.length / this.perPage)); },
                     get total() { return this.matches.length; },
                     get current() { return Math.min(this.page, this.pageCount); },
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
                     go(page) { this.page = Math.min(Math.max(1, page), this.pageCount); },
                 }"
                 x-init="
                     {{-- ?open=SPD-… — a link from the command center, the
                          unrouted alert or a tracking page opens that request. --}}
                     const wanted = @js($openTracking);
                     const req = wanted ? requests.find((r) => r.tracking_number === wanted) : null;
                     if (req) {
                         if (closed(req)) { tab = req.status; }
                         $nextTick(() => open(req));
                         const url = new URL(window.location.href);
                         url.searchParams.delete('open');
                         history.replaceState(history.state, '', url);
                     }
                 ">

                <div class="st-toolbar mb-4 flex-wrap justify-between gap-3">
                    <div class="st-tabs" role="tablist" aria-label="Request queues">
                        <template x-for="t in tabs" :key="t.key">
                            <button type="button" role="tab" @click="tab = t.key; page = 1"
                                    :class="tab === t.key ? 'on' : ''" :aria-selected="tab === t.key">
                                <span x-text="t.label"></span> <span class="st-tab-count" x-text="queueCount(t.key)"></span>
                            </button>
                        </template>
                    </div>

                    <div class="flex min-w-0 flex-1 items-center justify-end gap-3">
                        <label for="requestSearch" class="sr-only">Search requests</label>
                        <div class="st-search">
                            <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                            <input id="requestSearch" type="search" x-model="q" @input="page = 1" data-kbd-search placeholder="Search name, tracking #, office or staff">
                        </div>

                        @include('partials.staff-table-filter', ['id' => 'adminRequestsFilter'])
                    </div>
                </div>

                <div class="st-card">
                    <div class="st-scroll">
                        <table class="st-table">
                            <thead>
                                <tr>
                                    <th>Request Type</th>
                                    <th>Tracking ID · requested by</th>
                                    <th>Office · handled by</th>
                                    <th>Filed</th>
                                    <th>Progress</th>
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
                                                    <img :src="window.requestTypeIcon(req.document_type)" alt="">
                                                </span>
                                                <div class="min-w-0">
                                                    <p class="st-name truncate" x-text="req.document_type"></p>
                                                    <span class="pill mt-1 inline-block" :class="pillClass(req)" x-text="req.status_label"></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap">
                                            <span class="mono block" x-text="req.tracking_number"></span>
                                            <span class="st-sub" x-text="req.citizen_name || '—'"></span>
                                        </td>
                                        <td>
                                            <span class="block" x-text="req.department || 'No office'"
                                                  :class="req.department ? '' : 'font-semibold text-status-amber'"></span>
                                            <span class="st-sub" x-text="req.assigned_staff || 'Not assigned yet'"></span>
                                        </td>
                                        <td class="mono whitespace-nowrap" x-text="req.date_short"></td>
                                        <td>
                                            {{-- The same four stages as the Review modal's rail. --}}
                                            <div class="ro-rail" role="img"
                                                 :aria-label="'Stage ' + req.rail_stage + ' of ' + railStages.length + ': ' + req.status_label">
                                                <template x-for="(stage, i) in railStages" :key="stage.label">
                                                    <span :class="i + 1 < req.rail_stage || req.status === 'completed' ? 'is-done' : (i + 1 === req.rail_stage ? 'is-now' : '')"></span>
                                                </template>
                                            </div>
                                            <span x-show="req.over_sla" x-cloak class="cc-tag cc-tag-late mt-1" x-text="'+' + req.over_sla + ' late'"></span>
                                        </td>
                                        <td>
                                            <button type="button" @click="open(req)" class="st-action">
                                                <img src="{{ asset('images/staff/icon-eye.svg') }}" alt="">
                                                View details
                                            </button>
                                        </td>
                                    </tr>
                                </template>

                                <tr x-show="matches.length === 0" class="st-empty">
                                    <td colspan="6">
                                        <p class="text-sm font-semibold text-ink" x-text="q || filterCount() ? 'No requests match' : 'Nothing here'"></p>
                                        <p class="mt-1 text-sm text-ink-soft" x-text="q || filterCount() ? 'Try another search, or loosen the filter.' : 'Citizen requests in this state will appear here.'"></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    @include('partials.staff-table-pager', ['label' => 'Request pages'])
                </div>
            </div>

            @include('partials.review-modal', ['mode' => 'oversight'])
        </section>

        @include('admin.partials.notify-dialog')
    </div>

    @include('partials.request-type-icon-script')
</x-app-layout>
