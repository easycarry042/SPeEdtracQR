<x-app-layout fixed-height title="Dashboard">
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

        {{-- The table filters and paginates in the browser: the whole assigned
             set is already in the Alpine payload that drives the review modal,
             so a round trip per page would fetch what the page already holds. --}}
        <section class="mt-6 flex min-h-0 flex-1 flex-col"
                 x-data="{
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
                             && (! needle || [r.citizen_name, r.tracking_number, r.document_type]
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

            {{-- Search --}}
            <div class="st-toolbar mb-4">
                <label for="requestSearch" class="sr-only">Search requests</label>
                <div class="st-search">
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                    <input id="requestSearch" type="search" x-model="q" @input="page = 1" placeholder="Search">
                </div>

                @include('partials.staff-table-filter', ['id' => 'dashFilter'])
            </div>

            <div class="st-card">
                <div class="st-scroll">
                    <table class="st-table">
                        <thead>
                            <tr>
                                <th>Request Type</th>
                                <th>Tracking ID</th>
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
                                    <td class="mono" x-text="req.submitted_at"></td>
                                    <td x-text="req.citizen_name || '—'"></td>
                                    <td>
                                        <button type="button" @click="open(req)" class="st-action"
                                                :class="req.needs_triage && 'text-green-deep'">
                                            <img src="{{ asset('images/staff/icon-eye.svg') }}" alt="">
                                            <span x-text="req.needs_triage ? 'Review assignment' : 'Review'"></span>
                                        </button>
                                    </td>
                                </tr>
                            </template>

                            <tr x-show="matches.length === 0" class="st-empty">
                                <td colspan="5">
                                    <div class="mx-auto flex max-w-sm flex-col items-center">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-wash text-green-deep">
                                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                                        </div>
                                        <p class="mt-3 text-sm font-semibold text-ink"
                                           x-text="q || filterCount() ? 'No requests match' : 'No assigned requests'"></p>
                                        <p class="mt-1 text-sm text-ink-soft"
                                           x-text="q || filterCount() ? 'Try another search, or loosen the filter.' : 'Requests your supervisor assigns to you will appear here.'"></p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @include('partials.staff-table-pager', ['label' => 'Requests pages'])
            </div>
        </section>

        {{-- Same keyword rules as the <x-request-type-icon> component, which
             serves the server-rendered lists. Kept in step by hand: the table
             here is built in the browser from the Alpine payload. --}}
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

        @include('partials.review-modal', ['mode' => 'staff'])
    </div>
</x-app-layout>
