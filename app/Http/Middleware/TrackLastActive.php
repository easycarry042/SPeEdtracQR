<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamps users.last_active_at so "Active now" means the person is here.
 *
 * Two rules keep the stamp honest and cheap:
 *
 *  - Only real presence counts. A page the person opened, or anything they
 *    changed. Background JSON polls (the Speedy unread badge, the tracker's
 *    status poll) are excluded: they keep ticking in a forgotten tab, and
 *    counting them would show someone as "Active now" indefinitely after they
 *    went home.
 *  - At most one write a minute, and a quiet one. The marker is read to the
 *    nearest few minutes, so a write per request buys nothing — and a logged
 *    write would drop a "User updated" row into the audit log every minute
 *    and bury the entries that matter.
 */
class TrackLastActive
{
    /** How stale the stamp may get before it is worth another write. */
    private const REFRESH_AFTER_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $this->isPresence($request) && $this->isStale($user)) {
            $user->forceFill(['last_active_at' => now()])->saveQuietly();
        }

        return $next($request);
    }

    /** A request a person made, as opposed to one their open tab made. */
    private function isPresence(Request $request): bool
    {
        if (! $request->isMethodSafe()) {
            return true;   // POST/PATCH/DELETE is always someone doing something
        }

        // A navigation asks for a document; a poll asks for JSON.
        return $request->acceptsHtml() && ! $request->ajax();
    }

    private function isStale(mixed $user): bool
    {
        return $user->last_active_at === null
            || $user->last_active_at->lt(now()->subSeconds(self::REFRESH_AFTER_SECONDS));
    }
}
