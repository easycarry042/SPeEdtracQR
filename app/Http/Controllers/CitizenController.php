<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CitizenController extends Controller
{
    /** Citizen portal homepage — two option cards. */
    public function index(): Factory|View
    {
        return view('citizen.dashboard');
    }

    /**
     * Document tracking interface.
     * If a ?tracking= query param is present, redirect straight to the
     * existing public track page so citizens reuse the same tracking timeline.
     */
    public function track(Request $request)
    {
        $trackingNumber = trim((string) $request->query('tracking', ''));

        if ($trackingNumber !== '') {
            return to_route('track.show', ['trackingNumber' => $trackingNumber]);
        }

        return view('citizen.track');
    }
}
