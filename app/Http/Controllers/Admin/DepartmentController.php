<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::orderBy('name')->paginate(20);

        return view('admin.departments.index', compact('departments'));
    }

    public function create()
    {
        return view('admin.departments.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255|unique:departments,name',
            'code'      => 'nullable|string|max:20|unique:departments,code',
            'email'     => 'nullable|email|max:255',
            'sla_hours' => 'required|integer|min:1|max:8760',
        ]);

        Department::create($validated);

        return redirect()->route('admin.departments.index')
            ->with('success', "Department \"{$validated['name']}\" created successfully.");
    }

    public function edit(Department $department)
    {
        return view('admin.departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255|unique:departments,name,' . $department->id,
            'code'      => 'nullable|string|max:20|unique:departments,code,' . $department->id,
            'email'     => 'nullable|email|max:255',
            'sla_hours' => 'required|integer|min:1|max:8760',
        ]);

        $department->update($validated);

        return redirect()->route('admin.departments.index')
            ->with('success', "Department \"{$department->name}\" updated successfully.");
    }

    public function destroy(Department $department)
    {
        $department->delete();

        return back()->with('success', "Department \"{$department->name}\" removed.");
    }
}
