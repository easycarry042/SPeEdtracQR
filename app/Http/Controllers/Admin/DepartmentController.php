<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Document;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::orderBy('name')->paginate(20);

        $allDepartments = Department::orderBy('sla_hours')->get();
        $tightestSlaDept = $allDepartments->first();

        $queuedDocuments = Document::with(['scans' => fn ($q) => $q->where('action', 'in')->orderBy('scanned_at', 'desc')])
            ->whereIn('status', DocumentStatus::activeValues())
            ->whereNotNull('current_department_id')
            ->get()
            ->map(function ($doc) use ($allDepartments) {
                $slaHours = $allDepartments->firstWhere('id', $doc->current_department_id)?->sla_hours ?? 0;
                $elapsedHours = optional($doc->scans->first())->scanned_at?->diffInMinutes(now()) / 60 ?? 0;
                $doc->slaPct = $slaHours > 0 ? min(round(($elapsedHours / $slaHours) * 100), 100) : 0;
                $doc->slaOverdue = $slaHours > 0 && $elapsedHours > $slaHours;

                return $doc;
            });

        $departmentStats = $allDepartments->mapWithKeys(function ($dept) use ($queuedDocuments) {
            $docs = $queuedDocuments->where('current_department_id', $dept->id);
            $overdueCount = $docs->where('slaOverdue', true)->count();
            $avgPct = $docs->isNotEmpty() ? round($docs->avg('slaPct')) : 0;

            return [$dept->id => [
                'in_queue' => $docs->count(),
                'overdue' => $overdueCount,
                'avg_pct' => $avgPct,
                'health' => $overdueCount > 0 ? 'overdue' : ($avgPct >= 75 ? 'watch' : 'healthy'),
            ]];
        });

        $departmentsTotal = $allDepartments->count();
        $queueTotal = $queuedDocuments->count();
        $queueOverdueTotal = $queuedDocuments->where('slaOverdue', true)->count();

        return view('admin.departments.index', compact(
            'departments',
            'departmentStats',
            'tightestSlaDept',
            'departmentsTotal',
            'queueTotal',
            'queueOverdueTotal'
        ));
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
