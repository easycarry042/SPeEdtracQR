<x-app-layout>
    {{-- Figma: STAFF BOOKING. A month calendar on the left (707 of the frame's
         1512), and two 379-wide side panels — what is booked on the selected day,
         and what is coming up. The layout supplies the sidebar, the "Booking"
         title and the header controls, so this page is only the two columns. --}}
    <div class="page-shell"
         x-data="bookingCalendar({ meta: @js($dateMeta), selected: @js($defaultDate), today: @js($today) })">

        @if(session('status'))
            <div class="bk-panel !rounded-[14px] px-4 py-3 text-[13px] font-medium text-green-deep" role="status">{{ session('status') }}</div>
        @endif
        @if(session('error'))
            <div class="bk-panel !rounded-[14px] px-4 py-3 text-[13px] font-medium text-status-red" style="border-color:var(--red)" role="alert">{{ session('error') }}</div>
        @endif

        <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_379px]">

            {{-- ── Calendar ──────────────────────────────────────────────────── --}}
            <section class="bk-panel px-4 pb-4 pt-6 sm:px-6 sm:pb-6" aria-label="Reservation calendar">

                {{-- Month, with the two arrows the frame places either side of it.
                     "Today" is kept as a third control — a staff member who has
                     paged three months out otherwise has to count back — and it
                     sits in its own column, with a matching spacer opposite, so
                     the month stays centred in the panel as drawn. --}}
                <div class="flex items-center justify-between gap-2">
                    <button type="button" @click="goToday()" class="cr-btn cr-btn-sm shrink-0">Today</button>

                    <div class="flex items-center justify-center gap-4 sm:gap-6">
                    <button type="button" @click="prev()" aria-label="Previous month"
                            class="flex h-10 w-10 items-center justify-center rounded-full text-green transition hover:bg-green-wash focus:outline-none focus-visible:ring-2 focus-visible:ring-green">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>

                    <h2 class="bk-month whitespace-nowrap text-center" x-text="monthLabel"></h2>

                    <button type="button" @click="next()" aria-label="Next month"
                            class="flex h-10 w-10 items-center justify-center rounded-full text-green transition hover:bg-green-wash focus:outline-none focus-visible:ring-2 focus-visible:ring-green">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    </div>

                    {{-- Balances the Today button, aria-hidden because it is
                         nothing but the space that keeps the month centred. --}}
                    <span class="hidden w-[74px] shrink-0 sm:block" aria-hidden="true"></span>
                </div>

                {{-- Weekday heads. Sunday is red, as drawn. --}}
                <div class="mt-8 grid grid-cols-7 text-center">
                    @foreach(['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'] as $index => $dow)
                        <div @class(['bk-dow pb-4', 'bk-dow-sun' => $index === 0])>{{ $dow }}</div>
                    @endforeach
                </div>

                {{-- Weeks. Each row is a hairline apart; the days either side of
                     the month are shown muted rather than left blank, so the grid
                     never has holes in it. --}}
                <template x-for="(week, wi) in weeks" :key="wi">
                    <div class="bk-week grid grid-cols-7">
                        <template x-for="(cell, di) in week" :key="cell.iso">
                            <div>
                                <button type="button" @click="select(cell.iso)"
                                        class="bk-day"
                                        :class="{
                                            'bk-day-on': cell.iso === selected,
                                            'bk-day-out': cell.outside,
                                            'bk-day-sun': di === 0,
                                            'bk-day-has': cell.meta && !cell.outside
                                        }"
                                        :aria-pressed="cell.iso === selected"
                                        :aria-label="cell.label + (cell.meta && !cell.outside ? ', ' + cell.meta.count + (cell.meta.count === 1 ? ' booking' : ' bookings') + (cell.meta.conflict ? ', with an overlap to resolve' : '') : ', no bookings')">
                                    <span class="bk-day-n" x-text="cell.day"></span>

                                    {{-- How many reservations, and whether any of
                                         them clash. Nothing is drawn on an empty
                                         day: the frame's grid is quiet on purpose.
                                         Just the dot and the number — a cell is
                                         only ~100px wide, and the word is carried
                                         by the button's aria-label instead. --}}
                                    <template x-if="cell.meta && !cell.outside">
                                        <span class="bk-count" :class="cell.meta.conflict ? 'is-clash' : ''" x-text="cell.meta.count"></span>
                                    </template>
                                </button>
                            </div>
                        </template>
                    </div>
                </template>
            </section>

            {{-- ── Side panels ───────────────────────────────────────────────── --}}
            <div class="flex flex-col gap-5">

                {{-- Booked Today (or the day picked on the calendar) ─────────── --}}
                <section class="bk-panel flex min-h-[409px] flex-col px-6 py-6" aria-label="Reservations on the selected day">
                    <h2 class="bk-title" x-text="selected === today ? 'Booked Today' : 'Booked ' + shortLabel"></h2>

                    <div class="mt-4 min-h-0 flex-1 overflow-y-auto pr-1">
                        {{-- Nothing on the day ------------------------------------ --}}
                        <template x-if="!meta[selected]">
                            <div class="bk-empty">
                                {{-- book-bookmark (Streamline Freehand), redrawn as
                                     an inline outline so it inherits the muted ink
                                     and needs no network request. --}}
                                <svg class="h-[60px] w-[60px]" viewBox="0 0 60 60" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                    <rect x="11" y="8" width="38" height="44" rx="3.5"/>
                                    <path stroke-linecap="round" d="M18 8v44"/>
                                    <path stroke-linejoin="round" d="M33 8v20l6.5-5 6.5 5V8"/>
                                </svg>
                                <p x-text="selected === today ? 'There are no reservations today' : 'There are no reservations on this day'"></p>
                            </div>
                        </template>

                        {{-- One hidden group per date; Alpine reveals the selected
                             one, so switching days costs no round trip. --}}
                        @foreach($byDate as $date => $dayBookings)
                            <div x-show="selected === @js($date)" x-cloak class="space-y-3">
                                @foreach($dayBookings as $b)
                                    @php $conflict = in_array($b->id, $conflictIds, true); @endphp
                                    <div x-data="{ resched: false }"
                                         class="rounded-[15px] border p-3 {{ $conflict ? 'border-status-red bg-status-red-wash/40' : 'border-hairline bg-white/70' }}">
                                        <p class="text-[14px] font-bold text-ink">{{ $b->resource?->name ?? 'Resource' }}</p>
                                        <p class="mt-0.5 text-[13px] text-ink">
                                            {{ $b->starts_at->format('g:i A') }} – {{ $b->starts_at->isSameDay($b->ends_at) ? $b->ends_at->format('g:i A') : $b->ends_at->format('M j, g:i A') }}
                                        </p>
                                        <p class="mt-0.5 text-[12.5px] text-ink-soft">
                                            Requested by {{ $b->document?->citizen_name ?: 'Requester' }}
                                        </p>
                                        @if($b->document)
                                            <a href="{{ route('track.show', $b->document->tracking_number) }}"
                                               class="mt-0.5 inline-block font-mono text-[12px] text-green underline">{{ $b->document->tracking_number }}</a>
                                        @endif

                                        <div class="mt-2 flex flex-wrap items-center gap-2">
                                            <span class="pill {{ $b->status === 'approved' ? 'p-green' : 'p-amber' }}">{{ ucfirst($b->status) }}</span>
                                            @if($b->document?->quantity)<span class="pill p-green">Qty: {{ number_format($b->document->quantity) }}</span>@endif
                                            @if($conflict)<span class="pill p-red">Overlaps another booking</span>@endif
                                        </div>

                                        <div class="mt-3 flex flex-wrap items-center gap-2">
                                            @if($b->status !== 'approved')
                                                <form method="POST" action="{{ route('bookings.approve', $b) }}">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="cr-btn cr-btn-sm cr-btn-primary">Approve</button>
                                                </form>
                                            @endif
                                            <button type="button" @click="resched = !resched" class="cr-btn cr-btn-sm">Reschedule</button>
                                            <form method="POST" action="{{ route('bookings.cancel', $b) }}" onsubmit="return confirm('Cancel this booking? The slot will be freed.');">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="cr-btn cr-btn-sm cr-btn-danger">Cancel</button>
                                            </form>
                                        </div>

                                        <form x-show="resched" x-cloak method="POST" action="{{ route('bookings.reschedule', $b) }}"
                                              class="mt-3 space-y-2 border-t border-hairline pt-3">
                                            @csrf @method('PATCH')
                                            <div>
                                                <label class="block text-[10px] font-semibold uppercase tracking-wide text-ink-soft">New start</label>
                                                <input type="datetime-local" name="starts_at" required value="{{ $b->starts_at->format('Y-m-d\TH:i') }}"
                                                       class="mt-0.5 w-full rounded-[8px] border border-hairline-strong bg-white px-2 py-2 text-[13px] text-ink focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-semibold uppercase tracking-wide text-ink-soft">New end</label>
                                                <input type="datetime-local" name="ends_at" required value="{{ $b->ends_at->format('Y-m-d\TH:i') }}"
                                                       class="mt-0.5 w-full rounded-[8px] border border-hairline-strong bg-white px-2 py-2 text-[13px] text-ink focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25">
                                            </div>
                                            <button type="submit" class="cr-btn cr-btn-sm cr-btn-primary">Save</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- Upcoming — after today, whatever the calendar is showing ──── --}}
                <section class="bk-panel flex min-h-[409px] flex-col px-6 py-6" aria-label="Upcoming reservations">
                    <h2 class="bk-title">Upcoming</h2>

                    <div class="mt-4 min-h-0 flex-1 overflow-y-auto pr-1">
                        @forelse($upcoming as $b)
                            {{-- Each row jumps the calendar to that day, so the
                                 panel above fills in with the day's detail. --}}
                            <button type="button" @click="select(@js($b->starts_at->format('Y-m-d')))"
                                    class="w-full rounded-[15px] border border-hairline bg-white/70 p-3 text-left transition hover:border-green focus:outline-none focus-visible:ring-2 focus-visible:ring-green {{ $loop->first ? '' : 'mt-3' }}">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-[14px] font-bold text-ink">{{ $b->resource?->name ?? 'Resource' }}</p>
                                    <span class="shrink-0 text-[13px] font-semibold text-ink-soft">{{ $b->starts_at->format('m/d/y') }}</span>
                                </div>
                                <p class="mt-0.5 text-[13px] text-ink">{{ $b->starts_at->format('g:i A') }} – {{ $b->ends_at->format('g:i A') }}</p>
                                <p class="mt-0.5 text-[12.5px] text-ink-soft">Requested by {{ $b->document?->citizen_name ?: 'Requester' }}</p>
                                <span class="pill mt-2 {{ $b->status === 'approved' ? 'p-green' : 'p-amber' }}">{{ ucfirst($b->status) }}</span>
                            </button>
                        @empty
                            <div class="bk-empty">
                                {{-- book-flip-page (Streamline Freehand). --}}
                                <svg class="h-[55px] w-[55px]" viewBox="0 0 55 55" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M27.5 12.5C23 9.2 17.6 7.6 12 8v36c5.6-.4 11 1.2 15.5 4.5 4.5-3.3 9.9-4.9 15.5-4.5V8c-5.6-.4-11 1.2-15.5 4.5z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M27.5 12.5v36"/>
                                    <path stroke-linecap="round" d="M17.5 18.5h6M17.5 26.5h6M17.5 34.5h6"/>
                                </svg>
                                <p>There are no upcoming reservations</p>
                            </div>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('bookingCalendar', ({ meta, selected, today }) => ({
                meta: meta || {},
                selected: selected,
                today: today,
                view: null,

                init() {
                    const base = this.selected ? new Date(this.selected + 'T00:00:00') : new Date();
                    this.view = new Date(base.getFullYear(), base.getMonth(), 1);
                },

                iso(d) {
                    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
                },

                get monthLabel() {
                    return this.view.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
                },

                /**
                 * Whole weeks, Sunday-first, including the days either side of the
                 * month — the frame draws those muted rather than leaving the
                 * first and last rows ragged.
                 */
                get weeks() {
                    const year = this.view.getFullYear();
                    const month = this.view.getMonth();
                    const leading = new Date(year, month, 1).getDay();
                    const daysInMonth = new Date(year, month + 1, 0).getDate();
                    const rows = Math.ceil((leading + daysInMonth) / 7);
                    const weeks = [];

                    for (let w = 0; w < rows; w++) {
                        const week = [];
                        for (let d = 0; d < 7; d++) {
                            const date = new Date(year, month, 1 - leading + (w * 7) + d);
                            const iso = this.iso(date);
                            week.push({
                                day: date.getDate(),
                                iso,
                                outside: date.getMonth() !== month,
                                meta: this.meta[iso] || null,
                                label: date.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' }),
                            });
                        }
                        weeks.push(week);
                    }

                    return weeks;
                },

                /** "Sep 11" — the side panel's heading when the day is not today. */
                get shortLabel() {
                    if (!this.selected) { return ''; }

                    return new Date(this.selected + 'T00:00:00')
                        .toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
                },

                select(iso) {
                    if (!iso) { return; }
                    this.selected = iso;
                    // Picking a day in the leading/trailing week moves the month
                    // with it, otherwise the selection disappears off the grid.
                    const picked = new Date(iso + 'T00:00:00');
                    if (picked.getMonth() !== this.view.getMonth() || picked.getFullYear() !== this.view.getFullYear()) {
                        this.view = new Date(picked.getFullYear(), picked.getMonth(), 1);
                    }
                },

                prev() { this.view = new Date(this.view.getFullYear(), this.view.getMonth() - 1, 1); },
                next() { this.view = new Date(this.view.getFullYear(), this.view.getMonth() + 1, 1); },

                goToday() {
                    const t = new Date();
                    this.view = new Date(t.getFullYear(), t.getMonth(), 1);
                    this.selected = this.iso(t);
                },
            }));
        });
    </script>
</x-app-layout>
