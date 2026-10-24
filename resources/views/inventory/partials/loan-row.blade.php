{{-- One borrowing on the lending desk, drawn for the queue it sits in
     ($loan['state']): its own <tbody>, so the return form's second row shares
     the row's Alpine scope. Expects $loan (InventoryController::loanRow) and
     $columns. --}}
@php
    $booking = $loan['booking'];
    $state = $loan['state'];
    $search = strtolower(implode(' ', array_filter([$loan['borrower'], $loan['item'], $loan['tracking'], $loan['handler']])));
@endphp
<tbody x-data="{ open: false, extending: false }" x-show="shows($el)" data-search="{{ $search }}">
    <tr>
        {{-- Borrower: who has it, how to reach them, which request. --}}
        <td>
            <p class="nm text-ink">{{ $loan['borrower'] }}</p>
            <p class="text-[12px] text-ink-soft">
                @if($loan['tracking'])
                    <a href="{{ route('track.show', $loan['tracking']) }}" class="mono text-green hover:underline">{{ $loan['tracking'] }}</a>
                @endif
                @if(in_array($state, ['overdue', 'on_loan'], true) && $loan['contact'])
                    · {{ $loan['contact'] }}
                @endif
            </p>
            @if($state === 'pending')
                <p class="text-[12px] text-ink-soft">{{ $loan['handler'] ? 'Handled by '.$loan['handler'] : 'Not assigned yet' }}</p>
            @endif
        </td>

        <td class="text-ink">{{ $loan['item'] }}</td>

        @switch($state)
            @case('overdue')
                <td class="font-semibold text-ink">{{ number_format($loan['outstanding']) }}@if($loan['outstanding'] !== $loan['units'])<span class="text-[12px] font-normal text-ink-soft"> of {{ number_format($loan['units']) }}</span>@endif</td>
                <td>
                    <p class="text-ink">{{ $loan['due_on'] }}</p>
                    <span class="pill p-red mt-1">{{ $loan['days_overdue'] }} {{ $loan['days_overdue'] === 1 ? 'day' : 'days' }} overdue</span>
                </td>
                <td class="muted">{{ $loan['reminded_on'] ?? 'Not yet' }}</td>
                <td>
                    <div class="flex flex-wrap justify-end gap-2">
                        <form method="POST" action="{{ route('inventory.loans.remind', $booking) }}">
                            @csrf
                            <button type="submit" class="cr-btn cr-btn-sm" title="{{ $loan['email'] ? 'Email '.$loan['email'] : 'No email on file' }}">Send reminder</button>
                        </form>
                        <button type="button" @click="extending = ! extending; open = false" class="cr-btn cr-btn-sm" :aria-expanded="extending">Change return date</button>
                        <button type="button" @click="open = ! open; extending = false" class="cr-btn cr-btn-sm cr-btn-primary" :aria-expanded="open">Record return</button>
                    </div>
                </td>
                @break

            @case('pending')
                <td class="muted">{{ $loan['window'] }}</td>
                <td class="muted">{{ $loan['is_equipment'] ? number_format($loan['requested']) : '—' }}</td>
                @if($loan['not_ready'])
                    {{-- The request has not been assigned and accepted yet: the
                         approval waits for it (App\Support\Lending::requestNotReady). --}}
                    <td colspan="2">
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <span class="text-right text-[12px] text-ink-soft">{{ $loan['not_ready'] }}</span>
                            <button type="submit" form="decline-{{ $booking->id }}" class="cr-btn cr-btn-sm cr-btn-danger">Decline</button>
                        </div>
                    </td>
                @elseif($loan['is_equipment'])
                    {{-- How many to lend. Whole numbers only; the warning and the
                         disabled button come from the same count the server
                         checks, so "Approve" never offers what will be refused. --}}
                    <td colspan="2" x-data="{ qty: {{ max(1, $loan['requested']) }}, free: {{ (int) $loan['free'] }}, owned: {{ (int) $loan['serviceable'] }} }">
                        <form method="POST" action="{{ route('inventory.loans.approve', $booking) }}" class="flex flex-wrap items-center justify-end gap-2">
                            @csrf @method('PATCH')
                            <label class="sr-only" for="lend-{{ $booking->id }}">How many {{ $loan['item'] }} to lend</label>
                            <input id="lend-{{ $booking->id }}" type="number" name="quantity" min="1" step="1" required
                                   inputmode="numeric" data-whole-number x-model.number="qty" class="st-filter-field inv-num">
                            <button type="submit" class="cr-btn cr-btn-sm cr-btn-primary"
                                    :disabled="! (Number.isInteger(qty) && qty >= 1 && qty <= free)">Approve</button>
                            <button type="submit" form="decline-{{ $booking->id }}" class="cr-btn cr-btn-sm cr-btn-danger">Decline</button>
                        </form>
                        <p class="mt-1 text-right text-[12px]"
                           :class="qty > free ? 'font-semibold text-status-red' : 'text-ink-soft'" role="status">
                            <template x-if="owned === 0"><span>No count set for this item yet — set how many the office owns below.</span></template>
                            <template x-if="owned > 0 && qty > free"><span x-text="'Not enough — only ' + free + ' free for these dates. Lend fewer or reschedule.'"></span></template>
                            <template x-if="owned > 0 && qty <= free"><span x-text="free + ' free for these dates · ' + Math.max(0, free - (Number.isInteger(qty) ? qty : 0)) + ' left after this'"></span></template>
                        </p>
                    </td>
                @else
                    <td colspan="2">
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            @if($loan['clash'])
                                <span class="pill p-red">Overlaps an approved reservation</span>
                            @endif
                            <form method="POST" action="{{ route('inventory.loans.approve', $booking) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="cr-btn cr-btn-sm cr-btn-primary" @disabled($loan['clash'])>Approve</button>
                            </form>
                            <button type="submit" form="decline-{{ $booking->id }}" class="cr-btn cr-btn-sm cr-btn-danger">Decline</button>
                        </div>
                    </td>
                @endif
                @break

            @case('reserved')
            @case('missed')
                <td class="font-semibold text-ink">{{ number_format($loan['units']) }}</td>
                <td>
                    <p class="text-ink">{{ $loan['pickup_on'] }}</p>
                    @if($state === 'missed')
                        <span class="pill p-amber mt-1">Not picked up</span>
                    @elseif($loan['pickup_today'])
                        <span class="pill p-green mt-1">Today</span>
                    @endif
                </td>
                <td class="muted">{{ $loan['due_on'] }}</td>
                <td>
                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" @click="extending = ! extending; open = false" class="cr-btn cr-btn-sm" :aria-expanded="extending">Change return date</button>
                        <form method="POST" action="{{ route('inventory.loans.release', $booking) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="cr-btn cr-btn-sm cr-btn-primary">Hand over</button>
                        </form>
                        <button type="submit" form="decline-{{ $booking->id }}" class="cr-btn cr-btn-sm cr-btn-danger">Cancel</button>
                    </div>
                </td>
                @break

            @case('on_loan')
                <td class="font-semibold text-ink">{{ number_format($loan['outstanding']) }}@if($loan['outstanding'] !== $loan['units'])<span class="text-[12px] font-normal text-ink-soft"> of {{ number_format($loan['units']) }}</span>@endif</td>
                <td class="muted">{{ $loan['released_on'] }}@if($loan['released_by'])<br><span class="text-[12px]">by {{ $loan['released_by'] }}</span>@endif</td>
                <td>
                    <p class="text-ink">{{ $loan['due_on'] }}</p>
                    @if($loan['due_today'])
                        <span class="pill p-amber mt-1">Due today</span>
                    @endif
                </td>
                <td>
                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" @click="extending = ! extending; open = false" class="cr-btn cr-btn-sm" :aria-expanded="extending">Change return date</button>
                        <button type="button" @click="open = ! open; extending = false" class="cr-btn cr-btn-sm cr-btn-primary" :aria-expanded="open">Record return</button>
                    </div>
                </td>
                @break

            @case('returned')
                <td class="muted">{{ number_format($loan['units']) }}</td>
                <td>
                    <div class="flex flex-wrap gap-1">
                        @if($loan['returned'] - $loan['damaged'] > 0)
                            <span class="pill p-green">{{ number_format($loan['returned'] - $loan['damaged']) }} good</span>
                        @endif
                        @if($loan['damaged'] > 0)
                            <span class="pill p-amber">{{ number_format($loan['damaged']) }} damaged</span>
                        @endif
                        @if($loan['lost'] > 0)
                            <span class="pill p-red">{{ number_format($loan['lost']) }} missing</span>
                        @endif
                    </div>
                    @if($loan['notes'])
                        <p class="mt-1 text-[12px] text-ink-soft">{{ $loan['notes'] }}</p>
                    @endif
                </td>
                <td class="muted">{{ $loan['returned_on'] }}@if($loan['returned_by'])<br><span class="text-[12px]">by {{ $loan['returned_by'] }}</span>@endif</td>
                <td></td>
                @break
        @endswitch
    </tr>

    @if(in_array($state, ['overdue', 'on_loan'], true))
        {{-- Record a return: what came back, how many of those are damaged,
             how many are missing. Anything not accounted for stays on loan. --}}
        <tr x-show="open" x-cloak>
            <td colspan="{{ $columns }}" class="inv-edit">
                <form method="POST" action="{{ route('inventory.loans.return', $booking) }}"
                      x-data="{ back: {{ $loan['outstanding'] }}, damaged: 0, lost: 0, out: {{ $loan['outstanding'] }} }"
                      class="flex flex-wrap items-end gap-3">
                    @csrf @method('PATCH')
                    <label class="block">
                        <span class="inv-field-label">Came back</span>
                        <input type="number" name="returned" min="0" step="1" required inputmode="numeric" data-whole-number
                               x-model.number="back" class="st-filter-field inv-num">
                    </label>
                    <label class="block">
                        <span class="inv-field-label">Of those, damaged</span>
                        <input type="number" name="damaged" min="0" step="1" inputmode="numeric" data-whole-number
                               x-model.number="damaged" class="st-filter-field inv-num">
                    </label>
                    <label class="block">
                        <span class="inv-field-label">Missing</span>
                        <input type="number" name="lost" min="0" step="1" inputmode="numeric" data-whole-number
                               x-model.number="lost" class="st-filter-field inv-num">
                    </label>
                    <label class="block min-w-[12rem] flex-1">
                        <span class="inv-field-label">Note (optional)</span>
                        <input type="text" name="notes" maxlength="1000" placeholder="e.g. two legs cracked" class="st-filter-field w-full">
                    </label>
                    <button type="submit" class="cr-btn cr-btn-primary cr-btn-sm"
                            :disabled="! ([back, damaged, lost].every(Number.isInteger) && back + lost >= 1 && back + lost <= out && damaged <= back && damaged >= 0)">Save return</button>
                    <p class="w-full text-[12px]" role="status"
                       :class="back + lost > out || damaged > back ? 'font-semibold text-status-red' : 'text-ink-soft'"
                       x-text="back + lost > out ? 'Only ' + out + ' are out on this loan.'
                            : damaged > back ? 'Damaged ones are part of what came back.'
                            : (out - back - lost) > 0 ? (out - back - lost) + ' will stay on loan until they come back.'
                            : 'This closes the loan. Damaged ones go out of service; missing ones come off the count.'"></p>
                </form>
            </td>
        </tr>
    @endif

    @if(in_array($state, ['overdue', 'on_loan', 'reserved', 'missed'], true))
        {{-- A new return date: the borrower keeping it longer (or bringing it
             back early). The pickup stays; the extra days are stock-checked. --}}
        <tr x-show="extending" x-cloak>
            <td colspan="{{ $columns }}" class="inv-edit">
                <form method="POST" action="{{ route('inventory.loans.extend', $booking) }}" class="flex flex-wrap items-end gap-3">
                    @csrf @method('PATCH')
                    <label class="block">
                        <span class="inv-field-label">New return date</span>
                        <input type="date" name="return_by" required min="{{ now()->toDateString() }}"
                               value="{{ $booking->ends_at->isPast() ? now()->addDay()->toDateString() : $booking->ends_at->toDateString() }}"
                               class="st-filter-field inv-text">
                    </label>
                    <button type="submit" class="cr-btn cr-btn-primary cr-btn-sm">Save return date</button>
                    <p class="w-full text-[12px] text-ink-soft">
                        Due back {{ $loan['due_on'] }} now. Keeping {{ $loan['is_equipment'] ? 'them' : 'it' }} longer is checked against
                        what is already promised for those days{{ $loan['email'] ? ', and the borrower is emailed the new date' : '' }}.
                    </p>
                </form>
            </td>
        </tr>
    @endif

    @if(in_array($state, ['pending', 'reserved', 'missed'], true))
        {{-- Decline / cancel, as its own form so it can sit beside the approve
             form's buttons without nesting forms. --}}
        <tr class="hidden">
            <td colspan="{{ $columns }}">
                <form id="decline-{{ $booking->id }}" method="POST" action="{{ route('inventory.loans.cancel', $booking) }}"
                      onsubmit="return confirm('{{ $state === 'pending' ? 'Decline this request? The borrower is emailed.' : 'Cancel this borrowing? The items are freed for others.' }}');">
                    @csrf @method('PATCH')
                </form>
            </td>
        </tr>
    @endif
</tbody>
