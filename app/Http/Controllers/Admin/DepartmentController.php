<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    /** An office's turnaround on internal requests: one hour to 30 days. */
    private const array TURNAROUND_RULES = ['nullable', 'integer', 'min:1', 'max:720'];

    public function index(): Factory|View
    {
        $departments = Department::withCount([
            'users',
            // Citizen services this office handles. Zero means no citizen
            // request ever reaches it — fine for an internal-only office,
            // a routing gap for anything else.
            'requestTypes as services_count' => fn ($q) => $q->where('is_active', true),
        ])->orderBy('name')->get();

        // Who triages for each office: its active supervisors, by name.
        $supervisors = User::role('Supervisor')
            ->where('is_active', true)
            ->whereIn('department_id', $departments->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'name', 'department_id'])
            ->groupBy('department_id');

        return view('admin.departments.index', ['departments' => $departments, 'supervisors' => $supervisors]);
    }

    public function create(): Factory|View
    {
        return view('admin.departments.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:departments,name'],
            'code' => ['required', 'string', 'max:10', 'alpha_num', 'unique:departments,code'],
            'internal_step_hours' => self::TURNAROUND_RULES,
        ]);

        $department = Department::create([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'is_active' => true,
            'internal_step_hours' => $validated['internal_step_hours'] ?? Department::DEFAULT_INTERNAL_STEP_HOURS,
        ]);

        return to_route('admin.departments.index')
            ->with('success', "Department {$department->name} created successfully.");
    }

    public function edit(Department $department): Factory|View
    {
        return view('admin.departments.edit', ['department' => $department]);
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->ignore($department->id)],
            'code' => ['required', 'string', 'max:10', 'alpha_num', Rule::unique('departments', 'code')->ignore($department->id)],
            'internal_step_hours' => self::TURNAROUND_RULES,
        ]);

        $department->update([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'internal_step_hours' => $validated['internal_step_hours'] ?? $department->internalStepHours(),
        ]);

        return to_route('admin.departments.index')
            ->with('success', "Department {$department->name} updated successfully.");
    }

    /**
     * Deactivate instead of delete: departments are referenced by users, route
     * templates and request chains, so they are never removed outright.
     */
    public function toggleActive(Department $department)
    {
        $department->update(['is_active' => ! $department->is_active]);

        $action = $department->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Department {$department->name} has been {$action}.");
    }
}
