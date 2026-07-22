<?php

namespace App\Http\Controllers;

/** Header-bell actions: open (mark read + follow) and mark-all-read. */
class NotificationController extends Controller
{
    public function open(string $id)
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
