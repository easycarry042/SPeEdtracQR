<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResourceController extends Controller
{
    public function index(): Factory|View
    {
        $resources = Resource::withCount('bookings')->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.resources.index', ['resources' => $resources]);
    }

    public function create(): Factory|View
    {
        return view('admin.resources.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validateResource($request);

        $resource = Resource::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => true,
            'sort_order' => (int) (Resource::max('sort_order') ?? 0) + 1,
        ]);

        return to_route('admin.resources.index')
            ->with('success', "Resource {$resource->name} created.");
    }

    public function edit(Resource $resource): Factory|View
    {
        return view('admin.resources.edit', ['resource' => $resource]);
    }

    public function update(Request $request, Resource $resource)
    {
        $validated = $this->validateResource($request, $resource);

        $resource->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return to_route('admin.resources.index')
            ->with('success', "Resource {$resource->name} updated.");
    }

    public function toggleActive(Resource $resource)
    {
        $resource->update(['is_active' => ! $resource->is_active]);

        $action = $resource->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Resource {$resource->name} has been {$action}.");
    }

    public function destroy(Resource $resource)
    {
        $resource->delete();

        return to_route('admin.resources.index')
            ->with('success', "Resource {$resource->name} has been deleted.");
    }

    /**
     * @return array{name: string, description: ?string}
     */
    private function validateResource(Request $request, ?Resource $ignore = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('resources', 'name')->ignore($ignore?->id)],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
