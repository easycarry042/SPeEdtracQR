<x-app-layout fixed-height title="Look Up">
    {{-- The Look Up desk (Figma: STAFF LOOK UP, frames 1293:1331 + 1294:1680).
         A full-width table of the work in scope, with a modal per row. The older
         two-pane list+detail is still reachable at /track/{trackingNumber} —
         that is where the deeper review tools (requirements, release, the
         authenticity seal) live; this desk is for finding and triaging. --}}
    <div class="page-shell page-shell-loose"
         x-data="lookupDesk(@js(['pending' => $pendingRows, 'active' => $activeRows, 'flow' => $flow, 'rail' => $rail, 'openTracking' => $openTracking]))"
         @keydown.escape.window="close()">

        {{-- Same table design as the Dashboard and Internal (.st-* in app.css):
             queue tabs on the left, search and clear on the right, then a card
             that fills the page and scrolls its rows under a pinned header. --}}
        <section class="flex min-h-0 flex-1 flex-col">
            <div class="st-toolbar mb-4">
                <div class="st-tabs mr-auto" role="tablist" aria-label="Request queues">
                    <button type="button" role="tab" @click="tab = 'pending'; page = 1"
                            :class="tab === 'pending' ? 'on' : ''" :aria-selected="tab === 'pending'">
                        Pending <span class="st-tab-count" x-text="rows.pending.length"></span>
                    </button>
                    <button type="button" role="tab" @click="tab = 'active'; page = 1"
                            :class="tab === 'active' ? 'on' : ''" :aria-selected="tab === 'active'">
                        In Progress <span class="st-tab-count" x-text="rows.active.length"></span>
                    </button>
                </div>

                <label for="deskSearch" class="sr-only">Search requests</label>
                <div class="st-search">
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                    <input id="deskSearch" type="search" x-model="q" @input="page = 1" placeholder="Search">
                </div>

                @include('partials.staff-table-filter', ['id' => 'deskFilter'])
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
                            <template x-for="row in pageItems" :key="row.id">
                                <tr>
                                    <td>
                                        <div class="st-type">
                                            <span class="st-disc" aria-hidden="true">
                                                <img src="{{ asset('images/staff/row-disc.svg') }}" alt="">
                                                <img :src="window.requestTypeIcon(row.document_type)" alt="">
                                            </span>
                                            <p class="st-name truncate" x-text="row.document_type"></p>
                                        </div>
                                    </td>
                                    <td class="mono" x-text="row.tracking_number"></td>
                                    <td class="mono" x-text="row.submitted_at"></td>
                                    <td x-text="row.citizen_name || '—'"></td>
                                    <td>
                                        <button type="button" @click="open(row)" class="st-action">
                                            <img src="{{ asset('images/staff/icon-eye.svg') }}" alt="">
                                            View details
                                        </button>
                                    </td>
                                </tr>
                            </template>

                            <tr x-show="pageItems.length === 0" class="st-empty">
                                <td colspan="5">
                                    <p class="text-sm font-semibold text-ink"
                                       x-text="q || filterCount() ? 'No requests match' : 'Nothing in this queue'"></p>
                                    <p class="mt-1 text-sm text-ink-soft"
                                       x-text="q || filterCount() ? 'Try another search, or loosen the filter.' : 'Work assigned to you appears here as it arrives.'"></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @include('partials.staff-table-pager', ['label' => 'Request pages'])
            </div>
        </section>

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
                ...window.stFilterMixin(),
                rows: { pending: cfg.pending, active: cfg.active },
                flow: cfg.flow,
                rail: cfg.rail,
                tab: cfg.pending.length > 0 ? 'pending' : 'active',
                q: '',
                page: 1,
                perPage: 10,
                selected: null,
                qrOpen: false,    // the QR viewer over the detail card
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
                openTracking: cfg.openTracking || null,

                /**
                 * Deep link (?open=SPD-…): open that row's detail card on load,
                 * on its own queue's tab and page, so closing the card leaves
                 * the viewer looking at the row they came for. The controller
                 * has already redirected away anything not on this desk.
                 */
                init() {
                    if (! this.openTracking) {
                        return;
                    }

                    for (const queue of ['pending', 'active']) {
                        const row = this.rows[queue].find((r) => r.tracking_number === this.openTracking);

                        if (! row) {
                            continue;
                        }

                        this.tab = queue;
                        this.page = Math.max(1, Math.floor(this.matches.indexOf(row) / this.perPage) + 1);
                        this.open(row);

                        return;
                    }
                },

                /** Types across both queues, so switching tab keeps the choice. */
                typeOptions() {
                    return this.uniqueTypes(this.rows.pending.concat(this.rows.active).map((r) => r.document_type));
                },

                get matches() {
                    const needle = this.q.trim().toLowerCase();
                    const list = this.rows[this.tab] || [];

                    return list.filter((r) => this.passesFilter(r.document_type, r.submitted_on)
                        && (! needle || [r.citizen_name, r.tracking_number, r.document_type]
                            .some((f) => (f || '').toLowerCase().includes(needle))));
                },

                get total() {
                    return this.matches.length;
                },

                get pageCount() {
                    return Math.max(1, Math.ceil(this.matches.length / this.perPage));
                },

                /** Searching can shrink the list out from under the open page. */
                get current() {
                    return Math.min(this.page, this.pageCount);
                },

                get pageItems() {
                    return this.matches.slice((this.current - 1) * this.perPage, this.current * this.perPage);
                },

                get pageList() {
                    const last = this.pageCount;

                    if (last <= 6) {
                        return Array.from({ length: last }, function (_, i) { return i + 1; });
                    }

                    if (this.current > 5) {
                        return [1, 'gap', this.current - 1, this.current, this.current + 1, 'gap', last]
                            .filter(function (p) { return p === 'gap' || (p >= 1 && p <= last); });
                    }

                    return [1, 2, 3, 4, 5, 'gap', last];
                },

                go(page) {
                    this.page = Math.min(Math.max(1, page), this.pageCount);
                },

                open(row) {
                    this.selected = row;
                    this.qrOpen = false;
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
                    // Esc steps back one layer: the QR viewer first, then the modal.
                    if (this.qrOpen) {
                        this.qrOpen = false;

                        return;
                    }

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

                /**
                 * Stage of the selected row on the four-stage bar, 1-based. The
                 * server works it out (DocumentStatus::railStage), so side
                 * states land where the request actually stands.
                 */
                stageIndex() {
                    return this.selected ? (this.selected.rail_stage || 1) : 0;
                },

                /** 'done' | 'current' | 'todo' for the stage at `position`. */
                stageState(position) {
                    const current = this.stageIndex();

                    // A completed request has nothing left in progress.
                    if (position < current || (position === current && this.selected?.status === 'completed')) {
                        return 'done';
                    }

                    return position === current ? 'current' : 'todo';
                },

                /** Off-the-line states, named under the bar. */
                sideState() {
                    return {
                        returned: 'Returned for revision',
                        on_hold: 'On hold',
                        denied: 'Denied',
                    }[this.selected?.status] || null;
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

                /** Jump the calendar back to this month. */
                calendarToToday() {
                    const now = new Date();
                    this.calYear = now.getFullYear();
                    this.calMonth = now.getMonth();
                },

                /** "Friday, August 7, 2026" for the picked day. */
                pickedLabel() {
                    const picked = this.parseDate(this.dateValue + 'T00:00:00');

                    return picked ? picked.toLocaleDateString(undefined, { dateStyle: 'full' }) : '';
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
                                today: this.isoOf(day) === today,
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
