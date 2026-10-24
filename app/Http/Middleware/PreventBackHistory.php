<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stop the browser's back/forward cache (bfcache) from restoring ANY HTML page
 * out of history without contacting the server. This closes a whole class of
 * routing/auth bugs:
 *
 *  - Pressing Back after logout must never flash a protected screen.
 *  - Pressing Back after LOGIN must never strand the user on the public
 *    landing/login page (from which they could re-enter without re-auth) — the
 *    re-fetch runs the guest→dashboard redirect instead.
 *
 * `no-store` is the only Cache-Control directive that reliably prevents bfcache
 * across browsers (Chrome/Firefox/Safari), so we send it on every response. It
 * only affects HTML routed through the web middleware; static assets and QR
 * images are served outside this stack and keep normal caching. A `pageshow`
 * reload guard in the Blade layouts backs this up for any edge-case browser.
 */
class PreventBackHistory
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');

        return $response;
    }
}
