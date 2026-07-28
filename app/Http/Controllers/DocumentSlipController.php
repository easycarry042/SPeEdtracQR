<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

/**
 * A print-ready claim slip for a document: municipality header, the tracking QR,
 * the tracking number, request type and date, plus counter instructions. The
 * page is styled for print so citizens/staff can "Save as PDF" straight from the
 * browser. Guarded by the high-entropy tracking number, same as the public
 * tracking page.
 */
class DocumentSlipController extends Controller
{
    public function show(string $trackingNumber): Factory|View
    {
        $document = Document::where('tracking_number', $trackingNumber)->firstOrFail();

        return view('documents.slip', ['document' => $document]);
    }
}
