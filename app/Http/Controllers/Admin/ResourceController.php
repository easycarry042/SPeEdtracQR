<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Resource;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The super admin's catalog of what the municipality lends and reserves.
 *
 * There is one Resources page for everyone (inventory.index): the lending
 * desk, the stock and the facilities. A super admin sees it with the catalog
 * controls this controller serves — add an item, edit what it is, deactivate
 * or delete it. The page they used to have here was a separate list with no
 * stock and no lending, and could not even add equipment.
 */
class ResourceController extends Controller
{
    /** The catalog lives on the shared Resources page now. */
    public function index(): RedirectResponse
    {
        return to_route('inventory.index');
    }

    public function create(): Factory|View
    {
        return view('admin.resources.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateResource($request);
        $isEquipment = $validated['kind'] === Resource::KIND_EQUIPMENT;

        $resource = Resource::create([
            'name' => $validated['name'],
            'kind' => $validated['kind'],
            'description' => $validated['description'] ?? null,
            'location' => $validated['location'] ?? null,
            'total_quantity' => $isEquipment ? ($validated['total_quantity'] ?? null) : null,
            'capacity' => $isEquipment ? null : ($validated['capacity'] ?? null),
            'is_active' => true,
            'sort_order' => (int) (Resource::max('sort_order') ?? 0) + 1,
        ]);

        activity()->performedOn($resource)->log("Added {$resource->name} to the catalog.");

        return to_route('inventory.index')->with('status', "{$resource->name} added.");
    }

    public function edit(Resource $resource): Factory|View
    {
        return view('admin.resources.edit', [
            'resource' => $resource,
            'kindLocked' => $this->kindLocked($resource),
        ]);
    }

    /**
     * Name, description, where it is — and what kind of thing it is, but only
     * while nothing has been booked against it: a facility and a pile of
     * chairs are counted differently, so turning one into the other would
     * rewrite what every existing booking meant.
     */
    public function update(Request $request, Resource $resource): RedirectResponse
    {
        $validated = $this->validateResource($request, $resource);

        if ($validated['kind'] !== $resource->kind && $this->kindLocked($resource)) {
            return back()->withInput()->withErrors([
                'kind' => "{$resource->name} already has bookings, so it can no longer change between facility and equipment. Add a new item instead.",
            ]);
        }

        $resource->update([
            'name' => $validated['name'],
            'kind' => $validated['kind'],
            'description' => $validated['description'] ?? null,
            'location' => $validated['location'] ?? null,
        ]);

        activity()->performedOn($resource)->log("Edited {$resource->name}'s details.");

        return to_route('inventory.index')->with('status', "{$resource->name} updated.");
    }

    public function toggleActive(Resource $resource): RedirectResponse
    {
        $resource->update(['is_active' => ! $resource->is_active]);

        $action = $resource->is_active ? 'activated' : 'deactivated';
        activity()->performedOn($resource)->log(ucfirst($action)." {$resource->name}.");

        return back()->with('status', "{$resource->name} has been {$action}.");
    }

    /**
     * Deleting takes the item's bookings with it, so it is refused while any
     * of them is still live — promised, out on loan or waiting for approval.
     * Deactivating keeps the history and stops new requests.
     */
    public function destroy(Resource $resource): RedirectResponse
    {
        $live = $resource->bookings()
            ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_APPROVED])
            ->whereNull('returned_at')
            ->where(fn ($q) => $q->whereNotNull('released_at')->orWhere('ends_at', '>', now()))
            ->count();

        if ($live > 0) {
            return back()->with('error', "{$resource->name} has {$live} live ".($live === 1 ? 'booking' : 'bookings')
                .' (waiting, promised or out on loan), so it cannot be deleted. Deactivate it instead, or finish those first.');
        }

        $resource->delete();

        return to_route('inventory.index')->with('status', "{$resource->name} has been deleted.");
    }

    private function kindLocked(Resource $resource): bool
    {
        return $resource->bookings()->exists();
    }

    /**
     * @return array{name: string, kind: string, description: ?string, location: ?string, total_quantity: ?int, capacity: ?int}
     */
    private function validateResource(Request $request, ?Resource $ignore = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('resources', 'name')->ignore($ignore?->id)],
            'kind' => ['required', Rule::in([Resource::KIND_FACILITY, Resource::KIND_EQUIPMENT])],
            'description' => ['nullable', 'string', 'max:500'],
            'location' => ['nullable', 'string', 'max:255'],
            'total_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ], [
            'kind.required' => 'Choose whether this is a facility or equipment.',
            'total_quantity.integer' => 'The number owned is a whole number.',
        ]);
    }
}
