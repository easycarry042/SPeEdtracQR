<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Concerns\StoresDocumentAttachments;
use App\Mail\TicketSubmitted;
use App\Models\Document;
use App\Models\RequestType;
use App\Notifications\DocumentEvent;
use App\Services\QrCodeService;
use App\Support\DocumentFormOptions;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

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

    public function create(): Factory|View
    {
        return view('public.request', [
            'categories' => DocumentFormOptions::categoryOptions(),
            'requestTypes' => RequestType::query()
                ->where('is_active', true)
                ->where('kind', RequestType::KIND_DOCUMENT)
                ->with('requirements')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        // Honeypot: bots fill hidden fields. Silently bounce without creating.
        if (filled($request->input('website'))) {
            return to_route('public.request.create')
                ->with('status', 'Your request has been received.');
        }

        $validated = $request->validate([
            'document_type' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'citizen_name' => ['required', 'string', 'max:255'],
            'citizen_email' => ['required', 'email', 'max:255'],
            'citizen_contact' => ['nullable', 'string', 'max:255'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx', 'max:10240'], // 10 MB each: images, PDF or Word
            // Optional per-requirement uploads, keyed by request_type_requirement id.
            'requirements' => ['nullable', 'array'],
            'requirements.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx', 'max:10240'],
            'consent' => ['accepted'],
        ], [
            'consent.accepted' => 'You must agree to the data privacy notice to submit a request.',
            'attachments.*.mimes' => 'Each file must be an image (JPG/PNG), a PDF, or a Word document (DOCX).',
            'requirements.*.mimes' => 'Each requirement file must be an image (JPG/PNG), a PDF, or a Word document (DOCX).',
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
        );

        // Snapshot the selected type's requirement checklist onto this request,
        // storing any optional citizen upload per requirement (private disk).
        $type = RequestType::where('name', $validated['document_type'])->with('requirements')->first();
        if ($type) {
            $uploads = $request->file('requirements', []);
            foreach ($type->requirements as $requirement) {
                $file = $uploads[$requirement->id] ?? null;
                $path = ($file && $file->isValid())
                    ? $file->store('document-requirements', 'local')
                    : null;

                $document->requirements()->create([
                    'request_type_requirement_id' => $requirement->id,
                    'label' => $requirement->label,
                    'is_mandatory' => $requirement->is_mandatory,
                    'uploaded_file_path' => $path,
                ]);
            }
        }

        activity()
            ->performedOn($document)
            ->log('Citizen submitted online request');

        // Header-bell ping: supervisors have a new request to triage.
        Notification::send(
            DocumentEvent::supervisors(),
            DocumentEvent::newTicket($document),
        );

        if ($document->citizen_email) {
            Mail::to($document->citizen_email)->send(new TicketSubmitted($document));
            activity()->performedOn($document)->log('Emailed TicketSubmitted to citizen');
        }

        // Show the QR + tracking number first (do NOT jump straight to the
        // tracker) so the citizen can save the QR before navigating away.
        return view('public.request-submitted', [
            'document' => $document->fresh(),
        ]);
    }
}
