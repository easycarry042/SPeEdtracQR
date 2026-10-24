<?php

use App\Http\Middleware\EnsureHasPermission;
use App\Http\Middleware\EnsureHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackLastActive;
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

        // Behind nginx (production) or cloudflared (a tunnel), the proxy speaks
        // HTTPS to the world and plain HTTP to us. Without trusting its
        // forwarded headers Laravel believes every request is insecure and
        // generates http:// URLs on an https:// page — which browsers block as
        // mixed content, so the site loads completely unstyled.
        //
        // Loopback only, and hardcoded on purpose:
        //  - It is exactly the proxy we expect in both deployment shapes, and a
        //    remote client cannot spoof it (unlike trusting '*').
        //  - It is NOT read from config or env: this closure runs before the
        //    config service is bound, and env() would return null under
        //    `config:cache` in production — failing silently, in production only.
        //
        // If the proxy ever runs on a different host, name that host here.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);

        // A signed-in user who reaches a guest-only page (/login, typically via
        // the Back button) goes through the role-aware /home, which lands them
        // on THEIR workspace. Laravel's default is the route named 'dashboard'
        // — the supervisor dashboard — which bounces plain staff elsewhere.
        $middleware->redirectUsersTo('/home');

        $middleware->appendToGroup('web', EnsureUserIsActive::class);
        $middleware->appendToGroup('web', PreventBackHistory::class);
        $middleware->appendToGroup('web', SecurityHeaders::class);
        // After EnsureUserIsActive, so a deactivated account is logged out
        // rather than stamped as present.
        $middleware->appendToGroup('web', TrackLastActive::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
