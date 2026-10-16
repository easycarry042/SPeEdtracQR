<<<<<<< HEAD
<x-app-layout fixed-height>
    {{-- Booking: a month calendar beside two side cards — what is booked on the
         selected day, and what is coming up. Same card family as the staff
         tables (.bk-card mirrors .st-card); the page fills the window and each
         card scrolls on its own. --}}
=======
<x-app-layout title="Booking">
    {{-- Figma: STAFF BOOKING. A month calendar on the left (707 of the frame's
         1512), and two 379-wide side panels — what is booked on the selected day,
         and what is coming up. The layout supplies the sidebar, the "Booking"
         title and the header controls, so this page is only the two columns. --}}
>>>>>>> 445734fe6c2ab99dcac3772ebcf201eb1141dd91
    <div class="page-shell"
         x-data="bookingCalendar({ meta: @js($dateMeta), selected: @js($defaultDate), today: @js($today) })">

        @if(session('status'))
            <div class="bk-card shrink-0 px-4 py-3 text-[length:var(--fs-small)] font-medium text-green-deep" role="status">{{ session('status') }}</div>
        @endif
        @if(session('error'))
            <div class="bk-card shrink-0 px-4 py-3 text-[length:var(--fs-small)] font-medium text-status-red" style="border-color:var(--red)" role="alert">{{ session('error') }}</div>
        @endif

        <div class="grid min-h-0 flex-1 gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">

            {{-- ── Calendar ──────────────────────────────────────────────────── --}}
            <section class="bk-card min-h-[520px] lg:min-h-0" aria-label="Reservation calendar">
                <div class="bk-card-head">
                    {{-- The month title opens a month/year picker, to jump straight
                         to a month instead of paging to it. --}}
                    <div class="relative" @click.outside="picker = false" @keydown.escape.window="picker = false">
                        <h2>
                            <button type="button" @click="openPicker()"
                                    class="bk-month bk-month-btn"
                                    :aria-expanded="picker" aria-haspopup="dialog"
                                    :aria-label="'Choose month, currently ' + monthLabel">
                                <span x-text="monthLabel"></span>
                                <svg class="h-4 w-4 shrink-0 text-ink-soft transition-transform" :class="picker && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                            </button>
                        </h2>

                        <div x-show="picker" x-cloak x-transition.opacity.duration.150ms
                             class="bk-picker" role="dialog" aria-label="Choose month and year">
                            <div class="flex items-center justify-between">
                                <button type="button" @click="pickerYear--" aria-label="Previous year" class="bk-picker-step">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                                </button>
                                <label class="sr-only" for="bk-picker-year">Year</label>
                                <input id="bk-picker-year" type="number" inputmode="numeric" min="1900" max="2999"
                                       x-model.number="pickerYear" class="bk-picker-year">
                                <button type="button" @click="pickerYear++" aria-label="Next year" class="bk-picker-step">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </button>
                            </div>
                            <div class="mt-3 grid grid-cols-3 gap-1.5">
                                <template x-for="(name, mi) in monthNames" :key="mi">
                                    <button type="button" @click="jumpTo(pickerYear, mi)" x-text="name"
                                            class="bk-picker-month"
                                            :class="{ 'on': mi === view.getMonth() && pickerYear === view.getFullYear() }"
                                            :aria-pressed="mi === view.getMonth() && pickerYear === view.getFullYear()"></button>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- Previous / Today / Next, one joined control. --}}
                    <div class="bk-nav" role="group" aria-label="Change month">
                        <button type="button" @click="prev()" aria-label="Previous month"><svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6"/></svg></button>
                        <button type="button" @click="goToday()">Today</button>
                        <button type="button" @click="next()" aria-label="Next month"><svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/></svg></button>
                    </div>
                </div>

                {{-- Weekday heads. Sunday in red, the non-working day. --}}
                <div class="bk-dows" aria-hidden="true">
                    @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $index => $dow)
                        <div @class(['bk-dow', 'bk-dow-sun' => $index === 0])>{{ $dow }}</div>
                    @endforeach
                </div>

                {{-- Weeks fill the card, sharing its height evenly. Days from the
                     neighbouring months are shaded rather than left blank. --}}
                <div class="bk-weeks">
                    <template x-for="(week, wi) in weeks" :key="wi">
                        <div class="bk-week">
                            <template x-for="(cell, di) in week" :key="cell.iso">
                                <div class="h-full">
                                    <button type="button" @click="select(cell.iso)"
                                            class="bk-day"
                                            :class="{
                                                'bk-day-on': cell.iso === selected,
                                                'bk-day-today': cell.iso === today,
                                                'bk-day-out': cell.outside,
                                                'bk-day-sun': di === 0
                                            }"
                                            :aria-pressed="cell.iso === selected"
                                            :aria-current="cell.iso === today ? 'date' : null"
                                            :aria-label="cell.label + (cell.meta && !cell.outside ? ', ' + cell.meta.count + (cell.meta.count === 1 ? ' booking' : ' bookings') + (cell.meta.conflict ? ', with an overlap to resolve' : '') : ', no bookings')">
                                        <span class="bk-day-n" x-text="cell.day"></span>

                                        {{-- How many reservations, red when two overlap. --}}
                                        <template x-if="cell.meta && !cell.outside">
                                            <span class="bk-count" :class="cell.meta.conflict ? 'is-clash' : ''"
                                                  x-text="cell.meta.count + (cell.meta.count === 1 ? ' booking' : ' bookings')"></span>
                                        </template>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </section>

            {{-- ── Side cards ────────────────────────────────────────────────── --}}
            <div class="flex min-h-0 flex-col gap-5">

                {{-- Booked today (or on the day picked in the calendar) ─────── --}}
                <section class="bk-card min-h-[260px] flex-1" aria-label="Reservations on the selected day">
                    <div class="bk-card-head">
                        <h2 class="bk-title" x-text="selected === today ? 'Booked today' : 'Booked ' + shortLabel"></h2>
                        <span class="bk-head-count" x-text="meta[selected] ? meta[selected].count : 0"></span>
                    </div>

                    <div class="bk-card-body">
                        <template x-if="!meta[selected]">
                            <div class="bk-empty">
                                <span class="bk-empty-icon">
                                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                        <rect x="4" y="5" width="16" height="15" rx="2.5"/>
                                        <path stroke-linecap="round" d="M4 10h16M9 3v4M15 3v4"/>
                                    </svg>
                                </span>
                                <p x-text="selected === today ? 'There are no reservations today' : 'There are no reservations on this day'"></p>
                            </div>
                        </template>

                        {{-- One hidden group per date; Alpine reveals the selected
                             one, so switching days costs no round trip. --}}
                        @foreach($byDate as $date => $dayBookings)
                            <div x-show="selected === @js($date)" x-cloak>
                                @foreach($dayBookings as $b)
                                    @php $conflict = in_array($b->id, $conflictIds, true); @endphp
                                    <div x-data="{ resched: false }" @class(['bk-item', 'is-clash' => $conflict])>
                                        <p class="bk-item-title">{{ $b->resource?->name ?? 'Resource' }}</p>
                                        <p class="bk-item-line">
                                            {{ $b->starts_at->format('g:i A') }} – {{ $b->starts_at->isSameDay($b->ends_at) ? $b->ends_at->format('g:i A') : $b->ends_at->format('M j, g:i A') }}
                                        </p>
                                        <p class="bk-item-meta">
                                            Requested by {{ $b->document?->citizen_name ?: 'Requester' }}
                                            @if($b->document)
                                                · <a href="{{ route('track.show', $b->document->tracking_number) }}"
                                                     class="mono text-green hover:underline">{{ $b->document->tracking_number }}</a>
                                            @endif
                                        </p>

                                        <div class="mt-2 flex flex-wrap items-center gap-1.5">
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
                                                <label class="st-filter-label">New start</label>
                                                <input type="datetime-local" name="starts_at" required value="{{ $b->starts_at->format('Y-m-d\TH:i') }}" class="st-filter-field">
                                            </div>
                                            <div>
                                                <label class="st-filter-label">New end</label>
                                                <input type="datetime-local" name="ends_at" required value="{{ $b->ends_at->format('Y-m-d\TH:i') }}" class="st-filter-field">
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
                <section class="bk-card min-h-[260px] flex-1" aria-label="Upcoming reservations">
                    <div class="bk-card-head">
                        <h2 class="bk-title">Upcoming</h2>
                        <span class="bk-head-count">{{ $upcoming->count() }}</span>
                    </div>

                    <div class="bk-card-body">
                        @forelse($upcoming as $b)
                            {{-- Each row jumps the calendar to that day, so the card
                                 above fills in with the day's detail. --}}
                            <button type="button" @click="select(@js($b->starts_at->format('Y-m-d')))" class="bk-item">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="bk-item-title">{{ $b->resource?->name ?? 'Resource' }}</p>
                                    <span class="shrink-0 text-[length:var(--fs-small)] font-semibold text-ink-soft">{{ $b->starts_at->format('M j') }}</span>
                                </div>
                                <p class="bk-item-line">{{ $b->starts_at->format('g:i A') }} – {{ $b->ends_at->format('g:i A') }}</p>
                                <div class="mt-1.5 flex items-center justify-between gap-2">
                                    <p class="bk-item-meta !mt-0 truncate">Requested by {{ $b->document?->citizen_name ?: 'Requester' }}</p>
                                    <span class="pill shrink-0 {{ $b->status === 'approved' ? 'p-green' : 'p-amber' }}">{{ ucfirst($b->status) }}</span>
                                </div>
                            </button>
                        @empty
                            <div class="bk-empty">
                                <span class="bk-empty-icon">
                                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle cx="12" cy="12" r="8.5"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5V12l3 2"/>
                                    </svg>
                                </span>
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

                // Month/year picker behind the month label.
                picker: false,
                pickerYear: null,
                monthNames: Array.from({ length: 12 }, (_, m) =>
                    new Date(2000, m, 1).toLocaleDateString(undefined, { month: 'short' })),

                openPicker() {
                    this.pickerYear = this.view.getFullYear();
                    this.picker = !this.picker;
                },

                jumpTo(year, month) {
                    const y = parseInt(year, 10);
                    if (!Number.isFinite(y) || y < 1900 || y > 2999) { return; }
                    this.view = new Date(y, month, 1);
                    this.picker = false;
                },

                goToday() {
                    const t = new Date();
                    this.view = new Date(t.getFullYear(), t.getMonth(), 1);
                    this.selected = this.iso(t);
                },
            }));
        });
    </script>
</x-app-layout>
