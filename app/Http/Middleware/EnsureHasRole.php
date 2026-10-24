<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasRole
{
    /**
     * Verify the authenticated user has one of the required roles.
     * Unauthenticated visitors are sent to the login page; authenticated users
     * without the role get a 403 Access Denied page.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        if (! $request->user()->hasAnyRole($roles)) {
            abort(403, 'You do not have permission to access that area.');
        }

        return $next($request);
    }
}
