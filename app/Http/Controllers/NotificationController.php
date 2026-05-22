<?php

namespace App\Http\Controllers;

use App\Models\DepartmentNotification;
use App\Support\DepartmentScope;

class NotificationController extends Controller
{
    public function markRead(DepartmentNotification $notification)
    {
        $user = auth()->user();

        if (DepartmentScope::isOrgWide($user)) {
            abort(403);
        }

        if ((int) $notification->department_id !== (int) $user->department_id) {
            abort(403);
        }

        $notification->markRead();

        return redirect()->route('movements.index', ['tab' => 'inbox']);
    }
}
