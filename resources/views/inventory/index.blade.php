<x-app-layout title="Resources">
    {{-- Resources: what the office owns, who has it, and what is free.
         The lending desk runs a borrowing end to end — approve with a count,
         hand over, take back, chase the late ones (App\Support\Lending). The
         stock tables below read the same bookings, so the numbers agree. --}}
    <div class="page-shell page-shell-loose">

        @if(session('status'))
            <div class="panel"><div class="pb text-[13px] font-medium text-green-deep" role="status">{{ session('status') }}</div></div>
        @endif
        @if(session('error') || $errors->any())
            <div class="panel" style="border-color:var(--red)">
                <div class="pb text-[13px] font-medium text-status-red" role="alert">{{ session('error') ?? $errors->first() }}</div>
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-[13px] text-ink-soft">
                As of {{ $asOf->format('D j M Y, g:i A') }}.
                {{-- Supervisors have Resources but not the staff-owned calendar. --}}
                @can('manage bookings')
                    Reservations are managed on the
                    <a href="{{ route('bookings.index') }}" class="text-green underline">Booking</a> calendar.
                @else
                    Reservations are managed by staff on the Booking calendar.
                @endcan
            </p>
            {{-- The catalog itself — what exists to lend or reserve — is the
                 super admin's (Admin\ResourceController). --}}
            @can('manage system')
                <a href="{{ route('admin.resources.create') }}" class="cr-btn cr-btn-primary">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Add item
                </a>
            @endcan
        </div>

        {{-- The numbers someone at the counter is actually asked for. --}}
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <x-staff-stat-tile label="Available to lend" :value="number_format($tiles['units_available'])"
                               tone="deep" icon="check"
                               :note="$tiles['units_reserved'] > 0 ? number_format($tiles['units_reserved']).' more promised to pickups' : null" />
            <x-staff-stat-tile label="Out on loan" :value="number_format($tiles['units_on_loan'])"
                               :tone="$tiles['overdue'] > 0 ? 'amber' : 'mid'" icon="clock"
                               :note="$tiles['overdue'] > 0 ? $tiles['overdue'].' '.($tiles['overdue'] === 1 ? 'loan' : 'loans').' overdue' : null" />
            <x-staff-stat-tile label="To approve" :value="number_format($tiles['to_approve'])"
                               tone="bright" icon="hourglass"
                               :note="$tiles['pickups_today'] > 0 ? $tiles['pickups_today'].' pickup'.($tiles['pickups_today'] === 1 ? '' : 's').' today' : null" />
            <x-staff-stat-tile label="Facilities free now"
                               :value="$tiles['facilities_free'].' of '.$tiles['facilities_total']"
                               tone="brightest" icon="none" />
        </div>

        {{-- ── Lending desk ────────────────────────────────────────────────── --}}
        @php
            $tabs = [
                'overdue' => 'Overdue',
                'approve' => 'To approve',
                'pickup' => 'For pickup',
                'on_loan' => 'On loan',
                'returned' => 'Returned',
            ];
            // Open on the first queue that needs someone: late items, then
            // requests waiting on a decision, then pickups.
            $firstTab = collect(['overdue', 'approve', 'pickup', 'on_loan'])->first(fn ($key) => $desk[$key]->isNotEmpty()) ?? 'approve';
        @endphp
        <section x-data="{
                    tab: @js($firstTab),
                    q: '',
                    init() {
                        // Come back to the queue you were working in after a form posts.
                        try {
                            const kept = sessionStorage.getItem('inv-desk-tab');
                            if (kept && @js(array_keys($tabs)).includes(kept)) { this.tab = kept; }
                        } catch (e) {}
                        this.$watch('tab', (value) => { try { sessionStorage.setItem('inv-desk-tab', value); } catch (e) {} });
                    },
                    shows(el) { return ! this.q || el.dataset.search.includes(this.q.trim().toLowerCase()); },
                 }">
            <h2 class="st-section-title">Lending desk</h2>
            <p class="mt-1 text-[13px] text-ink-soft">
                Approve a borrowing with the number you are lending, hand it over, and mark it returned when it comes back.
                Nothing goes back into stock until someone records the return — a late borrower shows up under Overdue.
            </p>

            <div class="inv-desk-bar mt-3">
                {{-- Five queues are wider than a phone: the tab track scrolls in
                     its own row rather than pushing the page sideways. --}}
                <div class="inv-desk-tabs">
                <div class="st-tabs" role="tablist" aria-label="Lending queues">
                    @foreach($tabs as $key => $label)
                        <button type="button" role="tab" @click="tab = @js($key)"
                                :class="tab === @js($key) ? 'on' : ''" :aria-selected="tab === @js($key)">
                            {{ $label }}
                            <span @class(['st-tab-count', 'inv-tab-alert' => $key === 'overdue' && $desk[$key]->isNotEmpty()])>{{ $desk[$key]->count() }}</span>
                        </button>
                    @endforeach
                </div>
                </div>
                <label for="lendingSearch" class="sr-only">Search the lending desk</label>
                <div class="st-search">
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                    <input id="lendingSearch" type="search" x-model="q" placeholder="Search borrower, item or tracking number">
                </div>
            </div>

            <div class="panel mt-3">
                {{-- relative: the rows' screen-reader labels are absolutely
                     positioned, and would otherwise escape this scroller and
                     widen the whole page on a phone. --}}
                <div class="table-wrap relative">

                    {{-- Overdue ─────────────────────────────────────────── --}}
                    <table class="reg" x-show="tab === 'overdue'" x-cloak>
                        <thead><tr><th>Borrower</th><th>Item</th><th>Still out</th><th>Was due</th><th>Reminded</th><th></th></tr></thead>
                        @forelse($desk['overdue'] as $loan)
                            @include('inventory.partials.loan-row', ['loan' => $loan, 'columns' => 6])
                        @empty
                            <tbody><tr><td colspan="6" class="inv-empty">Nothing overdue. Every loan past its return date has been brought back.</td></tr></tbody>
                        @endforelse
                    </table>

                    {{-- To approve ──────────────────────────────────────── --}}
                    <table class="reg" x-show="tab === 'approve'" x-cloak>
                        <thead><tr><th>Borrower</th><th>Item</th><th>Dates</th><th>Asked for</th><th>Lend</th><th></th></tr></thead>
                        @forelse($desk['approve'] as $loan)
                            @include('inventory.partials.loan-row', ['loan' => $loan, 'columns' => 6])
                        @empty
                            <tbody><tr><td colspan="6" class="inv-empty">No borrowing or reservation requests are waiting.</td></tr></tbody>
                        @endforelse
                    </table>

                    {{-- For pickup ──────────────────────────────────────── --}}
                    <table class="reg" x-show="tab === 'pickup'" x-cloak>
                        <thead><tr><th>Borrower</th><th>Item</th><th>Lending</th><th>Pickup</th><th>Return by</th><th></th></tr></thead>
                        @forelse($desk['pickup'] as $loan)
                            @include('inventory.partials.loan-row', ['loan' => $loan, 'columns' => 6])
                        @empty
                            <tbody><tr><td colspan="6" class="inv-empty">No approved borrowings are waiting to be picked up.</td></tr></tbody>
                        @endforelse
                    </table>

                    {{-- On loan ─────────────────────────────────────────── --}}
                    <table class="reg" x-show="tab === 'on_loan'" x-cloak>
                        <thead><tr><th>Borrower</th><th>Item</th><th>Still out</th><th>Handed over</th><th>Due back</th><th></th></tr></thead>
                        @forelse($desk['on_loan'] as $loan)
                            @include('inventory.partials.loan-row', ['loan' => $loan, 'columns' => 6])
                        @empty
                            <tbody><tr><td colspan="6" class="inv-empty">Nothing is out on loan right now.</td></tr></tbody>
                        @endforelse
                    </table>

                    {{-- Returned (last 30 days) ─────────────────────────── --}}
                    <table class="reg" x-show="tab === 'returned'" x-cloak>
                        <thead><tr><th>Borrower</th><th>Item</th><th>Lent</th><th>Came back</th><th>Closed</th><th></th></tr></thead>
                        @forelse($desk['returned'] as $loan)
                            @include('inventory.partials.loan-row', ['loan' => $loan, 'columns' => 6])
                        @empty
                            <tbody><tr><td colspan="6" class="inv-empty">No loans were closed in the last 30 days.</td></tr></tbody>
                        @endforelse
                    </table>
                </div>
            </div>
        </section>

        {{-- ── Equipment ───────────────────────────────────────────────────── --}}
        <section>
            <h2 class="st-section-title">Equipment</h2>
            <p class="mt-1 text-[13px] text-ink-soft">
                Counted stock. <strong>Available</strong> is what you can still promise: owned, less out of service,
                less what is out on loan, less what is promised to approved pickups. Approving checks the request's own dates.
            </p>

            <div class="panel mt-3">
                <div class="table-wrap">
                    <table class="reg">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Owned</th>
                                <th>Out of service</th>
                                <th>On loan</th>
                                <th>Promised</th>
                                <th>Available</th>
                                <th></th>
                            </tr>
                        </thead>

                        {{-- One <tbody> per item, not one for the table. The edit
                             panel is a second <tr>, and a sibling row cannot see an
                             x-data declared on the row above it. --}}
                        @forelse($equipment as $row)
                            @php $resource = $row['resource']; @endphp
                            <tbody x-data="{ editing: false }">
                                <tr>
                                    <td>
                                        <p class="nm text-ink">{{ $row['name'] }}</p>
                                        <p class="text-[12px] text-ink-soft">{{ $resource->location ?: 'No location set' }}</p>
                                        @if($resource->description)
                                            <p class="max-w-xs truncate text-[11.5px] text-ink-soft" title="{{ $resource->description }}">{{ $resource->description }}</p>
                                        @endif
                                        @unless($resource->is_active)
                                            <span class="pill p-muted mt-1">Inactive</span>
                                        @endunless
                                    </td>
                                    <td class="muted">{{ $row['owned'] ? number_format($row['owned']) : 'Not counted' }}</td>
                                    <td class="muted">{{ $row['damaged'] ? number_format($row['damaged']) : '—' }}</td>
                                    <td class="muted">{{ $row['on_loan'] ? number_format($row['on_loan']) : '—' }}</td>
                                    <td class="muted">{{ $row['reserved'] ? number_format($row['reserved']) : '—' }}</td>
                                    <td>
                                        @if($row['owned'] === 0)
                                            <span class="pill p-muted">Set a count</span>
                                        @else
                                            {{-- The bar is only how the number is read; the count
                                                 itself carries the meaning, so it leads. --}}
                                            <p class="inv-free {{ $row['available'] === 0 ? 'is-none' : '' }}">{{ number_format($row['available']) }}</p>
                                            <div class="inv-bar" role="img"
                                                 aria-label="{{ $row['available'] }} of {{ max(0, $row['owned'] - $row['damaged']) }} available">
                                                <div class="inv-bar-fill {{ $row['available'] === 0 ? 'is-none' : '' }}" style="width:{{ $row['pct'] }}%"></div>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="flex justify-end">
                                            <button type="button" @click="editing = ! editing" class="cr-btn cr-btn-sm"
                                                    :aria-expanded="editing">Count &amp; history</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr x-show="editing" x-cloak>
                                    <td colspan="7" class="inv-edit">
                                        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                                            <form method="POST" action="{{ route('inventory.update', $resource) }}"
                                                  class="flex flex-wrap items-end gap-3">
                                                @csrf @method('PATCH')
                                                <label class="block">
                                                    <span class="inv-field-label">Where it is kept</span>
                                                    <input type="text" name="location" value="{{ $resource->location }}"
                                                           placeholder="e.g. GSO stockroom" class="st-filter-field inv-text">
                                                </label>
                                                <label class="block">
                                                    <span class="inv-field-label">How many owned</span>
                                                    <input type="number" name="total_quantity" min="0" step="1" value="{{ $resource->total_quantity }}"
                                                           inputmode="numeric" data-whole-number class="st-filter-field inv-num">
                                                </label>
                                                <label class="block">
                                                    <span class="inv-field-label">Out of service</span>
                                                    <input type="number" name="damaged_quantity" min="0" step="1" value="{{ $resource->damaged_quantity }}"
                                                           inputmode="numeric" data-whole-number class="st-filter-field inv-num">
                                                </label>
                                                <button type="submit" class="cr-btn cr-btn-primary cr-btn-sm">Save</button>
                                                <p class="w-full text-[12px] text-ink-soft">
                                                    Bought more, or repaired some? Change the counts here. Items lost by a borrower are
                                                    written off from their loan instead, so the record shows who had them.
                                                </p>
                                            </form>

                                            <div>
                                                <p class="inv-field-label">Recent history</p>
                                                @include('inventory.partials.history', ['entries' => $history[$resource->id] ?? []])
                                            </div>
                                        </div>
                                            @can('manage system')
                                                @include('inventory.partials.catalog-actions', ['resource' => $resource])
                                            @endcan
                                    </td>
                                </tr>
                            </tbody>
                        @empty
                            <tbody>
                                <tr>
                                    <td colspan="7" class="inv-empty">No equipment yet. Add it under Admin → Resources.</td>
                                </tr>
                            </tbody>
                        @endforelse
                    </table>
                </div>
            </div>
        </section>

        {{-- ── Facilities ──────────────────────────────────────────────────── --}}
        <section>
            <h2 class="st-section-title">Facilities</h2>
            <p class="mt-1 text-[13px] text-ink-soft">One of each — a venue is either free or held by an approved reservation. Mark it done when people leave early, and it is free again straight away.</p>

            <div class="panel mt-3">
                <div class="table-wrap">
                    <table class="reg">
                        <thead>
                            <tr>
                                <th>Facility</th>
                                <th>Holds</th>
                                <th>Right now</th>
                                <th>Next reserved</th>
                                <th></th>
                            </tr>
                        </thead>

                        @forelse($facilities as $row)
                            @php $resource = $row['resource']; @endphp
                            <tbody x-data="{ editing: false }">
                                <tr>
                                    <td>
                                        <p class="nm text-ink">{{ $row['name'] }}</p>
                                        <p class="text-[12px] text-ink-soft">{{ $resource->location ?: 'No location set' }}</p>
                                        @if($resource->description)
                                            <p class="max-w-xs truncate text-[11.5px] text-ink-soft" title="{{ $resource->description }}">{{ $resource->description }}</p>
                                        @endif
                                        @unless($resource->is_active)
                                            <span class="pill p-muted mt-1">Inactive</span>
                                        @endunless
                                    </td>
                                    <td class="muted">{{ $resource->capacity ? number_format($resource->capacity).' people' : '—' }}</td>
                                    <td>
                                        <span class="pill {{ $row['tone'] === 'free' ? 'p-green' : 'p-amber' }}">{{ $row['status'] }}</span>
                                        @if($row['detail'])
                                            <span class="ml-1 text-[12px] text-ink-soft">{{ $row['detail'] }}</span>
                                        @endif
                                    </td>
                                    <td class="muted">{{ $row['next'] ?? 'Nothing booked' }}</td>
                                    <td>
                                        <div class="flex flex-wrap justify-end gap-2">
                                            @if($row['booking'])
                                                <form method="POST" action="{{ route('inventory.loans.return', $row['booking']) }}"
                                                      onsubmit="return confirm(@js('Mark '.$row['name'].' done? It will show as free straight away.'))">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="cr-btn cr-btn-sm cr-btn-primary">Done</button>
                                                </form>
                                            @endif
                                            <button type="button" @click="editing = ! editing" class="cr-btn cr-btn-sm"
                                                    :aria-expanded="editing">Details</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr x-show="editing" x-cloak>
                                    <td colspan="5" class="inv-edit">
                                        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                                            <form method="POST" action="{{ route('inventory.update', $resource) }}"
                                                  class="flex flex-wrap items-end gap-3">
                                                @csrf @method('PATCH')
                                                <label class="block">
                                                    <span class="inv-field-label">Where it is</span>
                                                    <input type="text" name="location" value="{{ $resource->location }}"
                                                           placeholder="e.g. Beside the Municipal Hall" class="st-filter-field inv-text">
                                                </label>
                                                <label class="block">
                                                    <span class="inv-field-label">Holds (people)</span>
                                                    <input type="number" name="capacity" min="0" step="1" value="{{ $resource->capacity }}"
                                                           inputmode="numeric" data-whole-number class="st-filter-field inv-num">
                                                </label>
                                                <button type="submit" class="cr-btn cr-btn-primary cr-btn-sm">Save</button>
                                            </form>

                                            <div>
                                                <p class="inv-field-label">Recent history</p>
                                                @include('inventory.partials.history', ['entries' => $history[$resource->id] ?? []])
                                            </div>
                                        </div>
                                            @can('manage system')
                                                @include('inventory.partials.catalog-actions', ['resource' => $resource])
                                            @endcan
                                    </td>
                                </tr>
                            </tbody>
                        @empty
                            <tbody>
                                <tr>
                                    <td colspan="5" class="inv-empty">No facilities yet. Add one under Admin → Resources.</td>
                                </tr>
                            </tbody>
                        @endforelse
                    </table>
                </div>
            </div>
        </section>
    </div>

    <script>
        // Counts are whole numbers: a number field still lets a decimal point,
        // an exponent or a minus sign through, so those keys are refused here
        // (the server refuses them too).
        if (! window.wholeNumberGuard) {
            window.wholeNumberGuard = true;
            document.addEventListener('keydown', (event) => {
                if (event.target.matches?.('[data-whole-number]') && ['.', ',', 'e', 'E', '-', '+'].includes(event.key)) {
                    event.preventDefault();
                }
            });
        }
    </script>
</x-app-layout>
