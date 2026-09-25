<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends the baseline HTTP security headers on every web response.
 *
 * Before this existed the app sent none, which left it clickjackable,
 * MIME-sniffable and without any CSP backstop behind the XSS classes patched
 * in the dependency update of 2026-09-25.
 *
 * Everything is driven by config/security.php so the policy can be tuned per
 * environment without a code change. Two rules are load-bearing and should not
 * be "simplified" away:
 *
 *  - `camera=(self)` in Permissions-Policy. Every QR scan flow (Look Up hub,
 *    custody widget, internal request approval) calls getUserMedia(). Dropping
 *    it breaks them silently — no error, just a camera that never starts.
 *  - HSTS is only emitted over HTTPS. Sent from a plain-HTTP dev machine it
 *    would pin localhost to HTTPS in the browser and be painful to undo.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Cheap, universally safe headers — fine on every response type.
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', (string) config('security.frame_options', 'DENY'));
        $response->headers->set('Referrer-Policy', (string) config('security.referrer_policy'));

        if ($policy = config('security.permissions_policy')) {
            $response->headers->set('Permissions-Policy', (string) $policy);
        }

        if ($request->secure() && config('security.hsts.enabled')) {
            $response->headers->set('Strict-Transport-Security', $this->hsts());
        }

        // The CSP is only meaningful for documents the browser parses. Putting
        // it on a streamed attachment or a JSON payload achieves nothing and
        // risks surprising a download, so those are skipped.
        if (config('security.csp.enabled') && $this->isHtml($response)) {
            $header = config('security.csp.report_only')
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';

            $response->headers->set($header, $this->csp($request));
        }

        return $response;
    }

    /** Build the CSP header value from config, plus the runtime WebSocket origin. */
    private function csp(Request $request): string
    {
        /** @var array<string, array<int, string>> $directives */
        $directives = config('security.csp.directives', []);

        $directives['connect-src'] = array_values(array_unique(array_merge(
            $directives['connect-src'] ?? ["'self'"],
            $this->connectSources(),
        )));

        $parts = [];
        foreach ($directives as $name => $values) {
            $values = array_filter($values);
            if ($values === []) {
                continue;
            }
            $parts[] = $name.' '.implode(' ', $values);
        }

        // Ask the browser to rewrite any stray http:// subresource to https://
        // rather than block it outright — only meaningful once we're on TLS.
        if ($request->secure()) {
            $parts[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $parts);
    }

    /**
     * Origins the page legitimately opens connections to.
     *
     * Reverb powers live tracking over a WebSocket, and its host/port differ
     * per environment (127.0.0.1:8080 locally, the public host behind an nginx
     * /app proxy in production). Deriving it from the same VITE_* values the
     * client is built with keeps the policy and the client in step — if they
     * drift, live tracking dies with a console error and no server-side clue.
     *
     * @return array<int, string>
     */
    private function connectSources(): array
    {
        $sources = [];

        // Via config(), never env() — see the note in config/security.php: a
        // cached config makes env() return null in production and the origin
        // would silently disappear from the policy.
        $host = config('security.reverb.host');
        $port = config('security.reverb.port');
        $scheme = config('security.reverb.scheme', 'https');

        if ($host) {
            $wsScheme = $scheme === 'https' ? 'wss' : 'ws';
            $authority = $port ? $host.':'.$port : $host;

            $sources[] = $wsScheme.'://'.$authority;
            // Echo falls back between secure and insecure transports during
            // reconnects, so allow both rather than debug a flapping socket.
            $sources[] = ($wsScheme === 'wss' ? 'ws' : 'wss').'://'.$authority;
        }

        // Vite's dev server holds an HMR socket open when `npm run dev` is used.
        if (app()->environment('local')) {
            $sources[] = 'ws://localhost:5173';
            $sources[] = 'http://localhost:5173';
            $sources[] = 'ws://127.0.0.1:5173';
            $sources[] = 'http://127.0.0.1:5173';
        }

        return $sources;
    }

    private function hsts(): string
    {
        $value = 'max-age='.(int) config('security.hsts.max_age');

        if (config('security.hsts.include_subdomains')) {
            $value .= '; includeSubDomains';
        }

        if (config('security.hsts.preload')) {
            $value .= '; preload';
        }

        return $value;
    }

    private function isHtml(Response $response): bool
    {
        $type = (string) $response->headers->get('Content-Type', '');

        // A Blade view that hasn't been sent yet may not have a Content-Type
        // set; those are HTML by default in Laravel.
        return $type === '' || str_contains($type, 'text/html');
    }
}
