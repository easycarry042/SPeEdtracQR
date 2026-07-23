<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::withCount('users')->orderBy('name')->get();

        return view('admin.departments.index', compact('departments'));
    }

    public function create()
    {
        return view('admin.departments.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:departments,name',
            'code' => 'required|string|max:10|alpha_num|unique:departments,code',
        ]);

        $department = Department::create([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'is_active' => true,
        ]);

        return redirect()->route('admin.departments.index')
            ->with('success', "Department {$department->name} created successfully.");
    }

    public function edit(Department $department)
    {
        return view('admin.departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->ignore($department->id)],
            'code' => ['required', 'string', 'max:10', 'alpha_num', Rule::unique('departments', 'code')->ignore($department->id)],
        ]);

        $department->update([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
        ]);

        return redirect()->route('admin.departments.index')
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
