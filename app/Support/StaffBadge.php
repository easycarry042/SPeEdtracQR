<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * The staff badge QR: the office's answer to "prove it's really you" when
 * signing an endorsement hop.
 *
 * The payload is deliberately NOT a URL — a badge should be useless to anyone
 * who photographs it and opens it in a browser. It is an opaque prefixed secret
 * that only this application recognises.
 */
class StaffBadge
{
    /** Marks the payload as one of ours without revealing whose badge it is. */
    public const PREFIX = 'SPDSTAFF:';

    public static function payload(string $code): string
    {
        return self::PREFIX.$code;
    }

    /** The badge code inside a scanned payload, or null when it is not a badge. */
    public static function code(string $payload): ?string
    {
        $payload = trim($payload);

        if (! str_starts_with($payload, self::PREFIX)) {
            return null;
        }

        $code = substr($payload, strlen(self::PREFIX));

        return preg_match('/^[A-Za-z0-9]{16,40}$/', $code) === 1 ? $code : null;
    }

    /**
     * Whether a scanned payload is the badge of THIS user. Compared in constant
     * time, and never matches when the user has no badge code yet.
     */
    public static function belongsTo(string $payload, User $user): bool
    {
        $code = self::code($payload);

        if ($code === null || ! $user->identity_code) {
            return false;
        }

        return hash_equals($user->identity_code, $code);
    }
}
