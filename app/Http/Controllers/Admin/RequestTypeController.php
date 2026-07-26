<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RequestType;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RequestTypeController extends Controller
{
    public function index(): Factory|View
    {
        $requestTypes = RequestType::withCount('requirements')->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.request-types.index', ['requestTypes' => $requestTypes]);
    }

    public function create(): Factory|View
    {
        return view('admin.request-types.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validateType($request);

        $type = DB::transaction(function () use ($validated) {
            $type = RequestType::create([
                'name' => $validated['name'],
                'kind' => $validated['kind'],
                'description' => $validated['description'] ?? null,
                'is_active' => true,
                'sort_order' => (int) (RequestType::max('sort_order') ?? 0) + 1,
            ]);

            $type->requirements()->createMany($this->requirementRows($validated));

            return $type;
        });

        return to_route('admin.request-types.index')
            ->with('success', "Request type {$type->name} created.");
    }

    public function edit(RequestType $requestType): Factory|View
    {
        $requestType->load('requirements');

        return view('admin.request-types.edit', ['requestType' => $requestType]);
    }

    public function update(Request $request, RequestType $requestType)
    {
        $validated = $this->validateType($request, $requestType);

        DB::transaction(function () use ($requestType, $validated): void {
            $requestType->update([
                'name' => $validated['name'],
                'kind' => $validated['kind'],
                'description' => $validated['description'] ?? null,
            ]);

            // Replace the checklist wholesale. Requirements already snapshotted
            // onto submitted requests are unaffected (document_requirements keep
            // their own label/mandatory copy; the FK just nulls out).
            $requestType->requirements()->delete();
            $requestType->requirements()->createMany($this->requirementRows($validated));
        });

        return to_route('admin.request-types.index')
            ->with('success', "Request type {$requestType->name} updated.");
    }

    public function toggleActive(RequestType $requestType)
    {
        $requestType->update(['is_active' => ! $requestType->is_active]);

        $action = $requestType->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Request type {$requestType->name} has been {$action}.");
    }

    public function destroy(RequestType $requestType)
    {
        $requestType->delete();

        return to_route('admin.request-types.index')
            ->with('success', "Request type {$requestType->name} has been deleted.");
    }

    /**
     * @return array{name: string, kind: string, description: ?string, requirements: array<int, array{label: string, is_mandatory?: string|bool}>}
     */
    private function validateType(Request $request, ?RequestType $ignore = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('request_types', 'name')->ignore($ignore?->id)],
            'kind' => ['required', Rule::in([RequestType::KIND_DOCUMENT, RequestType::KIND_BOOKING])],
            'description' => ['nullable', 'string', 'max:500'],
            'requirements' => ['nullable', 'array'],
            'requirements.*.label' => ['required', 'string', 'max:255'],
            'requirements.*.is_mandatory' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * Normalize the submitted requirement rows into createMany payloads,
     * preserving order.
     *
     * @param  array{requirements?: array<int, array{label: string, is_mandatory?: string|bool}>}  $validated
     * @return array<int, array{label: string, is_mandatory: bool, sort_order: int}>
     */
    private function requirementRows(array $validated): array
    {
        return collect($validated['requirements'] ?? [])
            ->values()
            ->map(fn (array $row, int $i): array => [
                'label' => $row['label'],
                'is_mandatory' => (bool) ($row['is_mandatory'] ?? false),
                'sort_order' => $i,
            ])
            ->all();
    }
}
