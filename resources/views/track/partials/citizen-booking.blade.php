{{-- The citizen's borrowing or reservation, on their tracking page: what they
     asked for, what the office confirmed, and where it stands — the same
     lending states the counter works with (Booking::lendingState), in the
     citizen's words. Offices only; staff stay anonymous. Expects $booking. --}}
@php
    $resource = $booking->resource;
    $isEquipment = (bool) $resource?->isEquipment();
    $state = $booking->lendingState();
    $units = $isEquipment ? number_format($state === \App\Models\Booking::LENDING_PENDING ? $booking->unitsRequested() : $booking->units()).' ' : '';
    $what = $units.($resource?->name ?? 'Reservation');
    $pickup = $booking->starts_at->format('D, M j, Y · g:i A');
    $returnBy = $isEquipment ? $booking->ends_at->format('D, M j, Y · g:i A') : $booking->ends_at->format('g:i A');
    $them = $isEquipment ? (($state === \App\Models\Booking::LENDING_PENDING ? $booking->unitsRequested() : $booking->units()) === 1 ? 'it' : 'them') : 'it';

    [$label, $tone, $line] = match ($state) {
        \App\Models\Booking::LENDING_PENDING => ['Waiting for confirmation', 'amber', 'The office has not confirmed this yet. You will get an email when it does.'],
        \App\Models\Booking::LENDING_RESERVED => ['Confirmed', 'green', $isEquipment ? "Set aside for you. Pick {$them} up on the date below." : 'Reserved for you on the date below.'],
        \App\Models\Booking::LENDING_MISSED => ['Not picked up', 'amber', 'The pickup date has passed. Please contact the office if you still need '.$them.'.'],
        \App\Models\Booking::LENDING_ON_LOAN => ['With you', 'green', "Please bring {$them} back by the return date."],
        \App\Models\Booking::LENDING_OVERDUE => ['Overdue', 'red', $booking->daysOverdue().' '.($booking->daysOverdue() === 1 ? 'day' : 'days')." past the return date. Please bring {$them} back as soon as you can, or contact the office."],
        \App\Models\Booking::LENDING_RETURNED => [$isEquipment ? 'Returned' : 'Done', 'muted', $isEquipment ? 'Received back on '.$booking->returned_at?->format('M j, Y').'. Thank you.' : 'This reservation is finished.'],
        default => ['Cancelled', 'red', 'This reservation was cancelled. Please contact the office if this is unexpected.'],
    };
@endphp

<div class="cz-card">
    <div class="cz-card-head">
        <h2 class="cz-card-title">{{ $isEquipment ? 'Your borrowing' : 'Your reservation' }}</h2>
        <span @class(['pill', 'p-green' => $tone === 'green', 'p-amber' => $tone === 'amber', 'p-red' => $tone === 'red', 'p-muted' => $tone === 'muted'])>{{ $label }}</span>
    </div>
    <div class="cz-card-body">
        <p class="text-[17px] font-semibold text-[#1a1b1a]">{{ $what }}</p>
        <p class="mt-1 text-[15px] text-[#4a4f4a]">{{ $line }}</p>

        @if($state !== \App\Models\Booking::LENDING_CANCELLED)
            <dl class="mt-4 grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                <div>
                    <dt class="cz-key">{{ $isEquipment ? 'Pickup' : 'Date' }}</dt>
                    <dd class="mt-1 text-[16px] text-[#1a1b1a]">{{ $pickup }}</dd>
                </div>
                <div>
                    <dt class="cz-key">{{ $isEquipment ? 'Return by' : 'Until' }}</dt>
                    <dd @class(['mt-1 text-[16px]', 'font-semibold text-status-red' => $state === \App\Models\Booking::LENDING_OVERDUE, 'text-[#1a1b1a]' => $state !== \App\Models\Booking::LENDING_OVERDUE])>{{ $returnBy }}</dd>
                </div>
                @if($isEquipment && $state !== \App\Models\Booking::LENDING_PENDING && $booking->units() !== $booking->unitsRequested())
                    <div class="sm:col-span-2">
                        <dt class="cz-key">Quantity</dt>
                        <dd class="mt-1 text-[16px] text-[#1a1b1a]">You asked for {{ number_format($booking->unitsRequested()) }}; the office confirmed {{ number_format($booking->units()) }}.</dd>
                    </div>
                @endif
                @if($isEquipment && $state === \App\Models\Booking::LENDING_ON_LOAN && $booking->unitsOutstanding() !== $booking->units())
                    <div class="sm:col-span-2">
                        <dt class="cz-key">Still with you</dt>
                        <dd class="mt-1 text-[16px] text-[#1a1b1a]">{{ number_format($booking->unitsOutstanding()) }} of {{ number_format($booking->units()) }}</dd>
                    </div>
                @endif
            </dl>
        @endif
    </div>
</div>
