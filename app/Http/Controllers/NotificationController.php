<?php

namespace App\Http\Controllers;

use App\Models\DepartmentNotification;
use App\Support\AssignmentScope;

class NotificationController extends Controller
{
    public function markRead(DepartmentNotification $notification)
    {
        $user = auth()->user();
        abort_unless($user, 403);
        abort_unless(
            AssignmentScope::canViewAll($user) || AssignmentScope::userCanAccessDocument($notification->document),
            403
        );

        $notification->markRead();

        return redirect()->route('staff.profile', ['user' => $user->id]);
    }
}
