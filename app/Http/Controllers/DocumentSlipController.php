<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * A print-ready claim slip for a document: municipality header, the tracking QR,
 * the tracking number, request type and date, plus counter instructions. The
 * page is styled for print so citizens/staff can "Save as PDF" straight from the
 * browser. Guarded by the high-entropy tracking number, same as the public
 * tracking page.
 */
class DocumentSlipController extends Controller
{
    public function show(Request $request, string $trackingNumber): Factory|View
    {
        $document = Document::where('tracking_number', $trackingNumber)->firstOrFail();

        // Back returns to whichever page linked here. The browser's "previous
        // page" was used before, and it could be anything — a stale page, or a
        // staff page on a shared PC — once the slip was opened from the tracking
        // page or a bookmark.
        $backUrl = match (true) {
            $request->query('from') === 'receipt' => route('public.request.submitted'),
            $request->query('from') === 'tracking', ! auth()->check() => $document->citizenTrackingUrl(),
            default => route('track.show', $document->tracking_number),
        };

        return view('documents.slip', ['document' => $document, 'backUrl' => $backUrl]);
    }
}
