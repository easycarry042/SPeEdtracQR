<x-app-layout title="Inventory">
    {{-- Inventory: what the office owns, and what is free at this moment.
         Two tables because the two kinds answer different questions — a
         facility is free or it is not; equipment is a number. Both read the
         same approved bookings the calendar does, so the screens agree. --}}
    <div class="page-shell page-shell-loose">

        @if(session('status'))
            <div class="panel"><div class="pb text-[13px] font-medium text-green-deep" role="status">{{ session('status') }}</div></div>
        @endif
        @if($errors->any())
            <div class="panel" style="border-color:var(--red)">
                <div class="pb text-[13px] font-medium text-status-red" role="alert">{{ $errors->first() }}</div>
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-[13px] text-ink-soft">
                As of {{ $asOf->format('D j M Y, g:i A') }}. Reservations are managed on the
                <a href="{{ route('bookings.index') }}" class="text-green underline">Booking</a> calendar.
            </p>
        </div>

        {{-- The three numbers someone at the counter is actually asked for. --}}
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <x-staff-stat-tile label="Facilities free now"
                               :value="$tiles['facilities_free'].' of '.$tiles['facilities_total']"
                               tone="deep" icon="check" />
            <x-staff-stat-tile label="Units free to lend" :value="number_format($tiles['units_free'])"
                               tone="bright" icon="doc" />
            <x-staff-stat-tile label="Units out on loan" :value="number_format($tiles['units_out'])"
                               tone="mid" icon="clock"
                               :note="$tiles['units_damaged'] > 0 ? number_format($tiles['units_damaged']).' out of service' : null" />
        </div>

        {{-- ── Facilities ──────────────────────────────────────────────────── --}}
        <section>
            <h2 class="st-section-title">Facilities</h2>
            <p class="mt-1 text-[13px] text-ink-soft">One of each — a venue is either free or held by an approved reservation.</p>

            <div class="panel mt-3">
                <div class="table-wrap">
                    <table class="reg">
                        <thead>
                            <tr>
                                <th>Facility</th>
                                <th>Where</th>
                                <th>Holds</th>
                                <th>Right now</th>
                                <th>Next reserved</th>
                                <th></th>
                            </tr>
                        </thead>

                        {{-- One <tbody> per facility, not one for the table. The edit
                             panel is a second <tr>, and a sibling row cannot see an
                             x-data declared on the row above it. A table has nowhere
                             to put a wrapping <div>, but it is allowed as many tbody
                             elements as it likes. --}}
                        @forelse($facilities as $row)
                            @php $resource = $row['resource']; @endphp
                            <tbody x-data="{ editing: false }">
                                <tr>
                                    <td>
                                        <p class="nm text-ink">{{ $row['name'] }}</p>
                                        @unless($resource->is_active)
                                            <span class="pill p-muted mt-1">Inactive</span>
                                        @endunless
                                    </td>
                                    <td class="muted">{{ $resource->location ?: '—' }}</td>
                                    <td class="muted">{{ $resource->capacity ? number_format($resource->capacity).' people' : '—' }}</td>
                                    <td>
                                        <span class="pill {{ $row['tone'] === 'free' ? 'p-green' : 'p-amber' }}">{{ $row['status'] }}</span>
                                        @if($row['detail'])
                                            <span class="ml-1 text-[12px] text-ink-soft">{{ $row['detail'] }}</span>
                                        @endif
                                    </td>
                                    <td class="muted">{{ $row['next'] ?? 'Nothing booked' }}</td>
                                    <td>
                                        <div class="flex justify-end">
                                            <button type="button" @click="editing = ! editing" class="cr-btn cr-btn-sm"
                                                    :aria-expanded="editing">Edit</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr x-show="editing" x-cloak>
                                    <td colspan="6" class="inv-edit">
                                        <form method="POST" action="{{ route('inventory.update', $resource) }}"
                                              class="flex flex-wrap items-end gap-3">
                                            @csrf @method('PATCH')
                                            <label class="block">
                                                <span class="inv-field-label">Where it is</span>
                                                <input type="text" name="location" value="{{ $resource->location }}"
                                                       placeholder="e.g. Beside the Municipal Hall" class="st-filter-field w-64">
                                            </label>
                                            <label class="block">
                                                <span class="inv-field-label">Holds (people)</span>
                                                <input type="number" name="capacity" min="0" value="{{ $resource->capacity }}"
                                                       inputmode="numeric" class="st-filter-field w-32">
                                            </label>
                                            <button type="submit" class="cr-btn cr-btn-primary cr-btn-sm">Save</button>
                                        </form>
                                    </td>
                                </tr>
                            </tbody>
                        @empty
                            <tbody>
                                <tr>
                                    <td colspan="6" class="py-10 text-center text-[13px] text-ink-soft">
                                        No facilities yet. Add one under Admin → Resources.
                                    </td>
                                </tr>
                            </tbody>
                        @endforelse
                    </table>
                </div>
            </div>
        </section>

        {{-- ── Equipment ───────────────────────────────────────────────────── --}}
        <section>
            <h2 class="st-section-title">Equipment</h2>
            <p class="mt-1 text-[13px] text-ink-soft">Counted stock. "Free" is what can be handed over today — owned, less what is out and what is broken.</p>

            <div class="panel mt-3">
                <div class="table-wrap">
                    <table class="reg">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Where</th>
                                <th>Owned</th>
                                <th>Out on loan</th>
                                <th>Out of service</th>
                                <th>Free now</th>
                                <th></th>
                            </tr>
                        </thead>

                        @forelse($equipment as $row)
                            @php $resource = $row['resource']; @endphp
                            <tbody x-data="{ editing: false }">
                                <tr>
                                    <td>
                                        <p class="nm text-ink">{{ $row['name'] }}</p>
                                        @unless($resource->is_active)
                                            <span class="pill p-muted mt-1">Inactive</span>
                                        @endunless
                                    </td>
                                    <td class="muted">{{ $resource->location ?: '—' }}</td>
                                    <td class="muted">{{ $row['owned'] ? number_format($row['owned']) : 'Not counted' }}</td>
                                    <td class="muted">{{ number_format($row['out']) }}</td>
                                    <td class="muted">{{ $row['damaged'] ? number_format($row['damaged']) : '—' }}</td>
                                    <td>
                                        @if($row['owned'] === 0)
                                            <span class="pill p-muted">Set a count</span>
                                        @else
                                            {{-- The bar is only how the number is read; the count
                                                 itself carries the meaning, so it leads. --}}
                                            <p class="inv-free {{ $row['free'] === 0 ? 'is-none' : '' }}">{{ number_format($row['free']) }}</p>
                                            <div class="inv-bar" role="img"
                                                 aria-label="{{ $row['free'] }} of {{ max(0, $row['owned'] - $row['damaged']) }} free">
                                                <div class="inv-bar-fill {{ $row['free'] === 0 ? 'is-none' : '' }}" style="width:{{ $row['pct'] }}%"></div>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="flex justify-end">
                                            <button type="button" @click="editing = ! editing" class="cr-btn cr-btn-sm"
                                                    :aria-expanded="editing">Edit</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr x-show="editing" x-cloak>
                                    <td colspan="7" class="inv-edit">
                                        <form method="POST" action="{{ route('inventory.update', $resource) }}"
                                              class="flex flex-wrap items-end gap-3">
                                            @csrf @method('PATCH')
                                            <label class="block">
                                                <span class="inv-field-label">Where it is kept</span>
                                                <input type="text" name="location" value="{{ $resource->location }}"
                                                       placeholder="e.g. GSO stockroom" class="st-filter-field w-64">
                                            </label>
                                            <label class="block">
                                                <span class="inv-field-label">How many owned</span>
                                                <input type="number" name="total_quantity" min="0" value="{{ $resource->total_quantity }}"
                                                       inputmode="numeric" class="st-filter-field w-32">
                                            </label>
                                            <label class="block">
                                                <span class="inv-field-label">Out of service</span>
                                                <input type="number" name="damaged_quantity" min="0" value="{{ $resource->damaged_quantity }}"
                                                       inputmode="numeric" class="st-filter-field w-32">
                                            </label>
                                            <button type="submit" class="cr-btn cr-btn-primary cr-btn-sm">Save</button>
                                        </form>
                                    </td>
                                </tr>
                            </tbody>
                        @empty
                            <tbody>
                                <tr>
                                    <td colspan="7" class="py-10 text-center text-[13px] text-ink-soft">
                                        No equipment yet. Add it under Admin → Resources.
                                    </td>
                                </tr>
                            </tbody>
                        @endforelse
                    </table>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
