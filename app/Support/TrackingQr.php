<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * The payload printed in a request's QR code (receipt, claim slip, folder sticker).
 *
 * It is deliberately NOT a URL. A phone's camera or any generic QR app decodes
 * it to meaningless text — there is nothing to open — so the code only works
 * through this application's own scanners. It carries the tracking number plus
 * a short HMAC signature, so a code somebody types into a QR generator is
 * rejected by the server: only codes this office printed are accepted.
 *
 * Format: "SPDQR1:" + base32(tracking number bytes + 8 signature bytes). Base32
 * keeps every character inside the QR alphanumeric set, so the code stays as
 * small and as easy to scan as the URL it replaces.
 *
 * Mirrors window.SpeedQr.extractTracking in partials/qr-scan-helpers.blade.php,
 * which decodes the same format but cannot check the signature — this class is
 * the gate.
 */
class TrackingQr
{
    /** Marks the payload as one of ours; the digit versions the format. */
    public const PREFIX = 'SPDQR1:';

    private const SIGNATURE_BYTES = 8;

    private const TRACKING_PATTERN = '/^(?:SPD|INT)-\d{8}-[0-9A-Z]{6}$/';

    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** The query parameter the internal tracker reads a signed payload from. */
    public const URL_PARAMETER = 'qr';

    /**
     * What a request's QR image encodes. Citizen requests: the bare signed
     * payload. Internal requests: the payload inside a link to the public
     * internal tracker, so a phone's own camera opens it — anyone may see who
     * is handling an internal request (the client's request) — and our
     * scanners still read and verify the payload out of the link.
     */
    public static function content(string $trackingNumber): string
    {
        $payload = self::payload($trackingNumber);

        return str_starts_with(strtoupper($trackingNumber), 'INT-')
            ? route('internal.track', [self::URL_PARAMETER => $payload])
            : $payload;
    }

    public static function payload(string $trackingNumber): string
    {
        $trackingNumber = strtoupper($trackingNumber);

        return self::PREFIX.self::base32Encode($trackingNumber.self::signature($trackingNumber));
    }

    /**
     * The tracking number inside a scanned payload, or null when it is not one
     * of our QR codes or its signature does not match.
     */
    public static function trackingNumber(string $payload): ?string
    {
        $payload = strtoupper(trim($payload));

        if (! str_starts_with($payload, self::PREFIX)) {
            return null;
        }

        $bytes = self::base32Decode(substr($payload, strlen(self::PREFIX)));

        if ($bytes === null || strlen($bytes) <= self::SIGNATURE_BYTES) {
            return null;
        }

        $trackingNumber = substr($bytes, 0, -self::SIGNATURE_BYTES);
        $signature = substr($bytes, -self::SIGNATURE_BYTES);

        if (preg_match(self::TRACKING_PATTERN, $trackingNumber) !== 1) {
            return null;
        }

        return hash_equals(self::signature($trackingNumber), $signature) ? $trackingNumber : null;
    }

    private static function signature(string $trackingNumber): string
    {
        $raw = hash_hmac('sha256', 'tracking-qr|'.$trackingNumber, self::key(), true);

        return substr($raw, 0, self::SIGNATURE_BYTES);
    }

    /**
     * QR_SIGNING_KEY when set, otherwise APP_KEY. A dedicated key matters in
     * production: every printed sticker is signed with it, so rotating APP_KEY
     * alone must not invalidate folders already sitting in the office.
     */
    private static function key(): string
    {
        $key = (string) (config('app.qr_signing_key') ?: config('app.key'));

        if ($key === '') {
            throw new RuntimeException('No QR signing key: set QR_SIGNING_KEY or APP_KEY in .env.');
        }

        return $key;
    }

    private static function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::BASE32_ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    private static function base32Decode(string $text): ?string
    {
        if ($text === '' || strspn($text, self::BASE32_ALPHABET) !== strlen($text)) {
            return null;
        }

        $bits = '';
        foreach (str_split($text) as $char) {
            $bits .= str_pad(decbin(strpos(self::BASE32_ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
        }

        // The last character's spare bits must be zero, as the encoder writes
        // them; otherwise several spellings would decode to the same code.
        $spare = strlen($bits) % 8;
        if ($spare >= 5 || ($spare > 0 && str_contains(substr($bits, -$spare), '1'))) {
            return null;
        }

        $bytes = '';
        foreach (str_split(substr($bits, 0, strlen($bits) - $spare), 8) as $chunk) {
            $bytes .= chr(bindec($chunk));
        }

        return $bytes;
    }
}
