<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\QrCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DocumentWebController extends Controller
{
    public function __construct(private QrCodeService $qrCodeService)
    {
    }

    public function create()
    {
        $this->ensureCanCreate();

        $documentTypes = Document::query()
            ->whereNotNull('document_type')
            ->distinct()
            ->orderBy('document_type')
            ->pluck('document_type');

        $categoryOptions = [
            'Business Permit',
            'Barangay Clearance',
            'Building Permit',
            "Mayor's Permit",
            'Real Property Tax',
            'Birth Certificate Request',
            'Community Tax Certificate',
            'Other',
        ];

        return view('documents.create', compact('documentTypes', 'categoryOptions'));
    }

    public function store(Request $request)
    {
        $this->ensureCanCreate();

        $request->validate([
            'document_type' => 'required|string|max:255',
            'citizen_name' => 'nullable|string',
            'citizen_contact' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'purpose' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
            'attachment' => 'nullable|image|max:10240',
        ]);

        $trackingNumber = $this->qrCodeService->generateTrackingNumber();

        $data = [
            'tracking_number' => $trackingNumber,
            'document_type' => $request->document_type,
            'citizen_name' => $request->citizen_name,
            'status' => 'pending',
            'created_by' => auth()->id(),
            'remarks' => $request->remarks,
        ];

        if (Schema::hasColumn('documents', 'citizen_contact')) {
            $data['citizen_contact'] = $request->citizen_contact;
        }
        if (Schema::hasColumn('documents', 'description')) {
            $data['description'] = $request->description;
        }
        if (Schema::hasColumn('documents', 'purpose')) {
            $data['purpose'] = $request->purpose;
        }

        $document = Document::create($data);

        $trackingUrl = url("/track/{$trackingNumber}");
        $qrResult = $this->qrCodeService->generateAndStore($trackingNumber, $trackingUrl);

        if ($qrResult['success'] && Schema::hasColumn('documents', 'qr_code_path')) {
            $document->update(['qr_code_path' => $qrResult['relative_path']]);
        }

        if ($request->hasFile('attachment') && Schema::hasColumn('documents', 'attachment_path')) {
            $path = $request->file('attachment')->store('document-attachments', 'public');
            $document->update(['attachment_path' => $path]);
        }

        return redirect()->route('documents.created', $document);
    }

    public function created(Document $document)
    {
        $this->authorizeDocumentView($document);
        return view('documents.created', compact('document'));
    }

    public function printSticker(Document $document)
    {
        $this->authorizeDocumentView($document);
        $trackingUrl = url('/track/'.$document->tracking_number);

        return view('documents.qr-sticker', compact('document', 'trackingUrl'));
    }

    private function authorizeDocumentView(Document $document): void
    {
        // Authenticated staff can open confirmation / sticker / reprint for any document.
        if (! auth()->check()) {
            abort(403);
        }
    }

    private function ensureCanCreate(): void
    {
        // Any authenticated user may create submissions (route middleware enforces auth).
        if (! auth()->check()) {
            abort(403, 'You must be signed in to create document submissions.');
        }
    }
}