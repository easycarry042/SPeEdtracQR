<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * The signer's own staff badge: a printable card whose QR re-confirms identity
 * when endorsing a request. Always scoped to the authenticated user — nobody can
 * fetch anyone else's badge.
 */
class StaffBadgeController extends Controller
{
    public function show(): Factory|View
    {
        $user = auth()->user();

        return view('profile.badge', [
            'badgeUser' => $user,
            // SVG so printing stays crisp and no GD extension is needed.
            'badgeSvg' => base64_encode(
                (string) QrCode::format('svg')->size(220)->margin(1)->generate($user->badgePayload())
            ),
        ]);
    }

    /** Reissue the badge (lost card): the previous printed badge stops working. */
    public function regenerate(): RedirectResponse
    {
        auth()->user()->regenerateBadgeCode();

        activity()->causedBy(auth()->user())->log('Reissued their staff badge code');

        return to_route('profile.badge')
            ->with('status', 'New badge issued — print it and destroy the old card.');
    }
}
