<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Support\InternalTracking;
use App\Support\TrackingQr;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The public internal-request tracker: scan an INT- request's QR, or type its
 * number, and see which office has it and who there is handling it — no
 * account needed (the client's request). Reached from the staff sign-in page.
 *
 * Citizen (SPD-) numbers are sent to the citizen tracker instead, which shows
 * offices but never staff names.
 */
class InternalTrackerController extends Controller
{
    private const string INTERNAL_NUMBER = '/^INT-\d{8}-[0-9A-Z]{6}$/';

    public function show(Request $request): Factory|View
    {
        $number = strtoupper(trim((string) $request->query('number')));
        $result = null;
        $error = null;
        $citizenNumber = null;

        // Opened by a phone camera from the request's own QR: the link carries
        // the signed payload (TrackingQr::content()), and the signature decides.
        $signed = trim((string) $request->query(TrackingQr::URL_PARAMETER));
        if ($signed !== '') {
            $number = TrackingQr::trackingNumber($signed) ?? '';
            if ($number === '') {
                $error = 'That QR was not issued by SPeED TraQR.';
            }
        }

        if ($number !== '') {
            if (str_starts_with($number, 'SPD-')) {
                $citizenNumber = $number;
            } elseif (! preg_match(self::INTERNAL_NUMBER, $number)) {
                $error = 'That is not an internal request number. They look like INT-20261019-AB12CD.';
            } else {
                $document = Document::query()->internal()->where('tracking_number', $number)->first();

                $document
                    ? $result = InternalTracking::summary($document)
                    : $error = "No internal request {$number} was found. Check the number and try again.";
            }
        }

        return view('requests.public-track', [
            'number' => $number,
            'result' => $result,
            'error' => $error,
            'citizenNumber' => $citizenNumber,
        ]);
    }
}
