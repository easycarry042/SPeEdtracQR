<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Document;
use Illuminate\Http\Request;

/**
 * Remembers which request the citizen just filed, so the receipt page
 * (/request/submitted) can be reopened while they save the QR.
 *
 * It used to be a one-view flash, so any detour — the printable slip, the
 * tracking page, which polls in the background — wiped it and Back landed on a
 * blank "Submit a request" form. It now lasts a short while instead, and is
 * forgotten the moment anyone starts over (landing page, citizen portal home or
 * a new request form). That is what the next person at a shared counter does,
 * so they still never find the previous citizen's receipt waiting for them.
 */
final class CitizenReceipt
{
    public const SESSION_KEY = 'submitted_receipt';

    public const LIFETIME_MINUTES = 15;

    public static function remember(Request $request, Document $document): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'tracking_number' => $document->tracking_number,
            'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES)->getTimestamp(),
        ]);
    }

    /** The tracking number of the receipt still on offer, or null once it has expired or been forgotten. */
    public static function trackingNumber(Request $request): ?string
    {
        $receipt = $request->session()->get(self::SESSION_KEY);

        if (! is_array($receipt) || ($receipt['expires_at'] ?? 0) < now()->getTimestamp()) {
            self::forget($request);

            return null;
        }

        return $receipt['tracking_number'] ?? null;
    }

    public static function forget(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
