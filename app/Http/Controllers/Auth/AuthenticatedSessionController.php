<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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

    /**
     * Read-only pages that are never a useful place to land after signing in.
     * A signed-out visit to one of these stores it as the "intended" URL, which
     * would otherwise hijack the role landing page on the next login — e.g. a
     * restored browser tab on /staff/4 dropping the user on a profile instead
     * of their Requests workspace.
     *
     * @var list<string>
     */
    private const NON_LANDING_PATHS = ['staff', 'staff/*'];

    private function redirectByRole($user): RedirectResponse
    {
        $this->forgetNonLandingIntendedUrl();

        if ($user->can('manage system')) {
            return redirect()->intended(route('admin.dashboard', absolute: false));
        }

        if ($user->hasRole('Supervisor')) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        return redirect()->intended(route('staff.dashboard', absolute: false));
    }

    /**
     * Drop the stored intended URL when it points at a page that shouldn't be a
     * landing target. Genuine deep links (a document from a notification, say)
     * are left alone so they still survive the login round-trip.
     */
    private function forgetNonLandingIntendedUrl(): void
    {
        $intended = session('url.intended');

        if (! is_string($intended)) {
            return;
        }

        $path = trim(parse_url($intended, PHP_URL_PATH) ?: '', '/');

        if (Str::is(self::NON_LANDING_PATHS, $path)) {
            session()->forget('url.intended');
        }
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
