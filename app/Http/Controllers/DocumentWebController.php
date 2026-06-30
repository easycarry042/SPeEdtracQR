<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Concerns\StoresDocumentAttachments;
use App\Models\Document;
use App\Services\QrCodeService;
use App\Support\DepartmentScope;
use App\Support\DocumentFormOptions;
use Illuminate\Http\Request;

class DocumentWebController extends Controller
{
    use StoresDocumentAttachments;

    public function __construct(private QrCodeService $qrCodeService) {}

    public function create()
    {
        $this->ensureCanCreate();

        // The submission form now lives in a modal rendered by the layout
        // (resources/views/documents/partials/create-modal.blade.php). Keep
        // this route so old links still work and permission checks still apply.
        return redirect()->route('dashboard')->with('openCreateModal', true);
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
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'image|max:10240',
        ]);

        $trackingNumber = $this->qrCodeService->generateTrackingNumber();

        // New documents start Pending and unassigned; an admin assigns the
        // responsible staff member (see Admin\AssignmentController), who then
        // advances it through the DocumentStatus stages manually.
        $document = Document::create([
            'tracking_number' => $trackingNumber,
            'document_type' => $request->document_type,
            'citizen_name' => $request->citizen_name,
            'citizen_contact' => $request->citizen_contact,
            'description' => $request->description,
            'purpose' => $request->purpose,
            'status' => DocumentStatus::Pending->value,
            'status_changed_at' => now(),
            'created_by' => auth()->id(),
            'remarks' => $request->remarks,
        ]);

        $trackingUrl = url("/track/{$trackingNumber}");
        $qrResult = $this->qrCodeService->generateAndStore($trackingNumber, $trackingUrl);

        if ($qrResult['success']) {
            $document->update(['qr_code_path' => $qrResult['relative_path']]);
        }

        $this->storeDocumentAttachments($document, $request);

        return redirect()->route('documents.created', $document);
    }

    public function created(Document $document)
    {
        $this->authorizeDocumentView($document);
        $document->load(['routeSteps.department', 'attachments']);

        return view('documents.created', compact('document'));
    }

    public function edit(Document $document)
    {
        $this->ensureCanCreate();
        abort_unless(DepartmentScope::userCanAccessDocument($document), 403);

        $categoryOptions = $this->categoryOptions();

        return view('documents.edit', compact('document', 'categoryOptions'));
    }

    public function update(Request $request, Document $document)
    {
        $this->ensureCanCreate();
        abort_unless(DepartmentScope::userCanAccessDocument($document), 403);

        // Routing and status are changed only through scans, not this form.
        $validated = $request->validate([
            'document_type' => 'required|string|max:255',
            'citizen_name' => 'nullable|string|max:255',
            'citizen_contact' => 'nullable|string|max:255',
            'purpose' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);

        $document->update($validated);

        return redirect()
            ->route('track.show', $document->tracking_number)
            ->with('status', 'Document details updated.');
    }

    public function printSticker(Document $document)
    {
        $this->authorizeDocumentView($document);
        $trackingUrl = url('/track/'.$document->tracking_number);

        return view('documents.qr-sticker', compact('document', 'trackingUrl'));
    }

    public function complete(string $trackingNumber)
    {
        abort_unless(auth()->user()?->can('scan documents') && ! auth()->user()?->can('manage system'), 403);

        $document = Document::where('tracking_number', $trackingNumber)->firstOrFail();

        if ($document->status === DocumentStatus::Completed->value) {
            return response()->json(['message' => 'Document is already completed.'], 422);
        }

        $document->applyStatus(DocumentStatus::Completed);
        $document->save();

        return response()->json(['message' => "Document {$trackingNumber} marked as completed."]);
    }

    private function authorizeDocumentView(Document $document): void
    {
        if (! auth()->check()) {
            abort(403);
        }
    }

    private function categoryOptions(): array
    {
        return DocumentFormOptions::categoryOptions();
    }

    private function ensureCanCreate(): void
    {
        $user = auth()->user();

        if ($user?->can('manage system')) {
            abort(403, 'System administrators manage the organization but do not create document submissions.');
        }

        // receiving_staff is intake/scan-only and lacks this permission.
        if (! $user?->can('create documents')) {
            abort(403, 'You do not have permission to create document submissions.');
        }
    }

    private function storeDocumentAttachments(Document $document, Request $request): void
    {
        $files = collect($request->file('attachments', []));
        if ($request->hasFile('attachment')) {
            $files->prepend($request->file('attachment'));
        }

        $this->storeAttachmentsForDocument($document, $files->filter()->all());
    }
}
