<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Resource;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What the office owns and what is free right now.
 *
 * Staff at the counter get asked two questions all day — "is the gym free on
 * Saturday?" and "how many chairs can we still lend?" — and until now both
 * were answered by memory. This reads the same bookings the calendar does, so
 * the two screens can never disagree.
 */
class InventoryController extends Controller
{
    public function index(): Factory|View
    {
        $now = Carbon::now();

        $resources = Resource::query()
            ->withCount(['bookings' => fn ($q) => $q->where('status', '!=', 'cancelled')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $facilities = $resources->filter->isFacility()->map(fn (Resource $r): array => $this->facilityRow($r, $now))->values();
        $equipment = $resources->filter->isEquipment()->map(fn (Resource $r): array => $this->equipmentRow($r, $now))->values();

        return view('inventory.index', [
            'facilities' => $facilities,
            'equipment' => $equipment,
            'asOf' => $now,
            'tiles' => $this->tiles($facilities, $equipment),
        ]);
    }

    /**
     * Staff correct the counts as stock changes hands — a tent comes back torn,
     * the office buys fifty more chairs. The resource list itself stays with
     * the super admin under Admin → Resources.
     */
    public function update(Request $request, Resource $resource): RedirectResponse
    {
        $validated = $request->validate([
            'total_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'damaged_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'location' => ['nullable', 'string', 'max:255'],
        ], [
            'total_quantity.min' => 'The number owned cannot be negative.',
            'damaged_quantity.min' => 'The number out of service cannot be negative.',
        ]);

        $damaged = (int) ($validated['damaged_quantity'] ?? 0);
        $total = $validated['total_quantity'] ?? null;

        // More broken than owned is not a state the office can be in, and it
        // would read as negative stock on the page.
        if ($resource->isEquipment() && $total !== null && $damaged > (int) $total) {
            return back()->withInput()->withErrors([
                'damaged_quantity' => 'There cannot be more out of service than the office owns.',
            ]);
        }

        $before = $resource->only(['total_quantity', 'damaged_quantity', 'capacity', 'location']);
        $resource->update($validated);

        activity()
            ->performedOn($resource)
            ->withProperties(['before' => $before, 'after' => $resource->only(array_keys($before))])
            ->log("Updated inventory for {$resource->name}.");

        return back()->with('status', "{$resource->name} updated.");
    }

    /**
     * @return array{name: string, resource: \App\Models\Resource, status: string, tone: string, detail: ?string, next: ?string}
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
                ? 'until '.$current->ends_at->format('g:i A')
                : null,
            'next' => $next
                ? $next->starts_at->format('D j M, g:i A')
                : null,
        ];
    }

    /**
     * @return array{resource: \App\Models\Resource, name: string, owned: int, damaged: int, out: int, free: int, pct: int}
     */
    private function equipmentRow(Resource $resource, Carbon $now): array
    {
        $serviceable = $resource->serviceableUnits();
        // "Out" is what is on loan at this moment, not across the whole month —
        // the page answers what can be handed over today.
        $out = $resource->unitsCommitted($now, $now->copy()->addSecond());
        $free = max(0, $serviceable - $out);

        return [
            'resource' => $resource,
            'name' => $resource->name,
            'owned' => (int) $resource->total_quantity,
            'damaged' => (int) $resource->damaged_quantity,
            'out' => $out,
            'free' => $free,
            'pct' => $serviceable > 0 ? (int) round($free / $serviceable * 100) : 0,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $facilities
     * @param  Collection<int, array<string, mixed>>  $equipment
     * @return array<string, int>
     */
    private function tiles(Collection $facilities, Collection $equipment): array
    {
        return [
            'facilities_free' => $facilities->where('tone', 'free')->count(),
            'facilities_total' => $facilities->count(),
            'units_free' => (int) $equipment->sum('free'),
            'units_out' => (int) $equipment->sum('out'),
            'units_damaged' => (int) $equipment->sum('damaged'),
        ];
    }
}
