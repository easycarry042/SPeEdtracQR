<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;

class LogUserLogout
{
    public function handle(Logout $event): void
    {
        if (! $event->user) {
            return;
        }

        activity('auth')
            ->causedBy($event->user)
            ->withProperties([
                'ip'    => request()->ip(),
                'email' => $event->user->email,
            ])
            ->log('User logged out');
    }
}