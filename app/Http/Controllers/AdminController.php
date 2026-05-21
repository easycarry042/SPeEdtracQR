<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentScan;
use App\Models\User;

class AdminController extends Controller
{
    public function dashboard()
    {
        $totalDocuments   = Document::count();
        $totalStaff       = User::role(['staff', 'receiving_staff', 'department_admin'])->count();
        $totalDepartments = Department::count();
        $pendingDocuments = Document::whereIn('status', ['pending', 'in_transit', 'returned'])->count();

        $recentActivity = Document::with(['creator', 'currentDepartment'])
            ->latest()
            ->take(10)
            ->get();

        $recentScans = DocumentScan::with(['document', 'department', 'user'])
            ->latest('scanned_at')
            ->take(5)
            ->get();

        return view('admin.dashboard', compact(
            'totalDocuments',
            'totalStaff',
            'totalDepartments',
            'pendingDocuments',
            'recentActivity',
            'recentScans'
        ));
    }
}
