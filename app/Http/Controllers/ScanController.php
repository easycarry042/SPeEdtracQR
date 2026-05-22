<?php

namespace App\Http\Controllers;

use App\Jobs\CheckSlaJob;
use App\Jobs\CheckSlaWarningJob;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentScan;
use App\Support\DepartmentScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ScanController extends Controller
{
    public function index()
    {
        if (auth()->user()?->hasRole('super_admin')) {
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

        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('document-attachments', 'public');
            if (Schema::hasColumn('document_scans', 'attachment_path')) {
                $scan->update(['attachment_path' => $path]);
            }
            if (Schema::hasTable('document_attachments')) {
                $document->attachments()->create([
                    'file_path' => $path,
                    'uploaded_by' => auth()->id(),
                    'department_id' => (int) $validated['department_id'],
                    'sort_order' => $document->attachments()->count(),
                ]);
            }
        }
        $nextDepartment = null;

        if ($validated['action'] === 'in') {
            $document->current_department_id = $validated['department_id'];
            $document->status = 'in_transit';
            $document->save();

            $slaHours = optional($document->currentDepartment)->sla_hours ?? 48;
            $warningHours = (int) floor($slaHours * 0.75);
            CheckSlaWarningJob::dispatch($document->id, (int) $validated['department_id'])->delay(now()->addHours($warningHours));
            CheckSlaJob::dispatch($document->id, (int) $validated['department_id'])->delay(now()->addHours($slaHours));
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

    private function recordScan(Document $document, array $data): DocumentScan
    {
        if (! empty($data['offline_uuid']) && Schema::hasColumn('document_scans', 'offline_uuid')) {
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

        $attributes = [
            'document_id' => $document->id,
            'scanned_by' => auth()->id(),
            'department_id' => $data['department_id'],
            'action' => $data['action'],
            'scanned_at' => $data['scanned_at'] ?? now(),
            'location_ip' => request()->ip(),
        ];

        if (Schema::hasColumn('document_scans', 'remarks')) {
            $attributes['remarks'] = $data['remarks'] ?? null;
        }
        if (Schema::hasColumn('document_scans', 'offline_uuid')) {
            $attributes['offline_uuid'] = $data['offline_uuid'] ?? null;
        }
        if (Schema::hasColumn('document_scans', 'sync_status')) {
            $attributes['sync_status'] = 'synced';
        } elseif (Schema::hasColumn('document_scans', 'synced')) {
            $attributes['synced'] = true;
        }

        return DocumentScan::create($attributes);
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
        if (! auth()->user()?->hasAnyRole(['staff', 'receiving_staff', 'department_admin'])) {
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
