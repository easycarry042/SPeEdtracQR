<x-app-layout>
    <div class="page-shell page-shell-loose">

        @if(session('status'))
            <div class="panel"><div class="pb text-[13px] font-medium text-green-deep">{{ session('status') }}</div></div>
        @endif
        @if(session('error'))
            <div class="panel" style="border-color:var(--red)"><div class="pb text-[13px] font-medium text-status-red">{{ session('error') }}</div></div>
        @endif

        <p class="text-[13px] text-ink-soft">Upcoming resource reservations. Overlapping bookings are flagged in red — approve one and reschedule or cancel the other.</p>

        @forelse($grouped as $resourceName => $bookings)
            <div class="panel">
                <div class="ph"><h2>{{ $resourceName }}</h2><span class="sub">{{ $bookings->count() }} upcoming</span></div>
                <div class="pb space-y-2">
                    @foreach($bookings as $b)
                        @php $conflict = in_array($b->id, $conflictIds, true); @endphp
                        <div x-data="{ resched: false }"
                             class="rounded-[10px] border p-3 {{ $conflict ? 'border-status-red bg-status-red-wash/40' : 'border-hairline bg-[#f9fbfa]' }}">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[13px] font-semibold text-ink">
                                        {{ $b->starts_at->format('D, M j, Y · g:i A') }} – {{ $b->starts_at->isSameDay($b->ends_at) ? $b->ends_at->format('g:i A') : $b->ends_at->format('M j, g:i A') }}
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
            </div>
        @empty
            <div class="panel">
                <x-empty-state icon="clock" title="No upcoming reservations">
                    Booking requests (covered court, plaza, sound system…) will appear here as citizens submit them.
                </x-empty-state>
            </div>
        @endforelse
    </div>
</x-app-layout>
