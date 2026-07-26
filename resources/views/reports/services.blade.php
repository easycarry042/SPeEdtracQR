<x-app-layout>
    <div class="page-shell page-shell-loose">

        <div class="mb-4 flex items-center gap-3">
            <h1 class="text-lg font-semibold text-green-deep">Services report</h1>
            <span class="chip">Requests &amp; bookings</span>
        </div>

        {{-- KPI tiles --}}
        <div class="tiles" style="margin-bottom:14px;">
            <div class="tile"><div class="k">Active request types</div><div class="v mono">{{ $kpis['request_types'] }}</div><div class="bar bright"></div></div>
            <div class="tile"><div class="k">Upcoming bookings</div><div class="v mono">{{ $kpis['bookings_upcoming'] }}</div><div class="bar"></div></div>
            <div class="tile"><div class="k">Bookings awaiting approval</div><div class="v mono">{{ $kpis['pending_approvals'] }}</div><div class="bar amber"></div></div>
            <div class="tile"><div class="k">Mandatory docs submitted</div><div class="v mono">{{ $kpis['requirement_completion_pct'] }}%</div><div class="bar"></div></div>
        </div>

        <div class="row" style="display:grid;gap:14px;grid-template-columns:1.3fr 1fr;">

            {{-- LEFT: request volume by type --}}
            <div class="panel">
                <div class="ph">
                    <h2>Request volume by type</h2>
                    <span class="sub">all time</span>
                </div>
                <div class="pb">
                    @php $maxVolume = max(1, (int) $volumeByType->max('total')); @endphp
                    @forelse($volumeByType as $type)
                        <div class="mb-2.5 last:mb-0">
                            <div class="flex items-baseline justify-between gap-3">
                                <div class="min-w-0">
                                    <span class="text-[13px] font-semibold text-ink">{{ $type['name'] }}</span>
                                    <span class="pill {{ $type['kind'] === \App\Models\RequestType::KIND_BOOKING ? 'p-amber' : 'p-green' }} ml-1">{{ ucfirst($type['kind']) }}</span>
                                    @if($type['resource'])
                                        <span class="text-[11px] text-ink-soft">· {{ $type['resource'] }}</span>
                                    @endif
                                </div>
                                <span class="shrink-0 font-mono text-[13px] text-ink">{{ $type['total'] }}<span class="text-[11px] text-ink-soft"> ({{ $type['this_month'] }} this mo.)</span></span>
                            </div>
                            <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-hairline">
                                <div class="h-full rounded-full bg-green" style="width: {{ round($type['total'] / $maxVolume * 100) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-[13px] text-ink-soft">No request types defined yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- RIGHT: requirement checklist health --}}
            <div class="panel">
                <div class="ph">
                    <h2>Requirement checklist health</h2>
                    <span class="sub">mandatory items</span>
                </div>
                <div class="pb">
                    @if($requirementStats['mandatory_total'] > 0)
                        <p class="text-[13px] text-ink-soft">Across <strong class="text-ink">{{ $requirementStats['mandatory_total'] }}</strong> mandatory requirement{{ $requirementStats['mandatory_total'] === 1 ? '' : 's' }} attached to document requests.</p>

                        <div class="mt-3">
                            <div class="flex items-baseline justify-between">
                                <span class="text-[12px] font-medium text-ink">Submitted by citizen</span>
                                <span class="font-mono text-[13px] text-ink">{{ $requirementStats['submitted'] }} / {{ $requirementStats['mandatory_total'] }} ({{ $requirementStats['submitted_pct'] }}%)</span>
                            </div>
                            <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-hairline">
                                <div class="h-full rounded-full bg-green" style="width: {{ $requirementStats['submitted_pct'] }}%"></div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="flex items-baseline justify-between">
                                <span class="text-[12px] font-medium text-ink">Verified by staff</span>
                                <span class="font-mono text-[13px] text-ink">{{ $requirementStats['verified'] }} / {{ $requirementStats['mandatory_total'] }} ({{ $requirementStats['verified_pct'] }}%)</span>
                            </div>
                            <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-hairline">
                                <div class="h-full rounded-full bg-green-deep" style="width: {{ $requirementStats['verified_pct'] }}%"></div>
                            </div>
                        </div>
                    @else
                        <p class="text-[13px] text-ink-soft">No mandatory requirements have been captured on any request yet.</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Booking utilisation by resource --}}
        <div class="panel mt-3.5">
            <div class="ph">
                <h2>Booking utilisation by resource</h2>
                <span class="sub">{{ $bookingsByResource->count() }} resource{{ $bookingsByResource->count() === 1 ? '' : 's' }}</span>
            </div>
            <div class="pb">
                @forelse($bookingsByResource as $resource)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-[10px] border border-hairline bg-[#f9fbfa] p-3 {{ ! $loop->last ? 'mb-2' : '' }}">
                        <div class="min-w-0">
                            <span class="text-[13px] font-semibold text-ink">{{ $resource['name'] }}</span>
                            @unless($resource['is_active'])
                                <span class="pill p-red ml-1">Retired</span>
                            @endunless
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="pill p-green">{{ $resource['upcoming'] }} upcoming</span>
                            <span class="pill p-amber">{{ $resource['pending'] }} pending</span>
                            <span class="text-[12px] text-ink-soft">{{ $resource['approved'] }} approved · {{ $resource['total'] }} total</span>
                        </div>
                    </div>
                @empty
                    <x-empty-state icon="clock" title="No bookable resources yet">
                        Add resources under the catalog and link a booking-type request to start collecting reservations.
                    </x-empty-state>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
