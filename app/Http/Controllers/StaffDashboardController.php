<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

/**
 * Legacy staff "Requests" tab. Its contents (the assigned-work cockpit) now
 * live inside My Profile, so this route only forwards there — old bookmarks,
 * notification links, and the post-login redirect keep working.
 */
class StaffDashboardController extends Controller
{
    public function index(): RedirectResponse
    {
        return to_route('staff.profile', [
            'user' => auth()->id(),
            'tab' => 'assigned',
        ]);
    }
}
