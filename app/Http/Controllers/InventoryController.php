<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\Booking;
use App\Models\Resource;
use App\Support\Lending;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * Resources: what the office owns, who has it, and what is free.
 *
 * Staff at the counter get asked "is the gym free on Saturday?" and "how many
 * chairs can we still lend?" all day. This page answers both from the same
 * bookings the calendar reads, and is where a borrowing is run end to end —
 * approved with a count, handed over, taken back (App\Support\Lending).
 */
class InventoryController extends Controller
{
    public function index(): Factory|View
    {
        $now = Carbon::now();

        $resources = Resource::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $facilities = $resources->filter->isFacility()->map(fn (Resource $r): array => $this->facilityRow($r, $now))->values();
        $equipment = $resources->filter->isEquipment()->map(fn (Resource $r): array => $this->equipmentRow($r))->values();
        $desk = $this->desk($now);

        return view('inventory.index', [
            'facilities' => $facilities,
            'equipment' => $equipment,
            'desk' => $desk,
            'history' => $this->history($resources->pluck('id')->all()),
            'asOf' => $now,
            'tiles' => $this->tiles($facilities, $equipment, $desk),
        ]);
    }

    /**
     * Staff correct the counts as stock changes hands — the office buys fifty
     * more chairs, a broken tent is repaired. The resource list itself stays
     * with the super admin under Admin → Resources.
     */
    public function update(Request $request, Resource $resource): RedirectResponse
    {
        $validated = $request->validate([
            'total_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'damaged_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'location' => ['nullable', 'string', 'max:255'],
        ], [
            'total_quantity.integer' => 'The number owned is a whole number.',
            'damaged_quantity.integer' => 'The number out of service is a whole number.',
            'total_quantity.min' => 'The number owned cannot be negative.',
            'damaged_quantity.min' => 'The number out of service cannot be negative.',
        ]);

        if ($resource->isEquipment()) {
            $damaged = (int) ($validated['damaged_quantity'] ?? 0);
            $total = $validated['total_quantity'] ?? null;

            // More broken than owned is not a state the office can be in, and it
            // would read as negative stock on the page.
            if ($total !== null && $damaged > (int) $total) {
                return back()->withInput()->withErrors([
                    'damaged_quantity' => 'There cannot be more out of service than the office owns.',
                ]);
            }

            // Nor can it own fewer than are out with borrowers right now.
            $onLoan = $resource->unitsOnLoan();

            if ($total !== null && $onLoan > 0 && (int) $total < $damaged + $onLoan) {
                return back()->withInput()->withErrors([
                    'total_quantity' => "{$onLoan} {$resource->name} are out on loan".($damaged ? " and {$damaged} are out of service" : '')
                        .', so the office owns at least '.($damaged + $onLoan).'. Record a loss on the loan instead if some are gone.',
                ]);
            }
        }

        $before = $resource->only(['total_quantity', 'damaged_quantity', 'capacity', 'location']);
        $resource->update($validated);

        $changes = collect($resource->only(array_keys($before)))
            ->filter(fn ($value, string $key): bool => $value != $before[$key])
            ->map(fn ($value, string $key): string => match ($key) {
                'total_quantity' => 'owned '.($before[$key] ?? 0).' → '.($value ?? 0),
                'damaged_quantity' => 'out of service '.($before[$key] ?? 0).' → '.($value ?? 0),
                'capacity' => 'holds '.($before[$key] ?? '—').' → '.($value ?? '—'),
                default => 'location updated',
            });

        if ($changes->isNotEmpty()) {
            activity()
                ->performedOn($resource)
                ->withProperties(['before' => $before, 'after' => $resource->only(array_keys($before))])
                ->log('Stock count: '.$changes->implode(', ').'.');
        }

        return back()->with('status', "{$resource->name} updated.");
    }

    /**
     * @return array{name: string, resource: \App\Models\Resource, status: string, tone: string, detail: ?string, next: ?string, booking: ?Booking}
     */
    private function facilityRow(Resource $resource, Carbon $now): array
    {
        $current = $resource->bookingAt($now);
        $next = $resource->nextBookingAfter($now);

        return [
            'resource' => $resource,
            'name' => $resource->name,
            'status' => $current ? 'In use' : 'Free',
            'tone' => $current ? 'busy' : 'free',
            'detail' => $current
                ? 'until '.$current->ends_at->format('g:i A').' · '.($current->document?->citizen_name ?: 'Reserved')
                : null,
            'next' => $next
                ? $next->starts_at->format('D j M, g:i A')
                : null,
            'booking' => $current,
        ];
    }

    /**
     * Owned, less out of service, is what the office can lend at all. Of that,
     * some is out with borrowers (on loan, overdue included) and some is
     * promised to approved pickups still to come; what is left is available.
     *
     * @return array{resource: \App\Models\Resource, name: string, owned: int, damaged: int, on_loan: int, reserved: int, on_hand: int, available: int, pct: int}
     */
    private function equipmentRow(Resource $resource): array
    {
        $serviceable = $resource->serviceableUnits();
        $onLoan = $resource->unitsOnLoan();
        $reserved = $resource->unitsReserved();
        $onHand = max(0, $serviceable - $onLoan);
        $available = max(0, $onHand - $reserved);

        return [
            'resource' => $resource,
            'name' => $resource->name,
            'owned' => (int) $resource->total_quantity,
            'damaged' => (int) $resource->damaged_quantity,
            'on_loan' => $onLoan,
            'reserved' => $reserved,
            'on_hand' => $onHand,
            'available' => $available,
            'pct' => $serviceable > 0 ? (int) round($available / $serviceable * 100) : 0,
        ];
    }

    /**
     * The lending desk's queues. Equipment runs the whole loan here; a
     * facility only needs approving (the facilities table marks it done).
     *
     * @return array<string, Collection<int, array<string, mixed>>>
     */
    private function desk(Carbon $now): array
    {
        $with = ['resource', 'document.assignedTo', 'releasedBy', 'returnedBy'];

        $toApprove = Booking::query()
            ->where('status', Booking::STATUS_PENDING)
            ->where('ends_at', '>', $now)
            ->whereHas('document', fn ($q) => $q->where('status', '!=', DocumentStatus::Denied->value))
            ->whereHas('resource')
            ->with($with)
            ->orderBy('starts_at')
            ->get();

        $open = Booking::query()
            ->open()
            ->whereHas('resource', fn ($q) => $q->equipment())
            ->with($with)
            ->orderBy('ends_at')
            ->get();

        $returned = Booking::query()
            ->where('status', Booking::STATUS_APPROVED)
            ->where('returned_at', '>=', $now->copy()->subDays(30))
            ->whereNotNull('released_at')
            ->whereHas('resource', fn ($q) => $q->equipment())
            ->whereHas('document')
            ->with($with)
            ->latest('returned_at')
            ->limit(50)
            ->get();

        $state = fn (string ...$states) => fn (Booking $b): bool => in_array($b->lendingState($now), $states, true);

        return [
            'overdue' => $open->filter($state(Booking::LENDING_OVERDUE))->map(fn ($b) => $this->loanRow($b, $now))->values(),
            'approve' => $toApprove->map(fn ($b) => $this->loanRow($b, $now))->values(),
            'pickup' => $open->filter($state(Booking::LENDING_RESERVED, Booking::LENDING_MISSED))
                ->sortBy('starts_at')->map(fn ($b) => $this->loanRow($b, $now))->values(),
            'on_loan' => $open->filter($state(Booking::LENDING_ON_LOAN))->map(fn ($b) => $this->loanRow($b, $now))->values(),
            'returned' => $returned->map(fn ($b) => $this->loanRow($b, $now))->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function loanRow(Booking $booking, Carbon $now): array
    {
        $resource = $booking->resource;
        $document = $booking->document;
        $isEquipment = (bool) $resource?->isEquipment();
        $sameDay = $booking->starts_at->isSameDay($booking->ends_at);

        return [
            'booking' => $booking,
            'state' => $booking->lendingState($now),
            'item' => $resource?->name ?? 'Resource',
            'is_equipment' => $isEquipment,
            'borrower' => $document?->citizen_name ?: 'Requester',
            'contact' => $document?->citizen_contact,
            'email' => $document?->citizen_email,
            'tracking' => $document?->tracking_number,
            'handler' => $document?->assignedTo?->name,
            'requested' => $booking->unitsRequested(),
            'units' => $booking->units(),
            'outstanding' => $booking->unitsOutstanding(),
            'returned' => (int) $booking->returned_quantity,
            'damaged' => (int) $booking->damaged_quantity,
            'lost' => (int) $booking->lost_quantity,
            'notes' => $booking->return_notes,
            'window' => $sameDay
                ? $booking->starts_at->format('M j, g:i A').' – '.$booking->ends_at->format('g:i A')
                : $booking->starts_at->format('M j').' – '.$booking->ends_at->format('M j, Y'),
            'pickup_on' => $booking->starts_at->format('D, M j'),
            'pickup_today' => $booking->starts_at->isSameDay($now),
            'due_on' => $booking->ends_at->format('D, M j'),
            'due_today' => $booking->ends_at->isSameDay($now),
            'days_overdue' => $booking->daysOverdue($now),
            'released_on' => $booking->released_at?->format('M j, g:i A'),
            'released_by' => $booking->releasedBy?->name,
            'returned_on' => $booking->returned_at?->format('M j, g:i A'),
            'returned_by' => $booking->returnedBy?->name,
            'reminded_on' => $booking->overdue_reminded_at?->format('M j, g:i A'),
            // What the approval check would allow for these dates — drives the
            // live "not enough" warning before anyone presses Approve.
            'free' => $booking->status === Booking::STATUS_PENDING && $isEquipment
                ? $resource->unitsAvailable($booking->starts_at, $booking->ends_at, $booking->id)
                : null,
            'serviceable' => $isEquipment ? $resource->serviceableUnits() : null,
            // Approval waits for the request's own order: assigned, then accepted.
            'not_ready' => $booking->status === Booking::STATUS_PENDING ? Lending::requestNotReady($booking) : null,
            'clash' => $booking->status === Booking::STATUS_PENDING && $resource && ! $isEquipment
                && $resource->approvedConflicts($booking->starts_at, $booking->ends_at, $booking->id)->isNotEmpty(),
        ];
    }

    /**
     * Each item's recent changes — counts edited, loans handed over and
     * returned — newest first. One query for the whole page.
     *
     * @param  array<int, int>  $resourceIds
     * @return array<int, array<int, array{line: string, who: ?string, when: string}>>
     */
    private function history(array $resourceIds): array
    {
        if ($resourceIds === []) {
            return [];
        }

        return Activity::query()
            ->where('subject_type', (new Resource)->getMorphClass())
            ->whereIn('subject_id', $resourceIds)
            ->with('causer')
            ->latest()
            ->limit(400)
            ->get()
            ->groupBy('subject_id')
            ->map(fn (Collection $rows): array => $rows->take(8)->map(fn (Activity $a): array => [
                'line' => $a->description,
                'who' => $a->causer?->name,
                'when' => $a->created_at?->format('M j, g:i A') ?? '',
            ])->values()->all())
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $facilities
     * @param  Collection<int, array<string, mixed>>  $equipment
     * @param  array<string, Collection<int, array<string, mixed>>>  $desk
     * @return array<string, int>
     */
    private function tiles(Collection $facilities, Collection $equipment, array $desk): array
    {
        return [
            'facilities_free' => $facilities->where('tone', 'free')->count(),
            'facilities_total' => $facilities->count(),
            'units_available' => (int) $equipment->sum('available'),
            'units_reserved' => (int) $equipment->sum('reserved'),
            'units_on_loan' => (int) $equipment->sum('on_loan'),
            'units_damaged' => (int) $equipment->sum('damaged'),
            'overdue' => $desk['overdue']->count(),
            'to_approve' => $desk['approve']->whereNull('not_ready')->count(),
            'pickups_today' => $desk['pickup']->where('pickup_today', true)->count(),
        ];
    }
}
