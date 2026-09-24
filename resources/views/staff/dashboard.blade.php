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
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-green-deep" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m21 21-4.3-4.3"/></svg>
                    </span>
                    <input id="requestSearch" type="search" x-model="q" @input="page = 1"
                           placeholder="Search"
                           class="h-12 w-full rounded-full border border-hairline-strong bg-paper pl-12 pr-4 text-sm text-ink shadow-sm transition placeholder:text-ink-soft focus:border-green focus:outline-none focus:ring-4 focus:ring-green/15">
                </div>

                {{-- Clears the search: the only filter this table has. --}}
                <button type="button" @click="q = ''; page = 1"
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-hairline-strong bg-paper text-green-deep shadow-sm transition hover:bg-green-wash focus:outline-none focus-visible:ring-2 focus-visible:ring-green"
                        title="Clear search" aria-label="Clear search">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5h18l-7 8v6l-4 2v-8L3 5z"/></svg>
                </button>
            </div>

            <div class="overflow-hidden rounded-2xl border border-hairline bg-paper shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left">
                        <thead>
                            <tr class="border-b border-hairline text-sm font-semibold text-ink-soft">
                                <th class="px-6 py-4">Request Type</th>
                                <th class="px-6 py-4">Tracking ID</th>
                                <th class="px-6 py-4">Date</th>
                                <th class="px-6 py-4">Requested by</th>
                                <th class="px-6 py-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="req in pageItems" :key="req.id">
                                <tr class="border-b border-hairline/70 transition last:border-0 hover:bg-green-wash/60">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-green-deep text-on-green" aria-hidden="true">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                            </span>
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-ink" x-text="req.document_type"></p>
                                                {{-- Triage state stays on the row: it is the
                                                     difference between "read this now" and "later". --}}
                                                <span class="pill mt-1 inline-block" :class="pillClass(req)"
                                                      x-text="req.needs_triage ? 'New assignment' : req.status_label"></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="mono text-sm text-ink-soft" x-text="req.tracking_number"></span>
                                        {{-- The citizen is waiting on an answer. --}}
                                        <span x-show="req.unread_messages > 0" x-cloak
                                              class="ml-1 inline-flex items-center gap-1 rounded-full bg-[#e7f0fb] px-2 py-0.5 text-[10px] font-bold text-[#1d4e89]"
                                              :title="req.unread_messages + ' unread message(s) from the citizen'">
                                            <span class="h-1.5 w-1.5 rounded-full bg-[#1d4e89]"></span>
                                            <span x-text="req.unread_messages"></span> new
                                        </span>
                                    </td>
                                    <td class="mono px-6 py-4 text-sm text-ink-soft" x-text="req.submitted_at"></td>
                                    <td class="px-6 py-4 text-sm text-ink-soft" x-text="req.citizen_name || '—'"></td>
                                    <td class="px-6 py-4">
                                        <button type="button" @click="open(req)"
                                                class="inline-flex items-center gap-2 text-sm font-semibold transition"
                                                :class="req.needs_triage ? 'text-green-deep' : 'text-green hover:text-green-deep'">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.46 12C3.73 7.94 7.52 5 12 5s8.27 2.94 9.54 7c-1.27 4.06-5.06 7-9.54 7s-8.27-2.94-9.54-7z"/></svg>
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
                            class="flex h-8 w-8 items-center justify-center rounded-full border border-hairline-strong text-green-deep transition enabled:hover:bg-green-wash disabled:opacity-40"
                            aria-label="Previous page">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>

                    <template x-for="(entry, i) in pageList" :key="i">
                        <span>
                            <span x-show="entry === 'gap'" class="px-1 text-ink-soft" aria-hidden="true">…</span>
                            <button x-show="entry !== 'gap'" type="button" @click="go(entry)"
                                    :aria-current="entry === current ? 'page' : null"
                                    class="h-8 min-w-[2rem] rounded-full px-2 text-sm font-semibold transition"
                                    :class="entry === current ? 'bg-green-deep text-on-green' : 'text-ink-soft hover:bg-green-wash'"
                                    x-text="entry"></button>
                        </span>
                    </template>

                    <button type="button" @click="go(current + 1)" :disabled="current === pageCount"
                            class="flex h-8 w-8 items-center justify-center rounded-full border border-hairline-strong text-green-deep transition enabled:hover:bg-green-wash disabled:opacity-40"
                            aria-label="Next page">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </nav>
            </div>
        </section>

        @include('partials.review-modal', ['mode' => 'staff'])
    </div>
</x-app-layout>
