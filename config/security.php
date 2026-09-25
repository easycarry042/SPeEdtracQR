<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | Directives are declared here rather than hard-coded in the middleware so
    | the policy can be tightened without touching application code — and so a
    | deployment can loosen one directive in an emergency without a code change.
    |
    | HONEST LIMITATION: `script-src` carries 'unsafe-inline' and 'unsafe-eval',
    | which is what makes a CSP weak against XSS. Both are currently required:
    |
    |   - 'unsafe-inline': 22 Blade views contain inline <script> blocks, and 34
    |     use inline style="" attributes. Removing it means moving every one of
    |     them to a nonce or an external file.
    |   - 'unsafe-eval': Alpine.js evaluates expressions with `new Function`,
    |     and Livewire (via Pulse) does the same.
    |
    | The policy is still worth shipping: object-src, base-uri, form-action and
    | frame-ancestors close real attack classes on their own, and script-src is
    | still restricted to this origin plus one named CDN, so an injected
    | <script src="evil.com"> is blocked even though an inline one is not.
    |
    | To tighten later: adopt per-request nonces, drop 'unsafe-inline', and keep
    | 'unsafe-eval' only if Alpine is still in use.
    |
    */

    'csp' => [
        'enabled' => env('SECURITY_CSP_ENABLED', true),

        // Report-only publishes violations without enforcing them. Useful when
        // tightening the policy: turn this on, watch the console, then enforce.
        'report_only' => env('SECURITY_CSP_REPORT_ONLY', false),

        'directives' => [
            'default-src' => ["'self'"],

            // The CDN entry is the Sienna accessibility widget, loaded in
            // layouts/partials/accessibility-widget.blade.php.
            'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'", 'https://cdn.jsdelivr.net'],

            'style-src' => ["'self'", "'unsafe-inline'", 'https://cdn.jsdelivr.net'],

            // data: covers inline SVG/QR payloads and the caret icons encoded in
            // app.css; blob: covers the camera preview used by the QR scanners.
            'img-src' => ["'self'", 'data:', 'blob:'],

            'font-src' => ["'self'", 'data:', 'https://cdn.jsdelivr.net'],

            // Reverb's WebSocket origin is appended at runtime — see
            // SecurityHeaders::connectSources().
            'connect-src' => ["'self'"],

            'media-src' => ["'self'", 'blob:'],
            'worker-src' => ["'self'", 'blob:'],

            // Nothing in this app embeds plugins or frames other documents.
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions Policy
    |--------------------------------------------------------------------------
    |
    | `camera=(self)` is REQUIRED — the QR scanners on the Look Up hub, the
    | custody widget and the internal request action panel all call
    | getUserMedia(). Setting it to () silently breaks every scan flow.
    |
    */

    'permissions_policy' => env('SECURITY_PERMISSIONS_POLICY', implode(', ', [
        'camera=(self)',
        'microphone=()',
        'geolocation=()',
        'payment=()',
        'usb=()',
        'magnetometer=()',
        'gyroscope=()',
        'accelerometer=()',
        'interest-cohort=()',
    ])),

    /*
    |--------------------------------------------------------------------------
    | HTTP Strict Transport Security
    |--------------------------------------------------------------------------
    |
    | Only ever sent over HTTPS. Sending it on a plain-HTTP local machine would
    | pin `localhost` to HTTPS in the developer's browser and be a nuisance to
    | undo, so the middleware checks $request->secure() before emitting it.
    |
    | `preload` is deliberately OFF by default: submitting to the preload list
    | is effectively irreversible, and should be a conscious decision once the
    | production domain is settled.
    |
    */

    'hsts' => [
        'enabled' => env('SECURITY_HSTS_ENABLED', true),
        'max_age' => env('SECURITY_HSTS_MAX_AGE', 31536000), // 1 year
        'include_subdomains' => env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', true),
        'preload' => env('SECURITY_HSTS_PRELOAD', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reverb WebSocket origin (for connect-src)
    |--------------------------------------------------------------------------
    |
    | Read here rather than in the middleware on purpose: once
    | `php artisan config:cache` runs — which production does — `env()` returns
    | null everywhere outside the config directory. Calling it from the
    | middleware would silently drop the WebSocket origin from the CSP and kill
    | live tracking in production only, which is the worst place to find out.
    |
    | These mirror the VITE_* values the client bundle is built with, so the
    | policy and the client stay in step.
    |
    */

    'reverb' => [
        'host' => env('VITE_REVERB_HOST', env('REVERB_HOST')),
        'port' => env('VITE_REVERB_PORT', env('REVERB_PORT')),
        'scheme' => env('VITE_REVERB_SCHEME', 'https'),
    ],

    'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),

    'frame_options' => env('SECURITY_FRAME_OPTIONS', 'DENY'),

];
