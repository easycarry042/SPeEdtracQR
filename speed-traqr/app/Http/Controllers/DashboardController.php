<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Department;
use App\Models\DocumentScan;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Total documents
        $totalDocs = Document::count();
        $inTransit = Document::where('status', 'in_transit')->count();
        $completed = Document::where('status', 'completed')->count();
        $overdue = Document::get()->filter->isOverdue()->count();

        // Documents per department (current location)
        $deptLoad = Department::withCount(['documents' => function ($q) {
            $q->where('status', 'in_transit');
        }])->get()->map(function ($dept) {
            return ['department' => $dept->name, 'count' => $dept->documents_count];
        });

        // Heatmap: average dwell time per department (in hours)
        $dwellTimes = DB::table('document_scans as s1')
            ->join('document_scans as s2', function ($join) {
                $join->on('s1.document_id', '=', 's2.document_id')
                    ->whereRaw('s1.scanned_at < s2.scanned_at')
                    ->where('s1.action', 'in')
                    ->where('s2.action', 'out');
            })
            ->join('departments', 's1.department_id', '=', 'departments.id')
            ->select('departments.name as department', DB::raw('AVG(TIMESTAMPDIFF(HOUR, s1.scanned_at, s2.scanned_at)) as avg_hours'))
            ->groupBy('departments.id')
            ->get();

        return response()->json([
            'stats' => compact('totalDocs', 'inTransit', 'completed', 'overdue'),
            'department_load' => $deptLoad,
            'dwell_times' => $dwellTimes,
        ]);
    }
}