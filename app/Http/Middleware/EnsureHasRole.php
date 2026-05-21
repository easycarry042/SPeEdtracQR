<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasRole
{
    /**
     * Verify the authenticated user has one of the required roles.
     * On failure, redirects to the user's own dashboard rather than throwing a 403.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if (! $request->user()->hasAnyRole($roles)) {
            return $this->redirectToOwnDashboard($request->user());
        }

        return $next($request);
    }

    private function redirectToOwnDashboard($user): Response
    {
        $route = $user->hasRole('super_admin') ? 'admin.dashboard' : 'dashboard';

        return redirect()->route($route)
            ->with('error', 'You do not have permission to access that area.');
    }
}
