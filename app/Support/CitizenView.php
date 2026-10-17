<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Whether a page should render for the citizen rather than for whoever is
 * signed in.
 *
 * Public pages are often opened on a shared counter PC where a staff member is
 * still signed in. Pages that serve both audiences (the landing page, the
 * tracking page) used to pick the audience purely from the login, so a citizen
 * following their receipt or email was dropped onto the staff desk or the admin
 * dashboard. Every citizen-facing link now carries ?view=citizen, which wins
 * over the login.
 */
final class CitizenView
{
    /** Query parameters that mark a link as citizen-facing. */
    public const QUERY = ['view' => 'citizen'];

    /** Guests always get the citizen page; signed-in users only when the link asks for it. */
    public static function requested(): bool
    {
        return ! auth()->check() || request()->query('view') === self::QUERY['view'];
    }

    /** The public landing page, reachable even while a staff member is signed in. */
    public static function homeUrl(): string
    {
        return route('welcome', self::QUERY);
    }
}
