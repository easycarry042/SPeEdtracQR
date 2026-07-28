<x-app-layout>
    <div class="page-shell page-shell-loose"
         x-data="bookingCalendar({ meta: @js($dateMeta), selected: @js($defaultDate) })">

        @if(session('status'))
            <div class="panel"><div class="pb text-[13px] font-medium text-green-deep">{{ session('status') }}</div></div>
        @endif
        @if(session('error'))
            <div class="panel" style="border-color:var(--red)"><div class="pb text-[13px] font-medium text-status-red">{{ session('error') }}</div></div>
        @endif

        <p class="text-[13px] text-ink-soft">Resource reservations by day. Pick a date to see its bookings; a red dot marks a day with an overlap to resolve.</p>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]">

            {{-- ── Calendar ──────────────────────────────────────────────────── --}}
            <div class="panel">
                <div class="ph">
                    <div class="flex items-center gap-2">
                        <button type="button" @click="prev()" aria-label="Previous month" class="cr-btn cr-btn-sm">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <h2 class="min-w-[9rem] text-center" x-text="monthLabel"></h2>
                        <button type="button" @click="next()" aria-label="Next month" class="cr-btn cr-btn-sm">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                    <button type="button" @click="goToday()" class="cr-btn cr-btn-sm">Today</button>
                </div>
                <div class="pb">
                    {{-- Weekday header --}}
                    <div class="grid grid-cols-7 gap-1 text-center text-[10.5px] font-semibold uppercase tracking-wide text-ink-soft">
                        @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow)
                            <div class="py-1">{{ $dow }}</div>
                        @endforeach
                    </div>
                    {{-- Day grid --}}
                    <div class="mt-1 grid grid-cols-7 gap-1">
                        <template x-for="(cell, i) in cells" :key="i">
                            <div>
                                <template x-if="cell">
                                    <button type="button" @click="select(cell.iso)"
                                            :class="selected === cell.iso
                                                ? 'border-green bg-green-wash ring-1 ring-green'
                                                : 'border-hairline hover:bg-hairline/30'"
                                            class="flex min-h-[54px] w-full flex-col items-center rounded-[8px] border p-1.5 transition">
                                        <span :class="isToday(cell.iso) ? 'font-bold text-green-deep' : 'text-ink'"
                                              class="text-[12.5px]" x-text="cell.day"></span>
                                        <template x-if="cell.meta">
                                            <span class="mt-1 inline-flex items-center gap-1">
                                                <span class="h-1.5 w-1.5 rounded-full" :class="cell.meta.conflict ? 'bg-status-red' : 'bg-green'"></span>
                                                <span class="text-[10px] text-ink-soft" x-text="cell.meta.count"></span>
                                            </span>
                                        </template>
                                    </button>
                                </template>
                                <template x-if="!cell"><span class="block"></span></template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- ── Selected day panel ────────────────────────────────────────── --}}
            <div class="panel">
                <div class="ph">
                    <h2 x-text="selectedLabel"></h2>
                    <span class="sub" x-text="(meta[selected]?.count || 0) + ' booking' + ((meta[selected]?.count || 0) === 1 ? '' : 's')"></span>
                </div>
                <div class="pb space-y-2">
                    {{-- Empty day --}}
                    <template x-if="!meta[selected]">
                        <p class="py-8 text-center text-[13px] text-ink-soft">No reservations on this day.</p>
                    </template>

                    {{-- One hidden group per date; Alpine reveals the selected one. --}}
                    @foreach($byDate as $date => $dayBookings)
                        <div x-show="selected === @js($date)" x-cloak class="space-y-2">
                            @foreach($dayBookings as $b)
                                @php $conflict = in_array($b->id, $conflictIds, true); @endphp
                                <div x-data="{ resched: false }"
                                     class="rounded-[10px] border p-3 {{ $conflict ? 'border-status-red bg-status-red-wash/40' : 'border-hairline bg-[#f9fbfa]' }}">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-[13px] font-semibold text-ink">{{ $b->resource?->name ?? 'Resource' }}</p>
                                            <p class="mt-0.5 text-[12.5px] text-ink">
                                                {{ $b->starts_at->format('g:i A') }} – {{ $b->starts_at->isSameDay($b->ends_at) ? $b->ends_at->format('g:i A') : $b->ends_at->format('M j, g:i A') }}
                                            </p>
                                            <p class="mt-0.5 text-[12px] text-ink-soft">
                                                {{ $b->document?->citizen_name ?: 'Requester' }}
                                                @if($b->document)
                                                    · <a href="{{ route('track.show', $b->document->tracking_number) }}" class="font-mono text-green underline">{{ $b->document->tracking_number }}</a>
                                                @endif
                                            </p>
                                            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                                <span class="pill {{ $b->status === 'approved' ? 'p-green' : 'p-amber' }}">{{ ucfirst($b->status) }}</span>
                                                @if($b->document?->quantity)<span class="pill p-green">Qty: {{ number_format($b->document->quantity) }}</span>@endif
                                                @if($conflict)<span class="pill p-red">Overlaps another booking</span>@endif
                                            </div>
                                        </div>
                                        <div class="flex shrink-0 flex-wrap items-center gap-2">
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
                                    </div>

                                    <form x-show="resched" x-cloak method="POST" action="{{ route('bookings.reschedule', $b) }}"
                                          class="mt-3 flex flex-wrap items-end gap-3 border-t border-hairline pt-3">
                                        @csrf @method('PATCH')
                                        <div>
                                            <label class="block text-[10px] font-semibold uppercase tracking-wide text-ink-soft">New start</label>
                                            <input type="datetime-local" name="starts_at" required value="{{ $b->starts_at->format('Y-m-d\TH:i') }}"
                                                   class="mt-0.5 rounded-[6px] border border-hairline-strong bg-white px-2 py-2 text-[13px] text-ink focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-semibold uppercase tracking-wide text-ink-soft">New end</label>
                                            <input type="datetime-local" name="ends_at" required value="{{ $b->ends_at->format('Y-m-d\TH:i') }}"
                                                   class="mt-0.5 rounded-[6px] border border-hairline-strong bg-white px-2 py-2 text-[13px] text-ink focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25">
                                        </div>
                                        <button type="submit" class="cr-btn cr-btn-sm cr-btn-primary">Save</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        @if($byDate->isEmpty())
            <div class="panel">
                <x-empty-state icon="clock" title="No upcoming reservations">
                    Booking requests (covered court, plaza, sound system…) will appear here as citizens submit them.
                </x-empty-state>
            </div>
        @endif
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('bookingCalendar', ({ meta, selected }) => ({
                meta: meta || {},
                selected: selected,
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

                get cells() {
                    const year = this.view.getFullYear();
                    const month = this.view.getMonth();
                    const startDow = new Date(year, month, 1).getDay();
                    const daysInMonth = new Date(year, month + 1, 0).getDate();
                    const cells = [];
                    for (let i = 0; i < startDow; i++) { cells.push(null); }
                    for (let d = 1; d <= daysInMonth; d++) {
                        const iso = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                        cells.push({ day: d, iso, meta: this.meta[iso] || null });
                    }
                    return cells;
                },

                get selectedLabel() {
                    if (!this.selected) { return ''; }
                    return new Date(this.selected + 'T00:00:00')
                        .toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
                },

                isToday(iso) { return iso === this.iso(new Date()); },
                select(iso) { if (iso) { this.selected = iso; } },
                prev() { this.view = new Date(this.view.getFullYear(), this.view.getMonth() - 1, 1); },
                next() { this.view = new Date(this.view.getFullYear(), this.view.getMonth() + 1, 1); },
                goToday() {
                    const t = new Date();
                    this.view = new Date(t.getFullYear(), t.getMonth(), 1);
                    this.select(this.iso(t));
                },
            }));
        });
    </script>
</x-app-layout>
