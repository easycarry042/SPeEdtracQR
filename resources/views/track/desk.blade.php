<x-app-layout>
    {{-- The Look Up desk (Figma: STAFF LOOK UP, frames 1293:1331 + 1294:1680).
         A full-width table of the work in scope, with a modal per row. The older
         two-pane list+detail is still reachable at /track/{trackingNumber} —
         that is where the deeper review tools (requirements, release, the
         authenticity seal) live; this desk is for finding and triaging. --}}
    <div class="page-shell page-shell-loose"
         x-data="lookupDesk(@js(['pending' => $pendingRows, 'active' => $activeRows, 'flow' => $flow]))"
         @keydown.escape.window="close()">

        <div class="lookup-panel flex min-h-[600px] flex-col p-[10px]">

            {{-- Toolbar: queue tabs, search, filter — one row, as drawn. The
                 track is light and the SELECTED side is filled deep green; the
                 older Look Up frame had that the other way round. --}}
            <div class="flex flex-wrap items-center gap-[13px]">
                <div class="desk-tabs h-[66px] w-full max-w-[435px] shrink-0">
                    <button type="button" @click="tab = 'pending'" :class="tab === 'pending' ? 'on' : ''">Pending</button>
                    <button type="button" @click="tab = 'active'" :class="tab === 'active' ? 'on' : ''">In Progress</button>
                </div>

                <div class="relative min-w-0 flex-1">
                    <label for="deskSearch" class="sr-only">Search requests</label>
                    <span class="pointer-events-none absolute inset-y-0 left-[21px] flex items-center" aria-hidden="true">
                        <img src="{{ asset('images/staff/icon-search.svg') }}" alt=""
                             style="width:28px; height:28px; max-width:none;">
                    </span>
                    <input id="deskSearch" type="search" x-model="q" @input="page = 1" placeholder="Search"
                           class="h-[67px] w-full rounded-[15px] border border-[rgba(0,64,4,0.5)] bg-white/20 pl-[72px] pr-4 text-[22px] font-bold text-ink transition placeholder:text-[#b1b1b1] focus:border-green focus:bg-white/60 focus:outline-none focus:ring-4 focus:ring-green/15">
                </div>

                <button type="button" @click="q = ''; page = 1"
                        class="flex h-[67px] w-[67px] shrink-0 items-center justify-center rounded-[15px] border border-[rgba(0,64,4,0.5)] bg-white transition hover:bg-green-wash focus:outline-none focus-visible:ring-2 focus-visible:ring-green"
                        title="Clear search" aria-label="Clear search">
                    <img src="{{ asset('images/staff/icon-filter.svg') }}" alt=""
                         style="width:28px; height:28px; max-width:none;">
                </button>
            </div>

            {{-- Table --}}
            <div class="mt-[30px] min-h-0 flex-1 overflow-x-auto">
                <table class="w-full min-w-[820px] text-left">
                    <thead>
                        <tr class="border-b border-black/10 text-[20px] font-bold text-[#353535]">
                            <th class="px-[18px] pb-[18px] font-bold">Request Type</th>
                            <th class="px-[18px] pb-[18px] font-bold">Date</th>
                            <th class="px-[18px] pb-[18px] font-bold">Requested by</th>
                            <th class="px-[18px] pb-[18px] font-bold">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in pageItems" :key="row.id">
                            <tr class="border-b border-black/10 transition last:border-0 hover:bg-green-wash/40">
                                <td class="px-[18px] py-[21px]">
                                    <div class="flex items-center gap-[26px]">
                                        <span class="relative flex h-[70px] w-[70px] shrink-0 items-center justify-center" aria-hidden="true">
                                            <img src="{{ asset('images/staff/row-disc.svg') }}" alt=""
                                                 style="position:absolute; inset:0; width:70px; height:70px; max-width:none;">
                                            <img class="relative object-contain"
                                                 style="width:40px; height:40px; max-width:none;"
                                                 :src="window.requestTypeIcon(row.document_type)" alt="">
                                        </span>
                                        <span class="text-[20px] font-medium text-black" x-text="row.document_type"></span>
                                    </div>
                                </td>
                                <td class="px-[18px] py-[21px] text-[20px] font-semibold text-[#666]" x-text="row.date_short"></td>
                                <td class="px-[18px] py-[21px] text-[20px] font-medium text-black" x-text="row.citizen_name || '—'"></td>
                                <td class="px-[18px] py-[21px]">
                                    <button type="button" @click="open(row)"
                                            class="inline-flex items-center gap-[10px] text-[20px] font-medium text-[#01721a] transition hover:text-green-deep">
                                        {{-- This frame exports the eye at 28×28; the dashboard's
                                             is 24×18. Forcing one into the other's box squashes it
                                             into a ring, because the export carries
                                             preserveAspectRatio="none". --}}
                                        <img src="{{ asset('images/staff/icon-eye-28.svg') }}" alt=""
                                             style="width:28px; height:28px; max-width:none;">
                                        View details
                                    </button>
                                </td>
                            </tr>
                        </template>

                        <tr x-show="pageItems.length === 0">
                            <td colspan="4" class="px-6 py-16 text-center">
                                <p class="text-[18px] font-semibold text-ink"
                                   x-text="q ? 'No requests match that search' : 'Nothing in this queue'"></p>
                                <p class="mt-1 text-[16px] text-ink-soft"
                                   x-text="q ? 'Try a tracking number, a request type, or the requester name.' : 'Work assigned to you appears here as it arrives.'"></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Pager --}}
            <nav x-show="pageCount > 1" x-cloak aria-label="Request pages"
                 class="mt-[14px] flex items-center justify-end gap-[7px] border-t border-black/20 pt-[16px]">
                <button type="button" @click="go(page - 1)" :disabled="page === 1"
                        class="lookup-page-step" aria-label="Previous page">
                    <img src="{{ asset('images/staff/icon-angle-right.svg') }}" alt=""
                         class="rotate-180" style="width:18px; height:18px; max-width:none;">
                </button>

                <template x-for="(entry, i) in pageList" :key="i">
                    <span>
                        <span x-show="entry === 'gap'" class="px-1 text-[20px] font-semibold text-[#666]" aria-hidden="true">…</span>
                        <button x-show="entry !== 'gap'" type="button" @click="go(entry)"
                                :aria-current="entry === page ? 'page' : null"
                                class="lookup-page" :class="entry === page ? 'on' : ''"
                                x-text="entry"></button>
                    </span>
                </template>

                <button type="button" @click="go(page + 1)" :disabled="page === pageCount"
                        class="lookup-page-step" aria-label="Next page">
                    <img src="{{ asset('images/staff/icon-angle-right.svg') }}" alt=""
                         style="width:18px; height:18px; max-width:none;">
                </button>
            </nav>
        </div>

        @include('track.partials.desk-modal')
    </div>

    {{-- Shared with the dashboard table: both render rows in the browser, so
         neither can use the <x-request-type-icon> Blade component. --}}
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

        window.lookupDesk = function (cfg) {
            return {
                rows: { pending: cfg.pending, active: cfg.active },
                flow: cfg.flow,
                tab: cfg.pending.length > 0 ? 'pending' : 'active',
                q: '',
                page: 1,
                perPage: 5,
                selected: null,
                panel: null,      // 'attachments' | null — rendered inside the detail card
                side: null,       // 'date' | 'messages' | null — the second card
                dateValue: '',
                calYear: 0,
                calMonth: 0,      // 0-11, as Date reports it
                draft: '',
                file: null,       // staged attachment, posted with the message
                sending: false,
                busy: false,
                error: '',

                get matches() {
                    const needle = this.q.trim().toLowerCase();
                    const list = this.rows[this.tab] || [];

                    if (! needle) {
                        return list;
                    }

                    return list.filter(function (r) {
                        return [r.citizen_name, r.tracking_number, r.document_type]
                            .some(function (f) { return (f || '').toLowerCase().includes(needle); });
                    });
                },

                get pageCount() {
                    return Math.max(1, Math.ceil(this.matches.length / this.perPage));
                },

                get pageItems() {
                    const page = Math.min(this.page, this.pageCount);

                    return this.matches.slice((page - 1) * this.perPage, page * this.perPage);
                },

                get pageList() {
                    const last = this.pageCount;

                    if (last <= 6) {
                        return Array.from({ length: last }, function (_, i) { return i + 1; });
                    }

                    if (this.page > 5) {
                        return [1, 'gap', this.page - 1, this.page, this.page + 1, 'gap', last]
                            .filter(function (p) { return p === 'gap' || (p >= 1 && p <= last); });
                    }

                    return [1, 2, 3, 4, 5, 'gap', last];
                },

                go(page) {
                    this.page = Math.min(Math.max(1, page), this.pageCount);
                },

                open(row) {
                    this.selected = row;
                    this.panel = null;
                    this.side = null;
                    this.error = '';
                    this.dateValue = '';
                    this.draft = '';
                    this.clearFile();
                    this.allLogs = false;

                    // Land the calendar on the date already committed to, else on
                    // the one the citizen asked for, else on this month.
                    const anchor = this.parseDate(row.claim_date)
                        || this.parseDate(row.requested_claim_date)
                        || new Date();

                    this.calYear = anchor.getFullYear();
                    this.calMonth = anchor.getMonth();
                },

                close() {
                    this.selected = null;
                    this.panel = null;
                    this.side = null;
                },

                /** Open one of the two side cards, or close it if it is already up. */
                toggleSide(which) {
                    this.side = this.side === which ? null : which;

                    if (this.side === 'messages') {
                        this.readMessages();
                        this.$nextTick(() => this.scrollThread());
                    }
                },

                allLogs: false,

                /** Stage position of the selected row, 1-based, for the rail. */
                stageIndex() {
                    if (! this.selected) {
                        return 0;
                    }

                    const i = this.flow.findIndex((s) => s.value === this.selected.status);

                    return i === -1 ? 0 : i + 1;
                },

                railFill() {
                    const count = this.flow.length;

                    return count > 1 ? ((this.stageIndex() - 1) / (count - 1)) * 100 : 0;
                },

                nodeArt(position) {
                    const current = this.stageIndex();

                    if (position === current) {
                        return 'node-current';
                    }

                    return position < current ? 'node-done' : 'node-todo';
                },

                post(url, body) {
                    this.busy = true;
                    this.error = '';

                    return fetch(url, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            Accept: 'application/json',
                        },
                        body: JSON.stringify(body || {}),
                    }).then(async (r) => {
                        if (r.ok) {
                            window.location.reload();

                            return;
                        }

                        const data = await r.json().catch(() => ({}));
                        this.error = data.message || 'That could not be saved.';
                        this.busy = false;
                    }).catch(() => {
                        this.error = 'That could not be saved.';
                        this.busy = false;
                    });
                },

                markCompleted() {
                    if (! this.selected) {
                        return;
                    }

                    this.post(@js(url('/documents')) + '/' + this.selected.id + '/review/complete');
                },

                saveDate() {
                    if (! this.selected || ! this.dateValue) {
                        this.error = 'Pick the claiming date for the citizen.';

                        return;
                    }

                    this.post(
                        @js(url('/documents')) + '/' + this.selected.id + '/claim-date',
                        { claim_date: this.dateValue }
                    );
                },

                /* ── Calendar (Figma: STAFF LOOK UP 1321:1370) ──────────────── */

                /** Local YYYY-MM-DD. toISOString() would shift the day by the UTC offset. */
                isoOf(date) {
                    const pad = (n) => String(n).padStart(2, '0');

                    return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
                },

                parseDate(text) {
                    if (! text) {
                        return null;
                    }

                    const parsed = new Date(text);

                    return isNaN(parsed.getTime()) ? null : parsed;
                },

                monthLabel() {
                    return new Date(this.calYear, this.calMonth, 1)
                        .toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
                },

                shiftMonth(delta) {
                    const moved = new Date(this.calYear, this.calMonth + delta, 1);

                    this.calYear = moved.getFullYear();
                    this.calMonth = moved.getMonth();
                },

                /**
                 * The month as weeks of seven days, padded out to whole weeks with
                 * the neighbouring months' days — only as many rows as the month
                 * actually spans, so a 5-week month does not draw an empty sixth.
                 */
                calendarWeeks() {
                    const first = new Date(this.calYear, this.calMonth, 1);
                    const daysInMonth = new Date(this.calYear, this.calMonth + 1, 0).getDate();
                    const weeks = Math.ceil((first.getDay() + daysInMonth) / 7);
                    const today = this.isoOf(new Date());
                    const out = [];

                    for (let w = 0; w < weeks; w++) {
                        const row = [];

                        for (let d = 0; d < 7; d++) {
                            const day = new Date(this.calYear, this.calMonth, 1 - first.getDay() + (w * 7) + d);

                            row.push({
                                day: day.getDate(),
                                iso: this.isoOf(day),
                                label: day.toLocaleDateString(undefined, { dateStyle: 'long' }),
                                inMonth: day.getMonth() === this.calMonth,
                                sunday: day.getDay() === 0,
                                // A claiming date in the past is not a commitment
                                // anyone can keep, so those days are not offerable.
                                past: this.isoOf(day) < today,
                            });
                        }

                        out.push(row);
                    }

                    return out;
                },

                /* ── Conversation (Figma: STAFF LOOK UP 1321:1750) ──────────── */

                scrollThread() {
                    const thread = this.$refs.thread;

                    if (thread) {
                        thread.scrollTop = thread.scrollHeight;
                    }
                },

                /** Opening the thread is what clears the ticket's unread badge. */
                readMessages() {
                    if (! this.selected || ! this.selected.unread_messages) {
                        return;
                    }

                    this.selected.unread_messages = 0;

                    fetch(@js(url('/documents')) + '/' + this.selected.id + '/comments/read', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            Accept: 'application/json',
                        },
                    }).catch(() => {});
                },

                pickFile(event) {
                    this.file = event.target.files[0] || null;
                    this.error = '';
                },

                clearFile() {
                    this.file = null;

                    // The input only exists while the composer is rendered.
                    if (this.$refs.attachment) {
                        this.$refs.attachment.value = '';
                    }
                },

                sendMessage() {
                    const body = this.draft.trim();

                    if (! this.selected || ! body || this.sending) {
                        return;
                    }

                    this.sending = true;
                    this.error = '';

                    // Multipart throughout, with or without a file: one path to
                    // maintain, and the browser sets the boundary for us (so the
                    // Content-Type header must NOT be set by hand here).
                    const payload = new FormData();
                    payload.append('body', body);
                    payload.append('visibility', 'public');

                    if (this.file) {
                        payload.append('attachment', this.file);
                    }

                    fetch(@js(url('/documents')) + '/' + this.selected.id + '/comments', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            Accept: 'application/json',
                        },
                        body: payload,
                    }).then(async (r) => {
                        const data = await r.json().catch(() => ({}));

                        if (! r.ok) {
                            // 422 reports per-field messages; the file rules are
                            // the ones staff actually trip over.
                            this.error = (data.errors && Object.values(data.errors)[0][0])
                                || data.message
                                || 'That message could not be sent.';
                            this.sending = false;

                            return;
                        }

                        // Append rather than reload: the desk behind the modal has
                        // not changed, and a reload would close what staff are in.
                        this.selected.messages = (this.selected.messages || []).concat([data.comment]);
                        this.draft = '';
                        this.clearFile();
                        this.sending = false;
                        this.$nextTick(() => this.scrollThread());
                    }).catch(() => {
                        this.error = 'That message could not be sent.';
                        this.sending = false;
                    });
                },
            };
        };
    </script>
</x-app-layout>
