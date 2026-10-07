<x-app-layout fixed-height>
    @php
        $supervisorView = $supervisorView ?? false;
    @endphp

    {{-- Scan straight to a request instead of hunting for it in the list. --}}
    <x-slot name="pageActions">
        <button type="button"
                @click="window.dispatchEvent(new CustomEvent('lookup-scan-open'))"
                class="cr-btn cr-btn-primary">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/>
                <path stroke-linecap="round" d="M3 12h18"/>
            </svg>
            Scan QR
        </button>
    </x-slot>

    {{-- Paging for the queue lists. The rows are server-rendered, so the page
         window is computed over the ones currently matching the search box
         rather than over a JS array. Five per page, as the frame draws. --}}
    <script>
        window.lookupPager = function () {
            return {
                page: 1,
                perPage: 5,

                matchingRows() {
                    const list = this.$refs.list;

                    if (! list) {
                        return [];
                    }

                    const needle = (this.q || '').trim().toLowerCase();

                    return [...list.querySelectorAll('[data-search]')]
                        .filter(function (row) {
                            return ! needle || row.dataset.search.includes(needle);
                        });
                },

                rowVisible(el) {
                    const index = this.matchingRows().indexOf(el);

                    if (index === -1) {
                        return false;
                    }

                    return index >= (this.page - 1) * this.perPage
                        && index < this.page * this.perPage;
                },

                get pageCount() {
                    return Math.max(1, Math.ceil(this.matchingRows().length / this.perPage));
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
            };
        };
    </script>

    {{-- The Pending / In Progress list is kept compact (1/3) so the ticket
         handling panel gets the room (2/3). --}}
    {{-- lg:h-full + lg:grid-rows-1: the grid takes the height the fixed-height
         shell hands it, giving both panels a definite height to scroll within
         rather than growing the page. --}}
    <div class="grid w-full min-h-0 flex-1 grid-cols-1 gap-8 lg:h-full lg:grid-cols-[398fr_690fr] lg:grid-rows-1 lg:gap-[13px]"
         x-data="{ tab: @js($activeTab ?? 'inprogress'), q: '' }">
        {{-- Fixed-height panel; the list scrolls inside it --}}
        <div class="lookup-panel flex min-h-0 flex-col overflow-hidden p-3 lg:h-full">
            @php
                // (left tab key, label, count) — supervisor: Pending/In Progress,
                // staff: In Progress/Completed.
                $leftTab = $supervisorView
                    ? ['key' => 'pending', 'label' => 'Pending', 'count' => $pending->count()]
                    : ['key' => 'inprogress', 'label' => 'In Progress', 'count' => $myActive->count()];
                $rightTab = $supervisorView
                    ? ['key' => 'inprogress', 'label' => 'In Progress', 'count' => $inProgress->count()]
                    : ['key' => 'completed', 'label' => 'Completed', 'count' => $myCompleted->count()];
                $leftList = $supervisorView ? $pending : $myActive;
                $rightList = $supervisorView ? $inProgress : $myCompleted;
            @endphp

            {{-- Tabs --}}
            <div class="lookup-tabs mb-3 w-full">
                @foreach([$leftTab, $rightTab] as $t)
                    <button type="button" @click="tab = '{{ $t['key'] }}'"
                            :class="tab === '{{ $t['key'] }}' ? 'on' : ''">
                        {{ $t['label'] }}
                        <span class="lookup-tab-count">{{ $t['count'] }}</span>
                    </button>
                @endforeach
            </div>

            {{-- Narrows the list in place. Client-side on purpose: the whole
                 scoped list is already rendered, so a round trip per keystroke
                 would fetch what the page is holding. --}}
            <div class="mb-3 flex items-center gap-2">
                <label for="lookupSearch" class="sr-only">Search this list</label>
                <div class="relative min-w-0 flex-1">
                    <span class="pointer-events-none absolute inset-y-0 left-[16px] flex items-center" aria-hidden="true">
                        <img src="{{ asset('images/staff/icon-search.svg') }}" alt="" class="h-[24px] w-[24px]">
                    </span>
                    <input id="lookupSearch" type="search" x-model="q" placeholder="Search"
                           class="h-[54px] w-full rounded-[15px] border border-[rgba(0,64,4,0.5)] bg-white/20 pl-[58px] pr-3 text-[18px] font-bold text-ink transition placeholder:text-[rgba(177,177,177,0.9)] focus:border-green focus:bg-white/60 focus:outline-none focus:ring-4 focus:ring-green/15">
                </div>
                <button type="button" @click="q = ''"
                        class="flex h-[54px] w-[54px] shrink-0 items-center justify-center rounded-[15px] border border-[rgba(0,64,4,0.5)] bg-paper transition hover:bg-green-wash focus:outline-none focus-visible:ring-2 focus-visible:ring-green"
                        title="Clear search" aria-label="Clear search">
                    <img src="{{ asset('images/staff/icon-filter.svg') }}" alt="" class="h-[24px] w-[24px]">
                </button>
            </div>

            {{-- Two lists; clicking an item opens it in the detail box on the right --}}
            @foreach([['key' => $leftTab['key'], 'list' => $leftList, 'empty' => $supervisorView ? 'No pending requests.' : 'Nothing in progress.'], ['key' => $rightTab['key'], 'list' => $rightList, 'empty' => $supervisorView ? 'No requests in progress.' : 'No completed requests yet.']] as $section)
                <div x-show="tab === '{{ $section['key'] }}'" @if(! $loop->first) x-cloak @endif
                     x-data="lookupPager()" x-effect="q; page = 1"
                     class="flex min-h-0 flex-1 flex-col">
                <div x-ref="list" class="min-h-0 flex-1 overflow-y-auto">
                    @forelse($section['list'] as $item)
                        @php
                            $isOpen = $item->tracking_number === $document->tracking_number;
                        @endphp
                        <a href="{{ route('track.show', ['trackingNumber' => $item->tracking_number, 'tab' => $section['key']]) }}"
                           data-search="{{ Str::lower($item->document_type.' '.$item->citizen_name.' '.$item->tracking_number) }}"
                           x-show="rowVisible($el)"
                           @class([
                               'flex items-center gap-[14px] border-b border-[rgba(0,0,0,0.12)] px-[9px] py-[10px] last:border-b-0',
                               'rounded-[12px] border border-[rgba(0,64,4,0.5)] bg-[rgba(1,114,26,0.14)]' => $isOpen,
                               'hover:bg-green-wash/50' => ! $isOpen,
                           ])>
                            <span class="relative flex h-[60px] w-[60px] shrink-0 items-center justify-center">
                                <img src="{{ asset('images/staff/row-disc.svg') }}" alt="" class="absolute inset-0 h-full w-full">
                                <x-request-type-icon :type="$item->document_type" :size="30" class="relative" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline justify-between gap-2">
                                    <p @class([
                                           'truncate text-[16px]',
                                           'font-black text-[#004004]' => $isOpen,
                                           'font-bold text-black' => ! $isOpen,
                                       ])>{{ $item->document_type }}</p>
                                    <p class="shrink-0 text-[13px] font-semibold {{ $isOpen ? 'text-[#4a4a4a]' : 'text-[#666]' }}">
                                        {{ $item->created_at->format('m/d/y') }}
                                    </p>
                                </div>
                                <p class="truncate text-[15px] font-bold {{ $isOpen ? 'text-[#004004]' : 'text-[#666]' }}">
                                    {{ $item->citizen_name ? 'Requested by '.$item->citizen_name : $item->tracking_number }}
                                </p>
                            </div>
                        </a>
                    @empty
                        <p class="px-2 py-8 text-center text-sm text-ink-soft">{{ $section['empty'] }}</p>
                    @endforelse
                </div>

                {{-- Pager (Figma). Hidden on a single page: a lone "1" is noise. --}}
                <nav x-show="pageCount > 1" x-cloak aria-label="Request pages"
                     class="mt-[10px] flex items-center justify-center gap-[7px] border-t border-black/20 pt-[14px]">
                    <button type="button" @click="go(page - 1)" :disabled="page === 1"
                            class="lookup-page-step" aria-label="Previous page">
                        <img src="{{ asset('images/staff/icon-angle-right.svg') }}" alt=""
                             class="h-[18px] rotate-180" style="width: 18px; max-width: none;">
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
                             class="h-[18px]" style="width: 18px; max-width: none;">
                    </button>
                </nav>
                </div>
            @endforeach
        </div>

        {{-- Fixed-height panel; the document details scroll inside it --}}
        <div class="lookup-panel p-[30px] lg:h-full lg:overflow-y-auto">
            {{-- Type and requester lead; the tracking number is paired with a QR
                 glyph on the right, since the number and the code on the paper
                 are the same handle. --}}
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-[24px] font-black leading-tight text-green">{{ $document->document_type }}</p>
                    <p class="text-[18px] text-ink-soft">{{ $document->citizen_name ?? 'N/A' }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="flex h-[52px] w-[52px] shrink-0 items-center justify-center rounded-[10px] border border-green/40 text-green" aria-hidden="true">
                        <svg class="h-[30px] w-[30px]" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3 3h7v7H3V3zm2 2v3h3V5H5zm9-2h7v7h-7V3zm2 2v3h3V5h-3zM3 14h7v7H3v-7zm2 2v3h3v-3H5zm9-2h3v2h-3v-2zm5 0h2v3h-2v-3zm-5 4h2v3h-2v-3zm4 1h4v2h-2v1h-2v-3z"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-[20px] font-black leading-tight text-green">Tracking Number</p>
                        <span class="mono text-[18px] font-bold text-ink">{{ $document->tracking_number }}</span>
                    </div>
                </div>
            </div>

            @php
                $isPendingReview = $supervisorView && $document->statusEnum() === \App\Enums\DocumentStatus::Pending;
                $isStaffAssigned = $staffView && (int) $document->assigned_to === (int) auth()->id();
                $isStaffApprovable = $isStaffAssigned && $document->status === 'approved';
            @endphp

            {{-- Status progress leads the panel: the stage is the first thing a
                 reviewer needs, before any of the record's details. --}}
            <div class="mt-5 flex flex-wrap items-center gap-3">
                <x-status-badge :status="$document->status" />
                @if($document->assigned_to)
                    <p class="text-[13px] text-ink-soft">
                        Assigned to <span class="font-semibold text-green-deep">{{ $document->assignedTo->name ?? '—' }}</span>
                    </p>
                @endif
            </div>

            {{-- ── Stage rail (Figma: STAFF LOOK UP) ────────────────────────────
                 A single 10px track with the travelled part filled, a 40px node
                 on the current stage and 25px nodes either side. The frame draws
                 four stages; the app has five, so the rail is built from the real
                 flow — the treatment is copied, the stage list is not invented. --}}
            @php
                // Fully qualified: this @php block sits inside a conditional, and
                // a `use` statement there is a fatal.
                $railFlow = \App\Enums\DocumentStatus::flow();
                $railStage = $document->statusEnum();
                $railCurrent = $railStage->position();
                $railCount = count($railFlow);
                // Fill stops at the centre of the current node.
                $railFill = $railCount > 1 && $railCurrent > 0
                    ? (($railCurrent - 1) / ($railCount - 1)) * 100
                    : 0;
            @endphp

            <div class="mt-[38px] px-[22px]">
                <div class="relative h-[40px]">
                    <div class="absolute left-0 right-0 top-[15px] h-[10px] rounded-[50px] bg-[#d9d9d9]"></div>
                    <div class="absolute left-0 top-[15px] h-[10px] rounded-[50px] bg-[#01721a]"
                         style="width: {{ $railFill }}%"></div>

                    @foreach($railFlow as $i => $stage)
                        @php
                            $pos = $i + 1;
                            $isDone = $pos < $railCurrent;
                            $isNow = $pos === $railCurrent;
                            $offset = $railCount > 1 ? ($i / ($railCount - 1)) * 100 : 0;
                        @endphp
                        <span class="absolute top-1/2 -translate-x-1/2 -translate-y-1/2"
                              style="left: {{ $offset }}%">
                            {{-- Sized inline: the two sizes come from the frame and
                                 appear nowhere else, so there is nothing for
                                 Tailwind's scanner to pick up. max-width:none is
                                 required — the last node's wrapper sits at
                                 left:100%, where the available width is zero and
                                 preflight's `img { max-width: 100% }` collapses
                                 it to nothing. --}}
                            <img src="{{ asset('images/staff/node-'.($isNow ? 'current' : ($isDone ? 'done' : 'todo')).'.svg') }}"
                                 alt="" style="width: {{ $isNow ? 40 : 25 }}px; height: {{ $isNow ? 40 : 25 }}px; max-width: none;">
                        </span>
                    @endforeach
                </div>

                {{-- The end labels anchor to their own edge rather than centring
                     on the node: centred, the last one runs off the panel. --}}
                <div class="relative mt-[10px] h-[24px]">
                    @foreach($railFlow as $i => $stage)
                        @if($loop->first)
                            <span class="absolute left-0 whitespace-nowrap text-[15px] font-medium text-black xl:text-[18px]">{{ $stage->label() }}</span>
                        @elseif($loop->last)
                            <span class="absolute right-0 whitespace-nowrap text-[15px] font-medium text-black xl:text-[18px]">{{ $stage->label() }}</span>
                        @else
                            <span class="absolute -translate-x-1/2 whitespace-nowrap text-[15px] font-medium text-black xl:text-[18px]"
                                  style="left: {{ ($i / ($railCount - 1)) * 100 }}%">{{ $stage->label() }}</span>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- ── Jump buttons (Figma) ─────────────────────────────────────────
                 The frame's three actions. Each scrolls to the section of this
                 panel that already owns that job, rather than duplicating it. --}}
            <div class="mt-[28px] flex flex-wrap gap-[10px]">
                <a href="#panel-messages" class="lookup-action">
                    <img src="{{ asset('images/staff/icon-message.svg') }}" alt="" class="h-[28px] w-[28px]">
                    Messages
                </a>
                <a href="#panel-attachments" class="lookup-action">
                    <img src="{{ asset('images/staff/icon-file.svg') }}" alt="" class="h-[22px] w-[22px]">
                    View Attachments
                </a>
                <a href="#panel-claiming" class="lookup-action">
                    <img src="{{ asset('images/staff/icon-calendar.svg') }}" alt="" class="h-[22px] w-[22px]">
                    Set Claiming Date
                </a>
            </div>

            {{-- Stage controls (Advance / Move back / Return). The component's own
                 stage line is off — the rail above already draws it — but the
                 controls and their stage gates stay exactly as they were. --}}
            <div class="mt-[18px]">
                <x-routing-stepper :document="$document" :controls="true" :line="false" />
            </div>

            {{-- ── Logs (Figma) ─────────────────────────────────────────────────
                 Newest first, two visible, the rest behind "View More" — the
                 frame's own rule. Entries come from the status-change activity
                 log the controller already builds. --}}
            <div class="mt-[34px]" x-data="{ allLogs: false }">
                <h2 class="!text-[22px] !font-black !text-[#004004]">Logs</h2>

                @php
                    $logEntries = $timeline->reverse()->values();
                @endphp

                @forelse($logEntries as $index => $log)
                    <div class="mt-[18px] flex items-center gap-[14px]"
                         @if($index >= 2) x-show="allLogs" x-cloak @endif>
                        <img src="{{ asset('images/staff/log-bullet.svg') }}" alt="" class="h-[15px] w-[15px] shrink-0">
                        <span class="shrink-0 text-[16px] font-medium text-[#353535] xl:text-[18px]">{{ $log['event'] }}</span>
                        <span class="lookup-leader" aria-hidden="true"></span>
                        <span class="shrink-0 text-[16px] font-bold text-[#686868] xl:text-[18px]">{{ $log['timestamp'] }}</span>
                    </div>
                @empty
                    <p class="mt-[18px] text-[16px] text-ink-soft">Nothing recorded yet.</p>
                @endforelse

                @if($logEntries->count() > 2)
                    <button type="button" @click="allLogs = ! allLogs"
                            class="mt-[20px] flex w-full items-center gap-[18px] text-[16px] font-medium text-black xl:text-[18px]">
                        <span class="h-[2px] flex-1 bg-[#d9d9d9]"></span>
                        <span class="flex items-center gap-1 whitespace-nowrap">
                            <span x-text="allLogs ? 'View Less' : 'View More'">View More</span>
                            <svg class="h-4 w-4 transition" :class="allLogs ? 'rotate-180' : ''"
                                 fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/>
                            </svg>
                        </span>
                        <span class="h-[2px] flex-1 bg-[#d9d9d9]"></span>
                    </button>
                @endif
            </div>

            {{-- ── More Info (Figma) ────────────────────────────────────────
                 The frame's five rows lead, because they are what a reviewer
                 reads first. The rows the app carries beyond them (department,
                 who encoded it, purpose) follow in the same treatment rather
                 than being dropped. --}}
            @unless($document->isInternal())
            <div class="mt-[36px]">
                <h2 id="panel-claiming" class="!text-[22px] !font-black !text-[#004004] scroll-mt-6">More Info</h2>

                @php
                    $infoRows = [
                        ['Request by', $document->citizen_name ?: '—'],
                        ['Email', $document->citizen_email ?: '—'],
                        ['Contact No.', $document->citizen_contact ?: '—'],
                        ['Request Type', $document->document_type ?: '—'],
                        ['Request Date', $document->created_at?->format('F j, Y') ?: '—'],
                        ['Department', $document->department
                            ? $document->department->name.($document->department->code ? ' · '.$document->department->code : '')
                            : 'Not yet routed'],
                        ['Staff assigned', $document->assignedTo?->name ?: 'Not yet assigned'],
                        [$document->source === 'online' ? 'Entered' : 'Encoded by',
                         $document->source === 'online' ? 'Online (citizen self-service)' : ($document->creator?->name ?? 'Staff')],
                    ];

                    if ($document->purpose) {
                        $infoRows[] = ['Purpose', $document->purpose];
                    }

                    $infoRows[] = ['Claiming date', $document->claim_date?->format('l, M d, Y') ?: 'Not set yet'];
                @endphp

                <dl class="mt-[16px]">
                    @foreach($infoRows as [$label, $value])
                        <div class="flex items-baseline justify-between gap-6 border-b-2 border-[#d9d9d9] py-[13px] last:border-b-0">
                            <dt class="shrink-0 text-[16px] font-medium text-[#353535] xl:text-[18px]">{{ $label }}</dt>
                            <dd class="break-all text-right text-[16px] font-bold text-black xl:text-[18px]">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            @if($document->description)
                <p class="mt-3 text-sm text-ink"><span class="text-ink-soft">Details:</span> {{ $document->description }}</p>
            @endif
            @endunless

            {{-- No custody/QR card here: for a citizen request the detail panel is
                 for reviewing and deciding. Folder custody scanning belongs to the
                 internal endorsement chain, which requires it and shows its own
                 scan trail (resources/views/requests/show.blade.php). --}}

            {{-- ── QR-gated release: Completed docs are claimed at the counter ── --}}
            @if($document->status === 'completed')
                @php
                    $canRelease = auth()->user()->can('scan documents') || auth()->user()->can('assign documents') || auth()->user()->can('manage system');
                @endphp
                @if($errors->has('release'))
                    <div class="gate-errors" role="alert"><p>{{ $errors->first('release') }}</p></div>
                @endif
                @if($document->claimed_at)
                    <div class="release-stamp">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5 9-10"/></svg>
                        <span>Released to citizen on <b class="mono">{{ $document->claimed_at->format('M d, Y h:i A') }}</b>{{ $document->releasedBy ? ' by '.$document->releasedBy->name : '' }}</span>
                    </div>
                @elseif($canRelease)
                    <div class="release-panel" x-data="{ open: false }">
                        <div class="rp-head">
                            <p><b>Ready for release.</b> The citizen claims this by presenting their QR code at the counter.</p>
                            <button type="button" class="cr-btn cr-btn-primary cr-btn-sm" @click="open = !open">Release to citizen</button>
                        </div>
                        <div x-show="open" x-cloak class="rp-confirm">
                            <p>Confirm the person at the counter matches the record:</p>
                            <p class="rp-name">{{ $document->citizen_name ?? 'No citizen name on record' }}</p>
                            <form method="POST" action="{{ route('documents.release', $document) }}" class="mt-2 flex flex-wrap gap-2">
                                @csrf @method('PATCH')
                                <button type="submit" class="cr-btn cr-btn-primary cr-btn-sm">Confirm — mark as released</button>
                                <button type="button" class="cr-btn cr-btn-sm" @click="open = false">Cancel</button>
                            </form>
                        </div>
                    </div>
                @endif

                {{-- Authenticity seal: signed QR anyone can scan to verify. --}}
                @if($sealSvg)
                    <div class="seal-panel">
                        <img src="data:image/svg+xml;base64,{{ $sealSvg }}" alt="Authenticity verification QR code" width="84" height="84">
                        <div>
                            <p class="seal-title">Authenticity seal</p>
                            <p class="seal-sub">Anyone scanning this QR gets an official yes/no that this document is genuine — the link is cryptographically signed.</p>
                            <a href="{{ $verifyUrl }}" target="_blank" class="cr-link">Open verification page →</a>
                        </div>
                    </div>
                @endif
            @endif

            {{-- ── Pending review + assign (supervisor) — inline, no modal ───────── --}}
            @if($isPendingReview)
                <div class="mt-5 rounded-[10px] border border-hairline-strong bg-green-wash/40 p-5"
                     x-data="{
                        staffId: '',
                        submitting: false,
                        islandError: '',
                        denyOpen: false,
                        denyReason: '',
                        approve() {
                            if (!this.staffId || this.submitting) return;
                            this.submitting = true;
                            const fd = new FormData();
                            fd.append('assigned_to', this.staffId);
                            fetch('{{ route('documents.assign-approve', $document) }}', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                                body: fd,
                            }).then(r => {
                                if (r.ok) { window.location.href = '{{ route('track.show', $document->tracking_number) }}'; }
                                else { this.submitting = false; this.islandError = 'Could not approve. Please try again.'; }
                            }).catch(() => { this.submitting = false; this.islandError = 'Network error. Please try again.'; });
                        },
                        confirmDeny() {
                            if (this.submitting) return;
                            this.submitting = true;
                            const fd = new FormData();
                            if (this.denyReason.trim()) fd.append('reason', this.denyReason.trim());
                            fetch('{{ route('documents.deny', $document) }}', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                                body: fd,
                            }).then(r => {
                                if (r.ok) { window.location.href = '{{ route('track.show', $document->tracking_number) }}'; }
                                else { this.submitting = false; this.islandError = 'Could not deny. Please try again.'; }
                            }).catch(() => { this.submitting = false; this.islandError = 'Network error. Please try again.'; });
                        }
                     }">
                    <p class="text-sm font-bold text-green-deep">Review &amp; assign</p>

                    <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                        <div class="flex justify-between gap-3 border-b border-hairline pb-1"><dt class="text-ink-soft">Submitted</dt><dd class="text-right font-medium text-ink">{{ $document->created_at?->format('M j, Y g:i A') }}</dd></div>
                        <div class="flex justify-between gap-3 border-b border-hairline pb-1"><dt class="text-ink-soft">Purpose</dt><dd class="text-right font-medium text-ink">{{ $document->purpose ?: '—' }}</dd></div>
                        @if($document->quantity)
                        <div class="flex justify-between gap-3 border-b border-hairline pb-1"><dt class="text-ink-soft">Quantity</dt><dd class="text-right font-medium text-ink">{{ number_format($document->quantity) }}</dd></div>
                        @endif
                        @if($document->needed_by)
                        <div class="flex justify-between gap-3 border-b border-hairline pb-1"><dt class="text-ink-soft">Needed by</dt><dd class="text-right font-medium text-ink">{{ $document->needed_by->format('M j, Y') }}</dd></div>
                        @endif
                    </dl>

                    @if($document->description)
                        <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-ink-soft">Description</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-ink">{{ $document->description }}</p>
                    @endif

                    <p x-show="islandError" x-cloak x-text="islandError" class="mt-3 rounded-lg border border-status-red-wash bg-status-red-wash px-3 py-2 text-sm font-semibold text-status-red" role="alert"></p>

                    <label class="mt-4 block text-sm font-semibold text-green-deep">Assign to Staff <span class="text-status-red">*</span></label>
                    <select x-model="staffId" class="mt-1 w-full rounded-lg border border-hairline-strong bg-paper px-3 py-2.5 text-sm shadow-sm focus:border-green focus:outline-none focus:ring-2 focus:ring-green/20">
                        <option value="">Select a staff member…</option>
                        @foreach($assignableStaff as $member)
                            <option value="{{ $member->id }}">{{ $member->name }}</option>
                        @endforeach
                    </select>

                    <div class="mt-4 flex items-center justify-between gap-3">
                        <button type="button" @click="denyOpen = true" :disabled="submitting"
                                class="cr-btn cr-btn-danger !px-5 !py-2.5 !text-sm disabled:cursor-not-allowed disabled:opacity-50">
                            Deny
                        </button>
                        <button type="button" @click="approve()" :disabled="!staffId || submitting"
                                class="cr-btn cr-btn-primary !px-5 !py-2.5 !text-sm disabled:cursor-not-allowed disabled:opacity-50">
                            <span x-show="!submitting">Approve</span>
                            <span x-show="submitting">Approving…</span>
                        </button>
                    </div>

                    {{-- Deny reason modal --}}
                    <div x-show="denyOpen" x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
                         @keydown.escape.window="denyOpen = false" @click.self="denyOpen = false">
                        <div class="w-full max-w-md rounded-2xl border border-hairline bg-paper shadow-xl" x-show="denyOpen" x-transition>
                            <div class="border-b border-hairline px-6 py-4">
                                <h3 class="text-base font-bold text-ink">Deny request</h3>
                                <p class="mt-0.5 text-sm text-ink-soft">This rejects the request. You can add an optional reason for the record.</p>
                            </div>
                            <div class="px-6 py-5">
                                <label class="block text-sm font-semibold text-ink">Reason <span class="font-normal text-ink-soft">(optional)</span></label>
                                <textarea x-model="denyReason" rows="4" maxlength="1000" placeholder="e.g. Incomplete requirements, duplicate request…"
                                          class="mt-1 w-full rounded-lg border border-hairline-strong bg-paper px-3 py-2.5 text-sm shadow-sm focus:border-status-red focus:outline-none focus:ring-2 focus:ring-status-red/20"></textarea>
                            </div>
                            <div class="flex items-center justify-end gap-3 border-t border-hairline px-6 py-4">
                                <button type="button" @click="denyOpen = false" :disabled="submitting"
                                        class="cr-btn !px-5 !py-2.5 !text-sm disabled:opacity-50">
                                    Cancel
                                </button>
                                <button type="button" @click="confirmDeny()" :disabled="submitting"
                                        class="cr-btn cr-btn-danger !border-status-red !bg-status-red !text-white hover:!bg-[#6e1f1f] !px-5 !py-2.5 !text-sm disabled:cursor-not-allowed disabled:opacity-50">
                                    <span x-show="!submitting">Deny request</span>
                                    <span x-show="submitting">Denying…</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ── Final approve (assigned staff) — only at Approved stage ─────── --}}
            @if($isStaffApprovable)
                <div class="mt-5 rounded-[10px] border border-hairline-strong bg-green-wash/40 p-5"
                     x-data="{
                        submitting: false,
                        islandError: '',
                        complete() {
                            if (this.submitting) return;
                            this.submitting = true;
                            fetch('{{ route('documents.review.complete', $document) }}', {
                                method: 'PATCH',
                                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                            }).then(r => {
                                if (r.ok) { window.location.href = '{{ route('track.show', $document->tracking_number) }}'; }
                                else { this.submitting = false; r.json().then(d => { this.islandError = d.message || 'Could not complete. Advance to Approved first.'; }); }
                            }).catch(() => { this.submitting = false; this.islandError = 'Network error. Please try again.'; });
                        }
                     }">
                    <p class="text-sm font-bold text-green-deep">Ready to complete</p>
                    <p class="mt-1 text-sm text-ink-soft">This request is at <strong>Approved</strong>. Approving marks it <strong>Completed</strong> and moves it to your History.</p>
                    <p x-show="islandError" x-cloak x-text="islandError" class="mt-3 rounded-lg border border-status-red-wash bg-status-red-wash px-3 py-2 text-sm font-semibold text-status-red" role="alert"></p>
                    <div class="mt-4 flex justify-end">
                        <button type="button" @click="complete()" :disabled="submitting"
                                class="cr-btn cr-btn-primary !px-5 !py-2.5 !text-sm disabled:cursor-not-allowed disabled:opacity-50">
                            <span x-show="!submitting">Approve</span>
                            <span x-show="submitting">Approving…</span>
                        </button>
                    </div>
                </div>
            @endif

            @if(session('status'))
                <div class="mt-4 rounded-lg border border-hairline bg-green-wash px-4 py-2 text-sm font-semibold text-green-deep">{{ session('status') }}</div>
            @endif

            @if($document->attachments->isNotEmpty())
                <div class="mt-5">
                    <p class="text-[14px] font-bold text-ink">Attached Files</p>
                    <x-document-images :document="$document" :limit="12" size="lg" class="mt-2" :manage="true" />
                    <p class="mt-1 text-[12px] text-ink-soft">Hover a file and tap × to remove one placed by mistake.</p>

                    {{-- PDFs open in the built-in editor, where the staff member can
                         place their registered e-signature. Saving writes a new
                         version and keeps the citizen's original on file. --}}
                    @php
                        $editablePdfs = \App\Support\AssignmentScope::userCanEditDocument($document)
                            ? $document->attachments->filter(fn ($a) => strtolower(pathinfo((string) $a->file_path, PATHINFO_EXTENSION)) === 'pdf')
                            : collect();
                    @endphp
                    @if($editablePdfs->isNotEmpty())
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            @foreach($editablePdfs as $pdf)
                                <a href="{{ route('attachments.edit', $pdf) }}" class="cr-btn cr-btn-sm">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.86 4.49 1.69-1.69a1.875 1.875 0 1 1 2.65 2.65L10.58 16.07a4.5 4.5 0 0 1-1.9 1.13L6 18l.8-2.69a4.5 4.5 0 0 1 1.13-1.9l8.93-8.92Z"/></svg>
                                    Edit &amp; sign PDF {{ $editablePdfs->count() > 1 ? '#'.$loop->iteration : '' }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            @if($document->requirements->isNotEmpty())
                @php $canVerify = auth()->check() && $document->canBeAdvancedBy(auth()->user()); @endphp
                <div class="mt-6">
                    <p id="panel-attachments" class="scroll-mt-6 text-[14px] font-bold text-ink">Requirements</p>
                    <p class="mt-0.5 text-[12px] text-ink-soft">Review each supporting document. Returning or rejecting one emails the citizen your comment; returned items can be re-uploaded from their tracking page.</p>
                    <ul class="mt-2 divide-y divide-hairline overflow-hidden rounded-[10px] border border-hairline">
                        @foreach($document->requirements as $req)
                            @php
                                $badge = match($req->review_status) {
                                    'approved' => 'bg-green-wash text-green-deep',
                                    'needs_revision' => 'bg-status-amber-wash text-status-amber',
                                    'rejected' => 'bg-status-red-wash text-status-red',
                                    default => 'bg-hairline/40 text-ink-soft',
                                };
                            @endphp
                            @php
                                $fileUrl = $req->uploaded_file_path
                                    ? route('documents.requirements.file', [$document, $req])
                                    : null;
                                $extension = strtolower(pathinfo((string) $req->uploaded_file_path, PATHINFO_EXTENSION));
                                $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'heic'], true);
                                $isPdf = $extension === 'pdf';
                            @endphp
                            <li class="p-3 {{ $req->isApproved() ? 'bg-green-wash/40' : '' }}">
                                {{-- Row 1: what it is, where it stands --}}
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-[13px] font-semibold text-ink">{{ $req->label }}</span>
                                            <span class="text-[10.5px] font-semibold {{ $req->is_mandatory ? 'text-status-red' : 'text-ink-soft' }}">{{ $req->is_mandatory ? 'Required' : 'Optional' }}</span>
                                            <span class="rounded-full px-2 py-0.5 text-[10.5px] font-semibold {{ $badge }}">{{ $req->reviewStatusLabel() }}</span>
                                        </div>
                                        @if($req->isVerified())
                                            <p class="mt-1 text-[11px] font-medium text-green-deep">✓ Original verified{{ $req->verifier ? ' by '.$req->verifier->name : '' }} · {{ $req->verified_at?->format('M d, Y g:i A') }}</p>
                                        @endif
                                        @if($req->review_comment)
                                            <p class="mt-1 text-[12px] text-ink-soft"><span class="font-semibold">Comment:</span> {{ $req->review_comment }}</p>
                                        @endif
                                    </div>
                                    @if($fileUrl)
                                        <a href="{{ $fileUrl }}" target="_blank" rel="noopener" class="cr-btn cr-btn-sm shrink-0">
                                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-8.5M14 3h7v7M10 14 21 3"/></svg>
                                            Full size
                                        </a>
                                    @endif
                                </div>

                                {{-- Row 2: the upload itself, shown rather than linked --}}
                                @if($isImage)
                                    <a href="{{ $fileUrl }}" target="_blank" rel="noopener"
                                       class="mt-2 block overflow-hidden rounded-[8px] border border-hairline bg-white transition hover:border-green">
                                        <img src="{{ $fileUrl }}" alt="Upload for {{ $req->label }}" loading="lazy"
                                             class="max-h-64 w-full bg-hairline/20 object-contain">
                                    </a>
                                @elseif($isPdf)
                                    {{-- Inline PDF: the browser's own viewer, scrollable in place. --}}
                                    <object data="{{ $fileUrl }}" type="application/pdf"
                                            class="mt-2 h-64 w-full rounded-[8px] border border-hairline bg-white">
                                        <p class="p-3 text-[12px] text-ink-soft">
                                            This browser cannot display the PDF inline —
                                            <a href="{{ $fileUrl }}" target="_blank" rel="noopener" class="font-semibold text-green underline">open it in a new tab</a>.
                                        </p>
                                    </object>
                                @elseif($fileUrl)
                                    {{-- Anything else (docx, xlsx…): no browser preview exists. --}}
                                    <a href="{{ $fileUrl }}" target="_blank" rel="noopener"
                                       class="mt-2 flex items-center gap-2 rounded-[8px] border border-hairline bg-white px-3 py-2.5 text-[12.5px] font-medium text-ink transition hover:border-green">
                                        <svg class="h-4 w-4 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l4 4v14H7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13 3v5h5"/></svg>
                                        Open {{ $extension !== '' ? strtoupper($extension).' file' : 'file' }}
                                    </a>
                                @else
                                    <p class="mt-2 rounded-[8px] border border-dashed border-hairline-strong bg-hairline/10 px-3 py-2.5 text-[12px] text-ink-soft">
                                        Nothing uploaded — check the original over the counter.
                                    </p>
                                @endif

                                {{-- Row 3: the decision. Each button opens the shared
                                     dialog below — confirm for approve, comment for
                                     the two that email the citizen. Once approved,
                                     the row is settled and the actions drop away. --}}
                                @if($canVerify && ! $req->isApproved())
                                    @php
                                        $reviewAction = route('documents.requirements.review', [$document, $req]);
                                    @endphp
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <button type="button" class="cr-btn cr-btn-sm cr-btn-primary"
                                                @click="$dispatch('requirement-review', { mode: 'approved', label: @js($req->label), action: @js($reviewAction), comment: @js($req->review_comment) })">
                                            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                                            Approve &amp; verify original
                                        </button>
                                        <button type="button" class="cr-btn cr-btn-sm"
                                                @click="$dispatch('requirement-review', { mode: 'needs_revision', label: @js($req->label), action: @js($reviewAction), comment: @js($req->review_comment) })">
                                            Needs revision
                                        </button>
                                        <button type="button" class="cr-btn cr-btn-sm cr-btn-danger"
                                                @click="$dispatch('requirement-review', { mode: 'rejected', label: @js($req->label), action: @js($reviewAction), comment: @js($req->review_comment) })">
                                            Reject
                                        </button>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-6 flex flex-wrap gap-2">
                @if($document->isInternal() && auth()->check())
                    <a href="{{ route('requests.show', $document) }}" class="cr-btn cr-btn-primary">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M9 8h6M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2z"/></svg>
                        Open internal request chain
                    </a>
                @endif
            </div>

            {{-- Predictive insights (self-hosted analytics) --}}
            @if(!empty($anomaly))
                <div class="mt-6 rounded-[10px] border {{ $anomaly['severity'] === 'high' ? 'border-status-red-wash bg-status-red-wash text-status-red' : 'border-status-amber-wash bg-status-amber-wash text-status-amber' }} p-4">
                    <div class="flex items-start gap-3">
                        <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86l-8.18 14.14A2 2 0 0 0 3.83 21h16.34a2 2 0 0 0 1.72-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-bold">Anomaly: moving unusually slowly</p>
                            <p class="mt-0.5 text-sm">
                                Sitting here <strong>{{ $anomaly['elapsed_hours'] }}h</strong>{{ $anomaly['expected_hours'] ? ' — similar documents typically take ~'.$anomaly['expected_hours'].'h' : '' }}.
                                That is <strong>{{ $anomaly['over_by_hours'] }}h</strong> over the normal range.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="mt-6">
                <x-eta-estimate :prediction="$prediction ?? null" :document="$document" />
            </div>

            <div class="panel mt-8">
                <div class="ph"><h2>Logs</h2></div>
                <div class="pb space-y-2">
                    @foreach($timeline as $log)
                        <div class="flex items-center justify-between border-b border-hairline py-2 last:border-b-0">
                            <div class="flex items-center gap-3">
                                <span class="h-3 w-3 rounded-full bg-green-bright"></span>
                                <span class="text-[14px] text-ink-soft">{{ $log['event'] }}</span>
                            </div>
                            <span class="text-[13px] font-bold text-green-deep">{{ $log['timestamp'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- ── Collaboration feed (staff) ─────────────────────────────────── --}}
            @php
                $u = auth()->user();
                $canPost = $u && ($u->can('manage system') || $u->can('assign documents') || (
                    $u->can('advance documents') && $document->assigned_to !== null
                    && (int) $document->assigned_to === (int) $u->id
                ));
            @endphp
            <div class="panel mt-8" id="collab" data-document-id="{{ $document->id }}">
                <div class="ph">
                    <div>
                        <h2>Collaboration</h2>
                        <p class="sub mt-0.5">Two threads, one place. <strong>Internal</strong> notes are staff-only — ask a question here and the assignee is notified. <strong>Visible to citizen</strong> messages go to the tracking page and the citizen can reply, right in this feed.</p>
                    </div>
                </div>

                <div class="pb">
                    @if($canPost)
                        <form id="panel-messages" method="POST" action="{{ route('documents.comments.store', $document) }}" enctype="multipart/form-data" class="scroll-mt-6 rounded-lg border border-hairline bg-paper p-4">
                            @csrf
                            <textarea name="body" rows="3" required maxlength="5000" placeholder="Write an update, or ask the assignee a question…"
                                      class="w-full rounded-lg border border-hairline-strong bg-paper px-3 py-2 text-sm focus:border-green focus:outline-none focus:ring-2 focus:ring-green/20"></textarea>
                            <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-center gap-4 text-sm text-ink">
                                    <label class="flex items-center gap-1.5"><input type="radio" name="visibility" value="internal" checked class="accent-green"> Internal note</label>
                                    <label class="flex items-center gap-1.5"><input type="radio" name="visibility" value="public" class="accent-green"> Visible to citizen</label>
                                </div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <label class="text-xs text-ink-soft">
                                        <span class="sr-only">Attach a file</span>
                                        <input type="file" name="attachment" accept="{{ \App\Support\UploadRules::accept() }}"
                                               class="block text-xs text-ink-soft file:mr-2 file:rounded-lg file:border-0 file:bg-green-wash file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-green-deep">
                                    </label>
                                    <button type="submit" class="cr-btn cr-btn-primary">Post</button>
                                </div>
                            </div>
                            @error('attachment')<p class="mt-2 text-sm font-semibold text-status-red">{{ $message }}</p>@enderror
                        </form>
                    @endif

                    {{-- Top-level messages only; each renders its own replies, so a
                         question and its answers stay together. --}}
                    <div id="collabFeed" class="mt-4 space-y-3">
                        @forelse($document->comments->whereNull('parent_id') as $comment)
                            @include('track.partials.comment', ['comment' => $comment, 'canPost' => $canPost, 'document' => $document])
                        @empty
                            <p id="collabEmpty" class="text-sm italic text-ink-soft">No messages yet. Post an update, or ask the assignee a question.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- Opening an assigned, in-progress request (clicking it) moves it to
         "In Review", mirroring the old Review action. Reloads once so the badge
         and stepper reflect the new stage. --}}
    @if(($isStaffAssigned ?? false) && $document->status === 'in_progress')
        <script>
            fetch('{{ route('documents.review.open', $document) }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            }).then(r => r.ok ? r.json() : null).then(d => {
                if (d && d.status === 'in_review') { window.location.reload(); }
            }).catch(() => {});
        </script>
    @endif

    <script>
        (function () {
            const root = document.getElementById('collab');
            if (!root || !window.Echo) return;
            const id = root.dataset.documentId;
            const feed = document.getElementById('collabFeed');
            const badge = {
                internal: 'background:#eef2ef;color:#5b6b62;',
                public: 'background:#eef5f0;color:#0f5c2e;',
                citizen: 'background:#e7f0fb;color:#1d4e89;',
                system: 'background:#fbf3e0;color:#8a6d1f;',
            };

            /**
             * Live messages on the staff channel — both threads arrive here,
             * including anything the citizen just posted. A reply is threaded
             * under the message it answers; new top-level messages go to the end,
             * the way a conversation reads.
             */
            window.Echo.private('documents.' + id + '.staff').listen('.comment', (e) => {
                if (document.getElementById('comment-' + e.id)) return;
                document.getElementById('collabEmpty')?.remove();

                const tag = e.author_type === 'system'
                    ? 'system'
                    : (e.author_type === 'citizen' ? 'citizen' : (e.visibility || 'internal'));
                const parent = e.parent_id ? document.getElementById('comment-' + e.parent_id) : null;

                const el = document.createElement('div');
                el.id = 'comment-' + e.id;

                if (parent) {
                    el.className = 'rounded-lg p-2 ' + (tag === 'citizen' ? 'bg-[#eef5fd]' : 'bg-green-wash/40');
                    el.innerHTML = '<div class="flex items-center justify-between">' +
                        '<span class="text-[12px] font-bold text-ink"><span data-author></span>' +
                        ' <span class="ml-1 text-[10px] font-semibold text-ink-soft">· ' + tag + '</span></span>' +
                        '<span class="text-[11px] text-ink-soft" data-time></span></div>' +
                        '<p class="mt-0.5 whitespace-pre-wrap text-[13px] text-ink" data-body></p>';
                } else {
                    el.className = tag === 'citizen'
                        ? 'rounded-xl border-2 border-[#bcd6f5] bg-[#f7fbff] p-3'
                        : 'rounded-xl border border-hairline bg-paper p-3';
                    el.innerHTML = '<div class="flex items-center justify-between">' +
                        '<span class="text-[13px] font-bold text-ink"><span data-author></span>' +
                        ' <span class="ml-1 rounded-full px-2 py-0.5 text-[10px] font-semibold" style="' + (badge[tag] || badge.internal) + '">' + tag + '</span></span>' +
                        '<span class="text-[12px] text-ink-soft" data-time></span></div>' +
                        '<p class="mt-1 whitespace-pre-wrap text-[14px] text-ink" data-body></p>';
                }

                // textContent for anything author-supplied.
                el.querySelector('[data-author]').textContent = e.author || 'Staff';
                el.querySelector('[data-time]').textContent = e.timestamp || '';
                el.querySelector('[data-body]').textContent = e.body;

                if (parent) {
                    let replies = parent.querySelector('[data-replies]');
                    if (!replies) {
                        replies = document.createElement('div');
                        replies.dataset.replies = '';
                        replies.className = 'mt-3 space-y-2 border-l-2 border-hairline pl-3';
                        parent.appendChild(replies);
                    }
                    replies.appendChild(el);
                } else {
                    feed.appendChild(el);
                }
            });
        })();
    </script>
    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const base = @json(url('/documents'));
            document.querySelectorAll('.js-track-complete').forEach(btn => {
                btn.addEventListener('click', async function () {
                    if (!confirm('Mark this document as completed?')) return;
                    btn.disabled = true;
                    const res = await fetch(base + '/' + encodeURIComponent(this.dataset.tracking) + '/complete', {
                        method: 'PATCH',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    });
                    if (res.ok) location.reload();
                    else { alert('Could not complete document.'); btn.disabled = false; }
                });
            });
        })();
    </script>

    {{-- ── Requirement decision dialog ──────────────────────────────────────
         One dialog serves every requirement row: a confirmation for approve,
         and a remarks box for the two outcomes that email the citizen. The
         comment is also validated server-side, so a bypassed dialog still
         cannot return an item without a reason. --}}
    <div x-data="requirementReview()"
         x-show="open"
         x-cloak
         @requirement-review.window="start($event.detail)"
         @keydown.escape.window="close()"
         class="fixed inset-0 z-[60] flex items-center justify-center bg-ink/40 p-4"
         role="dialog"
         aria-modal="true"
         :aria-label="heading()">
        <div @click.outside="close()" class="w-full max-w-md rounded-2xl border border-hairline bg-paper p-5 shadow-2xl">
            <h2 class="text-base font-bold text-ink" x-text="heading()"></h2>
            <p class="mt-1 text-[13px] text-ink-soft" x-text="blurb()"></p>

            <form method="POST" :action="action" @submit="submitting = true">
                @csrf
                <input type="hidden" name="review_status" :value="mode">

                <template x-if="needsComment()">
                    <div class="mt-3">
                        <label for="requirement-review-comment" class="text-[12px] font-semibold text-ink">
                            Remarks <span class="text-status-red">*</span>
                        </label>
                        <textarea id="requirement-review-comment" x-ref="comment" name="review_comment" rows="3" required
                                  x-model="comment"
                                  :placeholder="mode === 'rejected' ? 'Why is this being rejected?' : 'What does the citizen need to correct?'"
                                  class="mt-1 w-full rounded-[8px] border border-hairline-strong bg-white px-2 py-1.5 text-[12.5px] text-ink focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25"></textarea>
                        <p class="mt-1 text-[11px] text-ink-soft">This is emailed to the citizen.</p>
                    </div>
                </template>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="close()" class="cr-btn cr-btn-sm">Cancel</button>
                    <button type="submit"
                            :disabled="! canSubmit() || submitting"
                            :class="mode === 'approved' ? 'cr-btn-primary' : (mode === 'rejected' ? 'cr-btn-danger' : '')"
                            class="cr-btn cr-btn-sm"
                            x-text="confirmLabel()"></button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function requirementReview() {
            return {
                open: false,
                submitting: false,
                mode: '',
                label: '',
                action: '',
                comment: '',

                start(detail) {
                    this.mode = detail.mode;
                    this.label = detail.label;
                    this.action = detail.action;
                    this.comment = detail.comment || '';
                    this.submitting = false;
                    this.open = true;

                    this.$nextTick(() => this.$refs.comment?.focus());
                },

                close() {
                    this.open = false;
                },

                /** Approve is a confirmation; the other two need a reason. */
                needsComment() {
                    return this.mode !== 'approved';
                },

                canSubmit() {
                    return ! this.needsComment() || this.comment.trim().length > 0;
                },

                heading() {
                    if (this.mode === 'approved') {
                        return 'Approve and verify this requirement?';
                    }

                    return this.mode === 'rejected' ? 'Reject this requirement' : 'Return this requirement for revision';
                },

                blurb() {
                    if (this.mode === 'approved') {
                        return `“${this.label}” will be marked approved and its original recorded as verified by you.`;
                    }

                    if (this.mode === 'rejected') {
                        return `“${this.label}” will be rejected and the citizen emailed your remarks.`;
                    }

                    return `“${this.label}” will be sent back so the citizen can re-upload it, with your remarks emailed to them.`;
                },

                confirmLabel() {
                    if (this.mode === 'approved') {
                        return 'Approve & verify';
                    }

                    return this.mode === 'rejected' ? 'Reject & notify' : 'Return & notify';
                },
            };
        }
    </script>

    {{-- ── Scan-to-open dialog (opened from the "Scan QR" title action) ─────
         Reuses window.SpeedQr so the accepted-code rules match every other
         scanner in the app: only codes this system issued are acted on. --}}
    @include('partials.qr-scan-helpers')

    <div x-data="lookupScanner()"
         x-show="open"
         x-cloak
         @lookup-scan-open.window="start()"
         @keydown.escape.window="close()"
         class="fixed inset-0 z-[60] flex items-center justify-center bg-ink/40 p-4"
         role="dialog"
         aria-modal="true"
         aria-label="Scan a request QR code">
        <div @click.outside="close()" class="w-full max-w-md rounded-2xl border border-hairline bg-paper p-5 shadow-2xl">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-ink">Scan a request</h2>
                    <p class="mt-0.5 text-[13px] text-ink-soft">Point the camera at the QR on the claim slip or folder.</p>
                </div>
                <button type="button" @click="close()" class="cr-btn cr-btn-sm" aria-label="Close scanner">Close</button>
            </div>

            <div id="lookupScanRegion" class="mt-4 min-h-[220px] overflow-hidden rounded-xl border-2 border-dashed border-green/40 bg-green-wash/40"></div>

            <p x-show="error" x-text="error" x-cloak class="mt-3 text-[13px] text-status-red"></p>

            {{-- Typing the number is the fallback when there is no camera. --}}
            <div class="mt-4 flex items-center gap-2">
                <div class="field flex-1">
                    <input type="text" x-model="manual" @keydown.enter.prevent="go(manual)"
                           placeholder="or type the tracking number"
                           aria-label="Tracking number" class="w-full uppercase">
                </div>
                <button type="button" @click="go(manual)" class="cr-btn cr-btn-primary">Open</button>
            </div>
        </div>
    </div>

    <script>
        function lookupScanner() {
            return {
                open: false,
                error: '',
                manual: '',

                start() {
                    this.open = true;
                    this.error = '';

                    this.$nextTick(() => {
                        window.SpeedQr.hasCamera().then((available) => {
                            if (! available) {
                                // Distinguishes "this device has no camera" from
                                // "the browser hides cameras on an insecure origin".
                                this.error = window.SpeedQr.describe(new DOMException('No camera.', 'NotFoundError'))
                                    + ' You can type the tracking number instead.';

                                return;
                            }

                            window.SpeedQr.start(
                                'lookupScanRegion',
                                (text) => this.go(window.SpeedQr.extractTracking(text), true),
                                (cameraError) => { this.error = window.SpeedQr.describe(cameraError); },
                            );
                        });
                    });
                },

                /** Navigate to a scanned/typed code, refusing anything foreign. */
                go(code, fromScan = false) {
                    const tracking = fromScan ? code : window.SpeedQr.extractTracking(code);

                    if (! tracking) {
                        this.error = fromScan ? window.SpeedQr.FOREIGN_CODE_MESSAGE : 'That does not look like a tracking number.';

                        return;
                    }

                    window.SpeedQr.stop();
                    window.location = @json(url('/track')) + '/' + encodeURIComponent(tracking);
                },

                close() {
                    if (! this.open) {
                        return;
                    }

                    window.SpeedQr.stop();
                    this.open = false;
                    this.error = '';
                },
            };
        }
    </script>
</x-app-layout>
