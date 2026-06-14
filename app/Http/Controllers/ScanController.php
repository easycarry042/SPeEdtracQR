<?php

namespace App\Http\Controllers;

use App\Events\DocumentMoved;
use App\Http\Controllers\Concerns\StoresDocumentAttachments;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentScan;
use App\Support\DepartmentScope;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ScanController extends Controller
{
    use StoresDocumentAttachments;

    public function index()
    {
        if (auth()->user()?->can('manage system')) {
            return redirect()->route('admin.dashboard');
        }

        $user = auth()->user();
        $isOrgWide = DepartmentScope::isOrgWide($user);
        $departments = $isOrgWide
            ? Department::orderBy('name')->get()
            : Department::where('id', $user?->department_id)->get();
        $allDepartments = Department::orderBy('name')->get();
        $sessionScans = collect(session('scanner.recent', []))->take(10);
        $userDepartmentId = $user?->department_id;
        $dept = $user?->department;

        return view('scan.index', compact('departments', 'allDepartments', 'sessionScans', 'userDepartmentId', 'isOrgWide', 'dept'));
    }

    public function store(Request $request)
    {
        $this->ensureCanScan();

        $validated = $request->validate([
            'tracking_number' => 'required|string',
            'department_id' => 'required|exists:departments,id',
            'action' => 'required|in:in,out',
            'remarks' => 'nullable|string',
            'scanned_at' => 'nullable|date',
            'offline_uuid' => 'nullable|string',
            'next_department_id' => 'nullable|exists:departments,id',
            'attachment' => 'nullable|image|max:10240',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'image|max:10240',
        ]);

        $this->ensureDepartmentForScan((int) $validated['department_id']);

        $document = Document::where('tracking_number', $validated['tracking_number'])->first();
        if (! $document) {
            return response()->json(['message' => 'Tracking number not found.'], 404);
        }

        if ($document->status === 'completed') {
            return response()->json(['message' => 'Document already completed.'], 422);
        }

        if ($validated['action'] === 'out'
            && (int) $document->current_department_id !== (int) $validated['department_id']) {
            return response()->json([
                'message' => 'This document is not currently at your department.',
            ], 422);
        }

        $scan = $this->recordScan($document, $validated);

        $scanFiles = collect($request->file('attachments', []));
        if ($request->hasFile('attachment')) {
            $scanFiles->prepend($request->file('attachment'));
        }
        $stored = $this->storeAttachmentsForDocument(
            $document,
            $scanFiles->filter()->all(),
            (int) $validated['department_id'],
        );
        if ($stored !== []) {
            $scan->update(['attachment_path' => $stored[0]->file_path]);
        }
        $nextDepartment = null;

        if ($validated['action'] === 'in') {
            $document->current_department_id = $validated['department_id'];
            $document->status = 'in_transit';
            // New stay at this department: restart the SLA clock and let the
            // scheduled sweep (documents:check-sla) re-notify if it overstays.
            $document->sla_warning_notified_at = null;
            $document->sla_breach_notified_at = null;
            $document->save();
            $document->load('currentDepartment');
            DocumentMoved::dispatch($document, $scan);
        } else {
            $manualNextId = $validated['next_department_id'] ?? null;
            $routedNext = $document->getNextDepartment();

            if ($manualNextId) {
                $nextDepartment = Department::find($manualNextId);
                $document->current_department_id = $manualNextId;
                $document->status = 'in_transit';
            } elseif ($routedNext) {
                $nextDepartment = $routedNext;
                $document->current_department_id = $routedNext->id;
                $document->status = 'in_transit';
            } else {
                return response()->json([
                    'message' => 'No next step in this document\'s route. Select the next department or mark the document complete.',
                    'requires_destination' => true,
                ], 422);
            }
            $document->save();
            $document->load('currentDepartment');
            DocumentMoved::dispatch($document, $scan);
        }

        $this->pushSessionScanLog($scan);

        return response()->json([
            'message' => 'Scan recorded successfully.',
            'scan_id' => $scan->id,
            'status' => $document->status,
            'next_department' => $nextDepartment ? [
                'id' => $nextDepartment->id,
                'name' => $nextDepartment->name,
            ] : null,
        ]);
    }

    public function sync(Request $request)
    {
        $this->ensureCanScan();

        $payload = $request->validate([
            'scans' => 'required|array|min:1',
            'scans.*.tracking_number' => 'required|string',
            'scans.*.department_id' => 'required|integer|exists:departments,id',
            'scans.*.action' => 'required|in:in,out',
            'scans.*.remarks' => 'nullable|string',
            'scans.*.scanned_at' => 'nullable|date',
            'scans.*.offline_uuid' => 'nullable|string',
        ]);

        $synced = [];
        $failed = [];
        foreach ($payload['scans'] as $item) {
            try {
                $this->ensureDepartmentForScan((int) $item['department_id']);
            } catch (HttpException $e) {
                $failed[] = ['offline_uuid' => $item['offline_uuid'] ?? null, 'reason' => $e->getMessage()];

                continue;
            }

            $document = Document::where('tracking_number', $item['tracking_number'])->first();
            if (! $document || $document->status === 'completed') {
                $failed[] = ['offline_uuid' => $item['offline_uuid'] ?? null, 'reason' => 'Document not valid for scan'];

                continue;
            }

            $scan = $this->recordScan($document, $item);
            $synced[] = ['offline_uuid' => $item['offline_uuid'] ?? null, 'scan_id' => $scan->id];
        }

        return response()->json(['synced' => $synced, 'failed' => $failed]);
    }

    /**
     * Undo the most recent scan (a mis-scan or premature handoff) and revert the
     * document to its prior location/status. An OUT is reversed by bringing the
     * document back to where it last checked in; an IN is simply removed (the IN
     * did not move it). The correction is written to the activity log.
     */
    public function undoLast(Document $document)
    {
        $this->ensureCanScan();

        $last = $document->scans()->first();
        if (! $last) {
            return back()->withErrors(['undo' => 'There is no scan to undo for this document.']);
        }

        // Only the department that recorded the scan (or an org-wide user) may undo it.
        $deptId = DepartmentScope::departmentId();
        if ($deptId !== null && (int) $last->department_id !== $deptId) {
            abort(403, 'You can only undo scans recorded by your own department.');
        }

        $undoneAction = $last->action;
        $undoneDeptName = $last->department->name ?? null;
        $last->delete();

        $remaining = $document->scans()->get();
        $latestIn = $remaining->firstWhere('action', 'in');

        if ($remaining->isEmpty()) {
            $document->current_department_id = null;
            $document->status = 'pending';
        } elseif ($undoneAction === 'out') {
            // Document had been forwarded; return it to its last check-in point.
            $document->current_department_id = $latestIn?->department_id;
            $document->status = $latestIn ? 'in_transit' : 'pending';
        } else {
            // Removed an arrival scan; it stays where it physically was.
            $document->status = 'in_transit';
        }
        $document->completed_at = null;
        $document->save();

        activity()
            ->performedOn($document)
            ->causedBy(auth()->user())
            ->log('Undid '.$undoneAction.' scan'.($undoneDeptName ? ' at '.$undoneDeptName : ''));

        return back()->with('status', 'The last scan was undone.');
    }

    private function recordScan(Document $document, array $data): DocumentScan
    {
        if (! empty($data['offline_uuid'])) {
            $existing = DocumentScan::where('offline_uuid', $data['offline_uuid'])->first();
            if ($existing) {
                return $existing;
            }
        } else {
            $existing = DocumentScan::where('document_id', $document->id)
                ->where('department_id', $data['department_id'])
                ->where('action', $data['action'])
                ->where('scanned_by', auth()->id())
                ->where('scanned_at', $data['scanned_at'] ?? now())
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        return DocumentScan::create([
            'document_id' => $document->id,
            'scanned_by' => auth()->id(),
            'department_id' => $data['department_id'],
            'action' => $data['action'],
            'scanned_at' => $data['scanned_at'] ?? now(),
            'location_ip' => request()->ip(),
            'remarks' => $data['remarks'] ?? null,
            'offline_uuid' => $data['offline_uuid'] ?? null,
            'synced' => true,
        ]);
    }

    private function pushSessionScanLog(DocumentScan $scan): void
    {
        $recent = collect(session('scanner.recent', []));
        $recent->prepend([
            'tracking_number' => $scan->document->tracking_number ?? '',
            'department' => $scan->department->name ?? '',
            'action' => strtoupper($scan->action),
            'at' => optional($scan->scanned_at)->format('Y-m-d H:i:s'),
        ]);

        session(['scanner.recent' => $recent->take(10)->values()->all()]);
    }

    private function ensureCanScan(): void
    {
        $user = auth()->user();

        // System administrators manage the org but do not operate scanners.
        if ($user?->can('manage system') || ! $user?->can('scan documents')) {
            abort(403, 'You do not have permission to scan documents.');
        }
    }

    private function ensureDepartmentForScan(int $departmentId): void
    {
        $allowed = DepartmentScope::departmentId();

        if ($allowed !== null && $departmentId !== $allowed) {
            abort(403, 'You can only record scans for your own department.');
        }
    }
}
