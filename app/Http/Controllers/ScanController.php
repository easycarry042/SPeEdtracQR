<?php

namespace App\Http\Controllers;

use App\Jobs\CheckSlaJob;
use App\Jobs\CheckSlaWarningJob;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentScan;
use App\Models\RoutingRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ScanController extends Controller
{
    public function index()
    {
        $departments = Department::orderBy('name')->get();
        $sessionScans = collect(session('scanner.recent', []))->take(10);
        $userDepartmentId = auth()->user()?->department_id;

        return view('scan.index', compact('departments', 'sessionScans', 'userDepartmentId'));
    }

    public function store(Request $request)
    {
        $this->ensureCanScan();

        $validated = $request->validate([
            'tracking_number'    => 'required|string',
            'department_id'      => 'required|exists:departments,id',
            'action'             => 'required|in:in,out',
            'remarks'            => 'nullable|string',
            'scanned_at'         => 'nullable|date',
            'offline_uuid'       => 'nullable|string',
            'next_department_id' => 'nullable|exists:departments,id',
        ]);

        $document = Document::where('tracking_number', $validated['tracking_number'])->first();
        if (! $document) {
            return response()->json(['message' => 'Tracking number not found.'], 404);
        }

        if ($document->status === 'completed') {
            return response()->json(['message' => 'Document already completed.'], 422);
        }

        $scan = $this->recordScan($document, $validated);
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
            $rule = RoutingRule::where('document_type', $document->document_type)
                ->where('from_department_id', $validated['department_id'])
                ->orderBy('step_order')
                ->first();

            $manualNextId = $validated['next_department_id'] ?? null;

            if ($manualNextId) {
                $nextDepartment = Department::find($manualNextId);
                $document->current_department_id = $manualNextId;
                $document->status = 'in_transit';
            } elseif ($rule) {
                $nextDepartment = Department::find($rule->to_department_id);
                $document->current_department_id = $rule->to_department_id;
                $document->status = 'in_transit';
            } else {
                return response()->json([
                    'message'              => 'No routing rule found for this document type. Please select the next department.',
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
        if (!empty($data['offline_uuid']) && Schema::hasColumn('document_scans', 'offline_uuid')) {
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
        if (! auth()->user()?->hasAnyRole(['staff', 'receiving_staff', 'super_admin'])) {
            abort(403, 'You do not have permission to scan documents.');
        }
    }
}
