<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\RouteTemplate;
use App\Models\RouteTemplateStep;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RouteTemplateController extends Controller
{
    public function index(): Factory|View
    {
        $templates = RouteTemplate::withCount('steps')->orderBy('name')->get();

        return view('admin.route-templates.index', ['templates' => $templates]);
    }

    public function create(): Factory|View
    {
        $departments = Department::active()->orderBy('name')->get();

        return view('admin.route-templates.create', ['departments' => $departments]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateTemplate($request);

        $template = DB::transaction(function () use ($validated) {
            $template = RouteTemplate::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => true,
            ]);

            $template->steps()->createMany($validated['steps']);

            return $template;
        });

        return to_route('admin.route-templates.index')
            ->with('success', "Route template {$template->name} created successfully.");
    }

    public function edit(RouteTemplate $routeTemplate): Factory|View
    {
        $routeTemplate->load('steps');
        $departments = Department::active()->orderBy('name')->get();

        return view('admin.route-templates.edit', ['routeTemplate' => $routeTemplate, 'departments' => $departments]);
    }

    public function update(Request $request, RouteTemplate $routeTemplate)
    {
        $validated = $this->validateTemplate($request, $routeTemplate);

        DB::transaction(function () use ($routeTemplate, $validated): void {
            $routeTemplate->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            // Replace the chain wholesale: templates only prefill new requests,
            // so already-materialized request_steps are unaffected.
            $routeTemplate->steps()->delete();
            $routeTemplate->steps()->createMany($validated['steps']);
        });

        return to_route('admin.route-templates.index')
            ->with('success', "Route template {$routeTemplate->name} updated successfully.");
    }

    public function toggleActive(RouteTemplate $routeTemplate)
    {
        $routeTemplate->update(['is_active' => ! $routeTemplate->is_active]);

        $action = $routeTemplate->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Route template {$routeTemplate->name} has been {$action}.");
    }

    public function destroy(RouteTemplate $routeTemplate)
    {
        $routeTemplate->delete();

        return to_route('admin.route-templates.index')
            ->with('success', "Route template {$routeTemplate->name} has been deleted.");
    }

    /**
     * @return array{name: string, description: ?string, steps: array<int, array{step_order: int, department_id: int, action: string, condition: ?string}>}
     */
    private function validateTemplate(Request $request, ?RouteTemplate $ignore = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('route_templates', 'name')->ignore($ignore?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.step_order' => ['required', 'integer', 'min:1', 'max:50'],
            'steps.*.department_id' => ['required', 'exists:departments,id'],
            'steps.*.action' => ['required', 'string', 'max:100'],
            'steps.*.condition' => ['nullable', Rule::in(array_keys(RouteTemplateStep::CONDITIONS))],
        ]);
    }
}
