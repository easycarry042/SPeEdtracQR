<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Concerns\StoresDocumentAttachments;
use App\Mail\TicketSubmitted;
use App\Models\Document;
use App\Services\QrCodeService;
use App\Support\DocumentFormOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Phase 2 — citizen self-service ticket creation (no account).
 *
 * A submitted request becomes a normal documents row with source='online'. All
 * input is treated as untrusted: honeypot + route throttle, validated and
 * size/type-restricted uploads stored on the PRIVATE disk, RA 10173 consent.
 */
class PublicTicketController extends Controller
{
    use StoresDocumentAttachments;

    public function __construct(private QrCodeService $qrCodeService) {}

    public function create()
    {
        return view('public.request', [
            'categories' => DocumentFormOptions::categoryOptions(),
        ]);
    }

    public function store(Request $request)
    {
        // Honeypot: bots fill hidden fields. Silently bounce without creating.
        if (filled($request->input('website'))) {
            return redirect()->route('public.request.create')
                ->with('status', 'Your request has been received.');
        }

        $validated = $request->validate([
            'document_type' => 'required|string|max:255',
            'purpose' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'citizen_name' => 'required|string|max:255',
            'citizen_email' => 'required|email|max:255',
            'citizen_contact' => 'nullable|string|max:255',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'image|max:10240', // 10 MB each, images only
            'consent' => 'accepted',
        ], [
            'consent.accepted' => 'You must agree to the data privacy notice to submit a request.',
        ]);

        $trackingNumber = $this->qrCodeService->generateTrackingNumber();

        $document = Document::create([
            'tracking_number' => $trackingNumber,
            'document_type' => $validated['document_type'],
            'citizen_name' => $validated['citizen_name'],
            'citizen_email' => $validated['citizen_email'],
            'citizen_contact' => $validated['citizen_contact'] ?? null,
            'purpose' => $validated['purpose'] ?? null,
            'description' => $validated['description'] ?? null,
            'status' => DocumentStatus::Pending->value,
            'status_changed_at' => now(),
            'source' => 'online',
            'created_by' => null, // submitted by a citizen, not a staff user
        ]);

        $trackingUrl = url('/track/'.$trackingNumber);
        $qrResult = $this->qrCodeService->generateAndStore($trackingNumber, $trackingUrl);
        if ($qrResult['success']) {
            $document->update(['qr_code_path' => $qrResult['relative_path']]);
        }

        // Uploads land on the private disk; uploaded_by is null (no staff user).
        $this->storeAttachmentsForDocument(
            $document,
            collect($request->file('attachments', []))->filter()->all(),
            null,
            null,
        );

        activity()
            ->performedOn($document)
            ->log('Citizen submitted online request');

        if ($document->citizen_email) {
            Mail::to($document->citizen_email)->send(new TicketSubmitted($document));
            activity()->performedOn($document)->log('Emailed TicketSubmitted to citizen');
        }

        return redirect()->route('track.show', $trackingNumber)
            ->with('ticket_submitted', $trackingNumber);
    }
}
