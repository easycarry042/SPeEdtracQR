<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     * Redirects to the appropriate dashboard based on the user's Spatie role.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return $this->redirectByRole($request->user());
    }

    private function redirectByRole($user): RedirectResponse
    {
        if ($user->can('manage system')) {
            return redirect()->intended(route('admin.dashboard', absolute: false));
        }

        if ($user->hasRole('Supervisor')) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        return redirect()->intended(route('staff.dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        // Tell the browser to purge everything it cached for this site — HTTP
        // cache AND the back/forward cache — plus cookies and storage. This is
        // what makes the Back button unable to resurrect a signed-in page after
        // logout, even on browsers that keep no-store pages in the bfcache.
        // (Clear-Site-Data is honoured on secure contexts, which includes
        // localhost and any HTTPS deployment.)
        return to_route('login')
            ->header('Clear-Site-Data', '"cache", "cookies", "storage"');
    }
}
