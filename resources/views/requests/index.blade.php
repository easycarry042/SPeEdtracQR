<<<<<<< HEAD
<x-app-layout fixed-height>
=======
<x-app-layout title="Internal Requests">
    {{-- The layout's title bar already says "Internal Requests", so only the
         action moves up here rather than repeating the heading beneath it. --}}
    @if($canFile)
        <x-slot name="pageActions">
            <a href="{{ route('requests.create') }}" title="File a request" aria-label="File a request"
               class="flex h-[52px] w-[52px] items-center justify-center rounded-full border border-hairline bg-paper text-green-deep shadow-sm transition hover:bg-green-wash focus:outline-none focus-visible:ring-2 focus-visible:ring-green">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            </a>
        </x-slot>
    @endif
>>>>>>> 445734fe6c2ab99dcac3772ebcf201eb1141dd91

    @php
        $tabs = $department
            ? [
                ['key' => 'awaiting', 'label' => 'Awaiting my office', 'list' => $awaiting],
                ['key' => 'filed', 'label' => 'Filed by my office', 'list' => $filed],
                ['key' => 'closed', 'label' => 'Closed', 'list' => $closed],
            ]
            : [
                ['key' => 'awaiting', 'label' => 'Active', 'list' => $awaiting],
                ['key' => 'closed', 'label' => 'Closed', 'list' => $closed],
            ];
    @endphp

    {{-- Same table design as the Dashboard and Look Up (.st-* in app.css). Rows
         are server-rendered per queue; the pager shows ten at a time by hiding
         the rest, so no row needs a second request to reach. --}}
    <div class="page-shell page-shell-loose"
         x-data="{
             ...window.stFilterMixin(),
             tab: 'awaiting',
             page: 1,
             perPage: 10,
             {{-- Per queue, in row order: what the filter matches on. Every internal
                  request has the same document type, so the "type" here is the
                  office that filed it — the one distinction the list has. --}}
             meta: @js(collect($tabs)->mapWithKeys(fn ($t) => [$t['key'] => $t['list']->map(fn ($doc) => [
                 'type' => $doc->requestingDepartment?->name,
                 'day' => $doc->created_at?->toDateString(),
             ])->values()])),
             typeOptions() {
                 return this.uniqueTypes(Object.values(this.meta).flat().map((r) => r.type));
             },
             {{-- Row indexes (within the queue) that pass the filter. --}}
             get visible() {
                 return (this.meta[this.tab] || [])
                     .map((r, i) => (this.passesFilter(r.type, r.day) ? i : -1))
                     .filter((i) => i !== -1);
             },
             get total() { return this.visible.length; },
             get pageCount() { return Math.max(1, Math.ceil(this.total / this.perPage)); },
             get current() { return Math.min(this.page, this.pageCount); },
             onPage(index) {
                 const position = this.visible.indexOf(index);

                 return position !== -1 && Math.floor(position / this.perPage) + 1 === this.current;
             },
             get pageList() {
                 const last = this.pageCount;

                 if (last <= 6) {
                     return Array.from({ length: last }, (_, i) => i + 1);
                 }

                 if (this.current > 5) {
                     return [1, 'gap', this.current - 1, this.current, this.current + 1, 'gap', last]
                         .filter((p) => p === 'gap' || (p >= 1 && p <= last));
                 }

                 return [1, 2, 3, 4, 5, 'gap', last];
             },
             go(page) { this.page = Math.min(Math.max(1, page), this.pageCount); },
         }">

        @if(session('status'))
            <div class="panel"><div class="pb text-[length:var(--fs-small)] font-medium text-green-deep">{{ session('status') }}</div></div>
        @endif

        <section class="flex min-h-0 flex-1 flex-col">
            <div class="st-toolbar mb-4">
                <div class="st-tabs mr-auto" role="tablist" aria-label="Request queues">
                    @foreach($tabs as $tab)
                        <button type="button" role="tab" :aria-selected="tab === '{{ $tab['key'] }}'"
                                @click="tab = '{{ $tab['key'] }}'; page = 1" :class="tab === '{{ $tab['key'] }}' ? 'on' : ''">
                            {{ $tab['label'] }}
                            <span class="st-tab-count">{{ $tab['list']->count() }}</span>
                        </button>
                    @endforeach
                </div>

                {{-- Searched on the server (?q=), so Enter submits; the field's
                     own clear "x" followed by Enter brings everything back. --}}
                <form method="GET" class="st-search" role="search">
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                    <input type="search" name="q" value="{{ $search }}" placeholder="Search tracking # or request…"
                           aria-label="Search internal requests">
                </form>

                @include('partials.staff-table-filter', [
                    'id' => 'internalFilter',
                    'typeLabel' => 'Requesting office',
                    'allTypesLabel' => 'All offices',
                ])
            </div>

            <div class="st-card">
                <div class="st-scroll">
                    @foreach($tabs as $tab)
                        <table class="st-table" x-show="tab === '{{ $tab['key'] }}'" @if(!$loop->first) x-cloak @endif>
                            <thead>
                                <tr>
                                    <th>Request</th>
                                    <th>From</th>
                                    <th>Current stage</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($tab['list'] as $doc)
                                    @php
                                        $current = $doc->requestSteps->firstWhere('status', \App\Models\RequestStep::STATUS_CURRENT);
                                        $aging = $current?->started_at;
                                        $stale = $aging && $aging->diffInHours(now()) >= 48;
                                    @endphp
                                    <tr x-show="onPage({{ $loop->index }})">
                                        <td>
                                            <div class="st-type">
                                                <span class="st-disc" aria-hidden="true">
                                                    <img src="{{ asset('images/staff/row-disc.svg') }}" alt="">
                                                    <img src="{{ asset('images/request-types/task-list-pen.svg') }}" alt="">
                                                </span>
                                                <div class="min-w-0">
                                                    <a href="{{ route('requests.show', $doc) }}" @click.prevent="$dispatch('open-internal-request', { url: @js(route('requests.show', $doc)) })" class="st-name block truncate hover:text-green hover:underline">{{ $doc->purpose }}</a>
                                                    <p class="st-sub mono">{{ $doc->tracking_number }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            {{ $doc->requestingDepartment?->code ?? '—' }}
                                            <p class="st-sub">{{ $doc->creator?->name }}</p>
                                        </td>
                                        <td>
                                            @if($current)
                                                <p class="font-medium text-ink">{{ $current->department?->name }}</p>
                                                <p class="st-sub {{ $stale ? 'font-semibold text-status-red' : '' }}">
                                                    {{ $current->action }}
                                                    @if($aging) · {{ $aging->diffForHumans() }} @endif
                                                </p>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            <span class="pill {{ match($doc->internalStatusBand()) {
                                                'green' => 'p-green',
                                                'red' => 'p-red',
                                                'returned' => 'p-orange',
                                                default => 'p-amber',
                                            } }}">{{ $doc->internalStatusLabel() }}</span>
                                        </td>
                                        <td>
                                            <a href="{{ route('requests.show', $doc) }}" @click.prevent="$dispatch('open-internal-request', { url: @js(route('requests.show', $doc)) })" class="st-action">
                                                <img src="{{ asset('images/staff/icon-eye.svg') }}" alt="">
                                                Open
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="st-empty">
                                        <td colspan="5" class="!p-0">
                                            @if($tab['key'] === 'awaiting' && $department)
                                                <x-empty-state title="Nothing awaiting {{ $department->name }}" icon="inbox">
                                                    Requests routed to your office for action will appear here.
                                                </x-empty-state>
                                            @elseif($tab['key'] === 'filed')
                                                <x-empty-state title="No active requests in transit" icon="document">
                                                    Requests your office has filed will show here until they're closed.
                                                </x-empty-state>
                                            @else
                                                <x-empty-state title="Nothing here yet" icon="inbox">
                                                    Requests your office has closed will be archived here.
                                                </x-empty-state>
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                                @if($tab['list']->isNotEmpty())
                                    <tr class="st-empty" x-show="total === 0" x-cloak>
                                        <td colspan="5">
                                            <p class="text-sm font-semibold text-ink">No requests match</p>
                                            <p class="mt-1 text-sm text-ink-soft">Loosen the filter to see more.</p>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    @endforeach
                </div>

                @include('partials.staff-table-pager', ['label' => 'Internal request pages'])
            </div>
        </section>
    </div>

    {{-- Request detail, opened from a row. The detail (requests/partials/detail)
         is fetched from the server and dropped in, so the cards and their
         actions are exactly the page's. Reopens by itself after an action taken
         inside it (?open=), which returns here. The href on each row is the
         no-JavaScript route to the full page. --}}
    @php $openId = (int) request('open'); @endphp
    <div x-data="internalRequestModal(@js($openId ? route('requests.show', $openId) : null))"
         @open-internal-request.window="load($event.detail.url)"
         @close-internal-request.window="close()"
         @keydown.escape.window="if (open && ! $event.defaultPrevented) close()">
        <div x-show="open" x-cloak x-transition.opacity.duration.150ms
             class="fixed inset-0 z-50 overflow-y-auto bg-black/40 px-4 py-[27px]"
             @click.self="close()" role="dialog" aria-modal="true" aria-label="Internal request">
            <div class="mx-auto w-full max-w-[1120px]">
                <div x-show="loading" class="dm-card mx-auto flex h-40 max-w-[660px] items-center justify-center text-[length:var(--fs-small)] text-ink-soft">Loading…</div>
                <p x-show="error" x-cloak x-text="error" class="dm-card mx-auto max-w-[660px] p-6 text-center text-[length:var(--fs-small)] font-semibold text-status-red"></p>
                <div x-ref="content"></div>
            </div>
        </div>
    </div>

    <script>
        window.internalRequestModal = function (initialUrl) {
            return {
                open: false,
                loading: false,
                error: '',

                init() {
                    if (initialUrl) {
                        this.load(initialUrl);
                        // Drop ?open= so a refresh does not reopen it again.
                        const url = new URL(window.location.href);
                        url.searchParams.delete('open');
                        history.replaceState(history.state, '', url);
                    }
                },

                async load(url) {
                    this.open = true;
                    this.loading = true;
                    this.error = '';
                    this.clear();

                    try {
                        const target = new URL(url, window.location.href);
                        target.searchParams.set('modal', '1');
                        const response = await fetch(target, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
                        if (! response.ok) {
                            throw new Error(response.status === 404 ? 'That request could not be found.' : 'That request could not be opened.');
                        }
                        this.mount(await response.text());
                    } catch (e) {
                        this.error = e.message || 'That request could not be opened.';
                    } finally {
                        this.loading = false;
                    }
                },

                /**
                 * Insert the detail with Alpine paused, run its scripts (they
                 * register the components it uses), then start Alpine on it.
                 */
                mount(html) {
                    const box = this.$refs.content;
                    const insert = () => {
                        box.innerHTML = html;
                        box.querySelectorAll('script').forEach((old) => {
                            const fresh = document.createElement('script');
                            [...old.attributes].forEach((a) => fresh.setAttribute(a.name, a.value));
                            fresh.textContent = old.textContent;
                            old.replaceWith(fresh);
                        });
                    };
                    window.Alpine ? window.Alpine.mutateDom(insert) : insert();
                    window.Alpine?.initTree(box);
                },

                clear() {
                    const box = this.$refs.content;
                    if (! box || ! box.firstChild) {
                        return;
                    }
                    window.SpeedQr?.stop();
                    window.Alpine?.destroyTree(box);
                    box.innerHTML = '';
                },

                close() {
                    this.open = false;
                    this.error = '';
                    this.clear();
                },
            };
        };
    </script>
</x-app-layout>
