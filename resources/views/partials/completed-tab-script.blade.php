{{-- The dashboards' Completed tab: finished requests are fetched from
     history.data a page at a time (search and the shared filter run on the
     server), so the full history never has to ride in the page payload.

     The surrounding Alpine scope spreads in window.stCompletedMixin(url)
     beside window.stFilterMixin() and provides `tab`, `q`, and the
     fType / fFrom / fTo filter fields. Its getters read `done` when
     tab === 'done'. --}}
@once
<script>
    window.stCompletedMixin = function (url) {
        return {
            done: { items: [], total: 0, page: 1, lastPage: 1, types: [], loading: false, loaded: false, failed: false },
            doneTicket: 0,
            doneTimer: null,

            /** Fetch one page of completed requests for the current search and filter. */
            loadDone(page = 1) {
                const params = new URLSearchParams({ page });
                if (this.q.trim()) params.set('search', this.q.trim());
                if (this.fType) params.set('document_type', this.fType);
                if (this.fFrom) params.set('from', this.fFrom);
                if (this.fTo) params.set('to', this.fTo);

                // Only the latest request may land: typing fires several.
                const ticket = ++this.doneTicket;
                this.done.loading = true;

                return fetch(`${url}?${params}`, { headers: { Accept: 'application/json' } })
                    .then((r) => (r.ok ? r.json() : Promise.reject(r)))
                    .then((d) => {
                        if (ticket !== this.doneTicket) return;
                        Object.assign(this.done, {
                            items: d.data,
                            total: d.total,
                            page: d.current_page,
                            lastPage: Math.max(1, d.last_page),
                            types: d.types,
                            loading: false,
                            loaded: true,
                            failed: false,
                        });
                    })
                    .catch(() => {
                        if (ticket !== this.doneTicket) return;
                        this.done.loading = false;
                        this.done.failed = true;
                    });
            },

            /** Search or filter changed: back to page 1 once typing pauses. */
            refreshDone(delay = 250) {
                if (this.tab !== 'done') return;
                clearTimeout(this.doneTimer);
                this.doneTimer = setTimeout(() => this.loadDone(1), delay);
            },

            /** Wire the reloads; call from the component's init(). */
            watchDone() {
                this.$watch('tab', (tab) => { if (tab === 'done') this.loadDone(1); });
                this.$watch('q', () => this.refreshDone());
                ['fType', 'fFrom', 'fTo'].forEach((field) => this.$watch(field, () => this.refreshDone(0)));
                if (this.tab === 'done') this.loadDone(1);
            },

            /** The Completed tab's page numbers, in the pager's shape. */
            donePageList() {
                const last = this.done.lastPage;
                const current = this.done.page;

                if (last <= 6) return Array.from({ length: last }, (_, i) => i + 1);
                if (current > 5) {
                    return [1, 'gap', current - 1, current, current + 1, 'gap', last]
                        .filter((p) => p === 'gap' || (p >= 1 && p <= last));
                }

                return [1, 2, 3, 4, 5, 'gap', last];
            },
        };
    };
</script>
@endonce
