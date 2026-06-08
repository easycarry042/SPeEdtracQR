<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasPermission
{
    /**
     * Verify the authenticated user has at least one of the required permissions.
     * On failure, redirects to the user's own dashboard rather than throwing a 403.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if (! $request->user()->hasAnyPermission($permissions)) {
            return $this->redirectToOwnDashboard($request->user());
        }

        return $next($request);
    }

    private function redirectToOwnDashboard($user): Response
    {
        $route = $user->can('manage system') ? 'admin.dashboard' : 'dashboard';

        return redirect()->route($route)
            ->with('error', 'You do not have permission to access that area.');
    }
}
