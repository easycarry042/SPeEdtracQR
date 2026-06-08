<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StoresDocumentAttachments;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentScan;
use App\Models\RoutingRule;
use App\Services\QrCodeService;
use App\Support\DepartmentScope;
use Illuminate\Http\Request;

class DocumentWebController extends Controller
{
    use StoresDocumentAttachments;

    public function __construct(private QrCodeService $qrCodeService) {}

    public function create()
    {
        $this->ensureCanCreate();

        $documentTypes = Document::query()
            ->whereNotNull('document_type')
            ->distinct()
            ->orderBy('document_type')
            ->pluck('document_type');

        $categoryOptions = $this->categoryOptions();

        $departments = Department::orderBy('name')->get();

        $defaultRoutesByType = RoutingRule::with(['fromDepartment', 'toDepartment'])
            ->orderBy('document_type')
            ->orderBy('step_order')
            ->get()
            ->groupBy('document_type')
            ->map(function ($rules) {
                $chain = collect([$rules->first()->fromDepartment?->id]);
                foreach ($rules as $rule) {
                    if ($rule->toDepartment) {
                        $chain->push($rule->toDepartment->id);
                    }
                }

                return $chain->filter()->unique()->values()->all();
            });

        return view('documents.create', compact(
            'documentTypes',
            'categoryOptions',
            'departments',
            'defaultRoutesByType'
        ));
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
            'route_departments' => 'required|array|min:1',
            'route_departments.*' => 'required|integer|exists:departments,id',
        ]);

        $routeDepartments = collect($request->input('route_departments', []))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (count($routeDepartments) < 1) {
            return back()
                ->withInput()
                ->withErrors(['route_departments' => 'Add at least one department to the routing path.']);
        }

        $trackingNumber = $this->qrCodeService->generateTrackingNumber();

        $document = Document::create([
            'tracking_number' => $trackingNumber,
            'document_type' => $request->document_type,
            'citizen_name' => $request->citizen_name,
            'citizen_contact' => $request->citizen_contact,
            'description' => $request->description,
            'purpose' => $request->purpose,
            'status' => 'pending',
            'created_by' => auth()->id(),
            'remarks' => $request->remarks,
        ]);

        $trackingUrl = url("/track/{$trackingNumber}");
        $qrResult = $this->qrCodeService->generateAndStore($trackingNumber, $trackingUrl);

        if ($qrResult['success']) {
            $document->update(['qr_code_path' => $qrResult['relative_path']]);
        }

        $this->storeDocumentAttachments($document, $request);

        $document->syncRouteSteps($routeDepartments);

        // Auto-check-in at the first department so it appears in the inbox immediately
        $firstDeptId = $routeDepartments[0];
        $document->update([
            'current_department_id' => $firstDeptId,
            'status' => 'in_transit',
        ]);

        DocumentScan::create([
            'document_id' => $document->id,
            'scanned_by' => auth()->id(),
            'department_id' => $firstDeptId,
            'action' => 'in',
            'scanned_at' => now(),
            'location_ip' => request()->ip(),
            'remarks' => 'Document received',
            'synced' => true,
        ]);

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

        if ($document->status === 'completed') {
            return response()->json(['message' => 'Document is already completed.'], 422);
        }

        $document->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

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
        return [
            'Business Permit',
            'Barangay Clearance',
            'Building Permit',
            "Mayor's Permit",
            'Real Property Tax',
            'Birth Certificate Request',
            'Community Tax Certificate',
            'Other',
        ];
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
