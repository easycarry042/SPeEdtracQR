<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Document;
use Illuminate\Http\Request;

class MovementController extends Controller
{
    public function index(Request $request)
    {
        $query = Document::with([
            'currentDepartment',
            'creator',
            'scans' => fn ($q) => $q->where('action', 'in')->orderBy('scanned_at', 'desc'),
        ])
            ->whereIn('status', ['pending', 'in_transit'])
            ->whereNotNull('current_department_id');

        if ($dept = $request->get('department')) {
            $query->where('current_department_id', $dept);
        }

        if ($request->boolean('overdue')) {
            $query->whereHas('scans', function ($q) {
                $q->where('action', 'in')
                  ->whereColumn('document_scans.department_id', 'documents.current_department_id')
                  ->whereRaw(
                      '(julianday("now") - julianday(document_scans.scanned_at)) * 24 > (select sla_hours from departments where id = documents.current_department_id)'
                  );
            });
        }

        $documents   = $query->orderBy('updated_at', 'asc')->paginate(25)->withQueryString();
        $departments = Department::orderBy('name')->get();

        return view('movements.index', compact('documents', 'departments'));
    }
}