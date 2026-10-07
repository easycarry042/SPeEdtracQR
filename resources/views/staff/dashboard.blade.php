<x-app-layout>
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
        <section class="mt-6"
                 x-data="{
                     q: '',
                     page: 1,
                     perPage: 5,
                     get matches() {
                         const needle = this.q.trim().toLowerCase();

                         if (! needle) {
                             return this.requests;
                         }

                         return this.requests.filter((r) => [r.citizen_name, r.tracking_number, r.document_type]
                             .some((field) => (field || '').toLowerCase().includes(needle)));
                     },
                     get pageCount() {
                         return Math.max(1, Math.ceil(this.matches.length / this.perPage));
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
            <div class="mb-4 flex items-center justify-end gap-3">
                <label for="requestSearch" class="sr-only">Search requests</label>
                <div class="relative w-full max-w-sm">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4" aria-hidden="true">
                        <img src="{{ asset('images/staff/icon-search.svg') }}" alt="" class="h-6 w-6">
                    </span>
                    <input id="requestSearch" type="search" x-model="q" @input="page = 1"
                           placeholder="Search"
                           class="h-[54px] w-full rounded-full border border-green/40 bg-paper pl-[52px] pr-4 text-[18px] text-ink transition placeholder:text-ink-soft focus:border-green focus:outline-none focus:ring-4 focus:ring-green/15">
                </div>

                {{-- Clears the search: the only filter this table has. --}}
                <button type="button" @click="q = ''; page = 1"
                        class="flex h-[54px] w-[54px] shrink-0 items-center justify-center rounded-[14px] border border-green/40 bg-paper text-green-deep transition hover:bg-green-wash focus:outline-none focus-visible:ring-2 focus-visible:ring-green"
                        title="Clear search" aria-label="Clear search">
                    <img src="{{ asset('images/staff/icon-filter.svg') }}" alt="" class="h-6 w-6">
                </button>
            </div>

            <div class="overflow-hidden rounded-[20px] border border-hairline bg-paper shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left">
                        <thead>
                            <tr class="border-b border-hairline text-[16px] font-bold text-green-deep">
                                <th class="px-6 py-5">Request Type</th>
                                <th class="px-6 py-5">Tracking ID</th>
                                <th class="px-6 py-5">Date</th>
                                <th class="px-6 py-5">Requested by</th>
                                <th class="px-6 py-5">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="req in pageItems" :key="req.id">
                                <tr class="border-b border-hairline/70 transition last:border-0 hover:bg-green-wash/60">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <span class="relative flex h-[60px] w-[60px] shrink-0 items-center justify-center" aria-hidden="true">
                                                <img src="{{ asset('images/staff/row-disc.svg') }}" alt="" class="absolute inset-0 h-full w-full">
                                                {{-- Alpine renders these rows, so the glyph is chosen in the
                                                     browser from the same keyword rules as <x-request-type-icon>. --}}
                                                <img class="relative h-[30px] w-[30px] object-contain"
                                                     :src="window.requestTypeIcon(req.document_type)" alt="">
                                            </span>
                                            <div class="min-w-0">
                                                <p class="truncate text-[17px] font-semibold text-ink" x-text="req.document_type"></p>
                                                {{-- Triage state stays on the row: it is the
                                                     difference between "read this now" and "later". --}}
                                                <span class="pill mt-1 inline-block" :class="pillClass(req)"
                                                      x-text="req.needs_triage ? 'New assignment' : req.status_label"></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="mono text-[16px] text-ink-soft" x-text="req.tracking_number"></span>
                                        {{-- The citizen is waiting on an answer. --}}
                                        <span x-show="req.unread_messages > 0" x-cloak
                                              class="ml-1 inline-flex items-center gap-1 rounded-full bg-[#e7f0fb] px-2 py-0.5 text-[10px] font-bold text-[#1d4e89]"
                                              :title="req.unread_messages + ' unread message(s) from the citizen'">
                                            <span class="h-1.5 w-1.5 rounded-full bg-[#1d4e89]"></span>
                                            <span x-text="req.unread_messages"></span> new
                                        </span>
                                    </td>
                                    <td class="mono px-6 py-4 text-[16px] text-ink-soft" x-text="req.submitted_at"></td>
                                    <td class="px-6 py-4 text-[16px] text-ink-soft" x-text="req.citizen_name || '—'"></td>
                                    <td class="px-6 py-4">
                                        <button type="button" @click="open(req)"
                                                class="inline-flex items-center gap-2 text-[16px] font-semibold transition"
                                                :class="req.needs_triage ? 'text-green-deep' : 'text-green hover:text-green-deep'">
                                            <img src="{{ asset('images/staff/icon-eye.svg') }}" alt="" class="h-6 w-[24px]">
                                            <span x-text="req.needs_triage ? 'Review assignment' : 'Review'"></span>
                                        </button>
                                    </td>
                                </tr>
                            </template>

                            <tr x-show="matches.length === 0">
                                <td colspan="5" class="px-6 py-10 text-center">
                                    <div class="mx-auto flex max-w-sm flex-col items-center">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-wash text-green-deep">
                                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                                        </div>
                                        <p class="mt-3 text-sm font-semibold text-ink"
                                           x-text="q ? 'No requests match that search' : 'No assigned requests'"></p>
                                        <p class="mt-1 text-sm text-ink-soft"
                                           x-text="q ? 'Try a tracking number, a request type, or the requester name.' : 'Requests your supervisor assigns to you will appear here.'"></p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Pager. Hidden on a single page: a lone "1" is noise. --}}
                <nav x-show="pageCount > 1" x-cloak aria-label="Requests pages"
                     class="flex items-center justify-end gap-1.5 border-t border-hairline px-6 py-4">
                    <button type="button" @click="go(current - 1)" :disabled="current === 1"
                            class="flex h-[30px] w-[30px] items-center justify-center rounded-full border border-green/40 bg-green-100 text-green-deep transition enabled:hover:bg-green-200 disabled:opacity-40"
                            aria-label="Previous page">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>

                    <template x-for="(entry, i) in pageList" :key="i">
                        <span>
                            <span x-show="entry === 'gap'" class="px-1 text-ink-soft" aria-hidden="true">…</span>
                            <button x-show="entry !== 'gap'" type="button" @click="go(entry)"
                                    :aria-current="entry === current ? 'page' : null"
                                    class="h-[30px] min-w-[30px] rounded-[8px] px-2 text-[17px] font-bold transition"
                                    :class="entry === current ? 'border border-green/50 bg-green-100 text-green-deep' : 'text-ink-soft hover:bg-green-wash'"
                                    x-text="entry"></button>
                        </span>
                    </template>

                    <button type="button" @click="go(current + 1)" :disabled="current === pageCount"
                            class="flex h-[30px] w-[30px] items-center justify-center rounded-full border border-green/40 bg-green-100 text-green-deep transition enabled:hover:bg-green-200 disabled:opacity-40"
                            aria-label="Next page">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </nav>
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
