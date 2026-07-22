<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Support\DocumentSeal;
use Illuminate\Http\Request;

/**
 * Public authenticity check. The QR on an issued document encodes a signed
 * URL; anyone scanning it sees whether the record is genuine — no account,
 * no personal data beyond what the paper itself already shows.
 */
class VerificationController extends Controller
{
    public function show(Request $request, string $trackingNumber)
    {
        $document = Document::where('tracking_number', $trackingNumber)->first();

        // Three verdicts: unknown record, bad signature (forged/altered), genuine.
        if (! $document) {
            $verdict = 'unknown';
        } elseif (! DocumentSeal::verify($document, $request->query('sig'))) {
            $verdict = 'invalid';
        } else {
            $verdict = 'genuine';
        }

        return view('verify.show', [
            'verdict' => $verdict,
            'trackingNumber' => $trackingNumber,
            'document' => $verdict === 'genuine' ? $document : null,
            'isIssued' => $verdict === 'genuine' && $document->statusEnum() === DocumentStatus::Completed,
        ]);
    }
}
