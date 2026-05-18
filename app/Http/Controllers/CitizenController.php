<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CitizenController extends Controller
{
    /** Citizen portal homepage — two option cards. */
    public function index()
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
            return redirect()->route('track.show', ['trackingNumber' => $trackingNumber]);
        }

        return view('citizen.track');
    }
}
