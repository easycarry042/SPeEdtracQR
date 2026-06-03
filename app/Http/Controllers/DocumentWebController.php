<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\DocumentScan;
use App\Models\RoutingRule;
use App\Services\QrCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DocumentWebController extends Controller
{
    public function __construct(private QrCodeService $qrCodeService) {}

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

        $this->storeDocumentAttachments($document, $request);

        $document->syncRouteSteps($routeDepartments);

        // Auto-check-in at the first department so it appears in the inbox immediately
        $firstDeptId = $routeDepartments[0];
        $document->update([
            'current_department_id' => $firstDeptId,
            'status' => 'in_transit',
        ]);

        $scanData = [
            'document_id' => $document->id,
            'scanned_by' => auth()->id(),
            'department_id' => $firstDeptId,
            'action' => 'in',
            'scanned_at' => now(),
            'location_ip' => request()->ip(),
        ];
        if (Schema::hasColumn('document_scans', 'remarks')) {
            $scanData['remarks'] = 'Document received';
        }
        if (Schema::hasColumn('document_scans', 'offline_uuid')) {
            $scanData['offline_uuid'] = null;
        }
        if (Schema::hasColumn('document_scans', 'sync_status')) {
            $scanData['sync_status'] = 'synced';
        } elseif (Schema::hasColumn('document_scans', 'synced')) {
            $scanData['synced'] = true;
        }
        DocumentScan::create($scanData);

        return redirect()->route('documents.created', $document);
    }

    public function created(Document $document)
    {
        $this->authorizeDocumentView($document);
        $document->load(['routeSteps.department', 'attachments']);

        return view('documents.created', compact('document'));
    }

    public function printSticker(Document $document)
    {
        $this->authorizeDocumentView($document);
        $trackingUrl = url('/track/'.$document->tracking_number);

        return view('documents.qr-sticker', compact('document', 'trackingUrl'));
    }

    public function complete(string $trackingNumber)
    {
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

    private function ensureCanCreate(): void
    {
        $user = auth()->user();

        if ($user?->hasRole('super_admin')) {
            abort(403, 'Super administrators manage the system but do not create document submissions.');
        }

        if (! $user?->hasAnyRole(['staff', 'receiving_staff', 'department_admin'])) {
            abort(403, 'You do not have permission to create document submissions.');
        }
    }

    private function storeDocumentAttachments(Document $document, Request $request): void
    {
        $files = collect($request->file('attachments', []));
        if ($request->hasFile('attachment')) {
            $files->prepend($request->file('attachment'));
        }

        $sort = 0;
        foreach ($files->filter() as $file) {
            $path = $file->store('document-attachments', 'local');

            if (Schema::hasTable('document_attachments')) {
                DocumentAttachment::create([
                    'document_id' => $document->id,
                    'file_path' => $path,
                    'uploaded_by' => auth()->id(),
                    'department_id' => null,
                    'sort_order' => $sort++,
                ]);
            }

            if ($sort === 1 && Schema::hasColumn('documents', 'attachment_path')) {
                $document->update(['attachment_path' => $path]);
            }
        }
    }
}
