<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Server-side authority on what counts as one of OUR QR codes.
 *
 * A scan is accepted only when the decoded payload is either a bare tracking
 * number we issue, or one of our own tracking/verification URLs on this host.
 * Everything else — a Wi-Fi code, a payment code, a link to another site, or
 * arbitrary text that happens to contain something tracking-number-shaped — is
 * rejected, so a code this office never printed can never drive an action.
 *
 * Mirrors window.SpeedQr.extractTracking in partials/qr-scan-helpers.blade.php;
 * the browser copy is a convenience, this one is the gate.
 */
class ScannedCode
{
    /** PREFIX-YYYYMMDD-XXXXXX: SPD = citizen request, INT = internal request. */
    private const TRACKING_PATTERN = '/^(?:SPD|INT)-\d{8}-[0-9A-Z]{6}$/i';

    /** Our own scannable paths and the tracking number each carries. */
    private const PATH_PATTERN = '#^/(?:track|verify)/((?:SPD|INT)-\d{8}-[0-9A-Z]{6})(?:/.*)?$#i';

    public const FOREIGN_CODE_MESSAGE = 'That code was not issued by SPeED TraQR. Scan the QR printed on the claim slip or folder.';

    /**
     * The tracking number a scanned payload refers to, or null when the payload
     * is not a code this system issued.
     */
    public static function trackingNumber(string $payload): ?string
    {
        $payload = trim($payload);

        if ($payload === '') {
            return null;
        }

        if (preg_match(self::TRACKING_PATTERN, $payload) === 1) {
            return strtoupper($payload);
        }

        // Otherwise it has to be one of our URLs — on this host, on a path we serve.
        $parts = parse_url($payload);

        if ($parts === false || ! isset($parts['path'])) {
            return null;
        }

        if (isset($parts['host']) && ! self::isOurHost($parts['host'])) {
            return null;
        }

        if (preg_match(self::PATH_PATTERN, self::normalisePath($parts['path']), $matches) !== 1) {
            return null;
        }

        return strtoupper($matches[1]);
    }

    public static function isOurs(string $payload): bool
    {
        return self::trackingNumber($payload) !== null;
    }

    private static function isOurHost(string $host): bool
    {
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        // Fall back to the current request's host when APP_URL is unset/misconfigured
        // (common in local setups reached over 127.0.0.1 or a LAN IP).
        $appHost = $appHost ?: request()->getHost();

        return strcasecmp($host, (string) $appHost) === 0
            || strcasecmp($host, request()->getHost()) === 0;
    }

    /** Strip any app sub-directory prefix so /speedtraqr/track/X still matches. */
    private static function normalisePath(string $path): string
    {
        $base = parse_url((string) config('app.url'), PHP_URL_PATH);

        if ($base && $base !== '/' && Str::startsWith($path, $base)) {
            $path = Str::after($path, rtrim($base, '/'));
        }

        return '/'.ltrim($path, '/');
    }
}
