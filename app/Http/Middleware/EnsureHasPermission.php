<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasPermission
{
    /**
     * Verify the authenticated user has at least one of the required permissions.
     * Unauthenticated visitors are sent to the login page; authenticated users
     * who lack the permission get a 403 Access Denied page (nav links are already
     * role-gated, so this only fires on direct URL access / history navigation).
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        if (! $request->user()->hasAnyPermission($permissions)) {
            abort(403, 'You do not have permission to access that area.');
        }

        return $next($request);
    }
}
