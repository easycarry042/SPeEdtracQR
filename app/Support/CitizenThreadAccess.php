<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Who may WRITE in a request's citizen-visible thread.
 *
 * Reading the tracking page needs only the tracking number, as before. Posting
 * is different: a forwarded or shoulder-surfed link must not let a stranger
 * speak as the requester, because staff act on what that thread says. So the
 * citizen confirms one detail already on the request — the email or the contact
 * number — and that confirmation is remembered for the session, per request.
 *
 * Comparison is deliberately forgiving about formatting (spaces, dashes, +63 vs
 * 0 prefixes, letter case) and strict about the value, and it never reveals
 * which of the two details matched, nor whether one is on file at all.
 */
class CitizenThreadAccess
{
    private const SESSION_PREFIX = 'citizen_thread_verified:';

    public static function sessionKey(Document $document): string
    {
        return self::SESSION_PREFIX.$document->tracking_number;
    }

    public static function isVerified(Request $request, Document $document): bool
    {
        return (bool) $request->session()->get(self::sessionKey($document), false);
    }

    public static function markVerified(Request $request, Document $document): void
    {
        $request->session()->put(self::sessionKey($document), true);
    }

    /** Whether posting can be offered at all — with nothing on file, nothing can match. */
    public static function canBeVerified(Document $document): bool
    {
        return filled($document->citizen_email) || filled($document->citizen_contact);
    }

    /** Does the supplied detail match the email or contact number on this request? */
    public static function detailMatches(Document $document, string $detail): bool
    {
        $detail = trim($detail);

        if ($detail === '') {
            return false;
        }

        if (filled($document->citizen_email)
            && hash_equals(Str::lower(trim($document->citizen_email)), Str::lower($detail))) {
            return true;
        }

        if (filled($document->citizen_contact)) {
            $onFile = self::normalisePhone($document->citizen_contact);
            $given = self::normalisePhone($detail);

            if ($onFile !== '' && $given !== '' && hash_equals($onFile, $given)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Digits only, with a Philippine mobile reduced to its national form, so
     * "+63 917 123 4567", "0917-123-4567" and "09171234567" all match.
     */
    private static function normalisePhone(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        if (Str::startsWith($digits, '63') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }
}
