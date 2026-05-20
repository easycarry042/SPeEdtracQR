<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class LogUserLogin
{
    public function handle(Login $event): void
    {
        activity('auth')
            ->causedBy($event->user)
            ->withProperties([
                'ip'         => request()->ip(),
                'user_agent' => request()->userAgent(),
                'email'      => $event->user->email,
            ])
            ->log('User logged in');
    }
}