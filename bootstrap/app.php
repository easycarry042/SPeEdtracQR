<?php

use App\Http\Middleware\EnsureHasPermission;
use App\Http\Middleware\EnsureHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register role-guard alias so routes can use middleware('role:admin') etc.
        $middleware->alias([
            'role' => EnsureHasRole::class,
            'permission' => EnsureHasPermission::class,
            'active.user' => EnsureUserIsActive::class,
        ]);

        $middleware->appendToGroup('web', EnsureUserIsActive::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
