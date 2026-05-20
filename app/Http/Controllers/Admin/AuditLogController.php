<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = Activity::with('causer')->orderBy('created_at', 'desc');

        if ($userId = $request->get('user')) {
            $query->where('causer_id', $userId)
                  ->where('causer_type', User::class);
        }

        if ($logName = $request->get('log')) {
            $query->where('log_name', $logName);
        }

        if ($date = $request->get('date')) {
            $query->whereDate('created_at', $date);
        }

        $logs  = $query->paginate(30)->withQueryString();
        $users = User::orderBy('name')->get(['id', 'name']);

        return view('admin.audit-log.index', compact('logs', 'users'));
    }
}