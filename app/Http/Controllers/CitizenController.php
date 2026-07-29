<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CitizenController extends Controller
{
    /**
     * Public tracking numbers look like SPD-20260728-K7M9Q2 — a date stamp plus
     * six unambiguous base32 characters.
     */
    private const TRACKING_NUMBER_PATTERN = '/^(SPD|INT)-\d{8}-[0-9A-Z]{6}$/';

    /** Citizen portal homepage — two option cards. */
    public function index(): Factory|View
    {
        return view('citizen.dashboard');
    }

    /**
     * Document tracking interface. A ?tracking= query param is resolved here and
     * only forwarded to the public timeline once it is known to be good.
     *
     * Handing an unresolved number to track.show would bounce a guest onward to
     * the public landing page (its "not found" path routes through track.index),
     * losing both the error message and the citizen's place in the portal.
     */
    public function track(Request $request): Factory|View|RedirectResponse
    {
        $trackingNumber = strtoupper(trim((string) $request->query('tracking', '')));

        if ($trackingNumber === '') {
            return view('citizen.track');
        }

        if (preg_match(self::TRACKING_NUMBER_PATTERN, $trackingNumber) !== 1) {
            return view('citizen.track', [
                'trackingError' => 'That is not a valid tracking number. It should look like SPD-20260728-K7M9Q2 — check the number printed on your receipt.',
            ]);
        }

        if (! $this->trackableDocumentExists($trackingNumber)) {
            return view('citizen.track', [
                'trackingError' => "No document found for {$trackingNumber}. Check the number and try again.",
            ]);
        }

        return to_route('track.show', ['trackingNumber' => $trackingNumber]);
    }

    /**
     * Guests must never learn that an internal department-to-department request
     * exists — those report as "not found" here rather than 404ing downstream.
     */
    private function trackableDocumentExists(string $trackingNumber): bool
    {
        $query = Document::where('tracking_number', $trackingNumber);

        if (! auth()->check()) {
            $query->where('origin', '!=', Document::ORIGIN_INTERNAL);
        }

        return $query->exists();
    }
}
