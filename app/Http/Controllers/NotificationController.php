<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;

/** Header-bell actions: open (mark read + follow) and mark-all-read. */
class NotificationController extends Controller
{
    public function open(string $id): Redirector|RedirectResponse
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        // The URL is written by our own DocumentEvent notification — not user input.
        return redirect(data_get($notification->data, 'url') ?: route('dashboard'));
    }

    public function readAll()
    {
        auth()->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'All notifications marked as read.');
    }
}
