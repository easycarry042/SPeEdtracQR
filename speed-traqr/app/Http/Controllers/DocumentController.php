<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Department;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:create documents')->only(['store']);
        $this->middleware('permission:scan documents')->only(['scan']);
        $this->middleware('permission:view all documents')->only(['index', 'show', 'history']);
    }

    public function index(Request $request)
    {
        $query = Document::with(['currentDepartment', 'creator']);

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->tracking_number) {
            $query->where('tracking_number', 'LIKE', "%{$request->tracking_number}%");
        }

        $documents = $query->latest()->paginate(20);
        return response()->json($documents);
    }

    public function store(Request $request)
    {
        $request->validate([
            'document_type' => 'required|string',
            'citizen_name' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);

        $trackingNumber = Document::generateTrackingNumber();

        $document = Document::create([
            'tracking_number' => $trackingNumber,
            'document_type' => $request->document_type,
            'citizen_name' => $request->citizen_name,
            'remarks' => $request->remarks,
            'status' => 'pending',
            'created_by' => auth()->id(),
        ]);

        // Generate QR code as SVG string
        $qrCode = QrCode::size(300)->generate(route('public.track', $trackingNumber));

        // Store QR code as file (optional)
        $qrPath = 'qrcodes/' . $trackingNumber . '.svg';
        Storage::disk('public')->put($qrPath, $qrCode);

        return response()->json([
            'document' => $document,
            'qr_code_url' => Storage::url($qrPath),
            'tracking_url' => route('public.track', $trackingNumber)
        ], 201);
    }

    public function show($id)
    {
        $document = Document::with(['scans.user', 'scans.department', 'currentDepartment'])->findOrFail($id);
        return response()->json($document);
    }

    public function history($trackingNumber)
    {
        $document = Document::where('tracking_number', $trackingNumber)
            ->with(['scans' => function ($q) {
                $q->orderBy('scanned_at', 'asc');
            }, 'scans.user', 'scans.department'])
            ->firstOrFail();

        return response()->json($document);
    }

    public function scan(Request $request)
    {
        $request->validate([
            'tracking_number' => 'required|exists:documents,tracking_number',
            'action' => 'required|in:in,out',
            'department_id' => 'required|exists:departments,id',
            'scanned_at' => 'nullable|date', // for offline sync
        ]);

        $document = Document::where('tracking_number', $request->tracking_number)->first();

        // Check if this is the first scan (IN)
        $existingScans = $document->scans()->count();

        if ($request->action === 'in') {
            // If document had no previous scans, it's entering first department
            if ($existingScans == 0) {
                $document->status = 'in_transit';
            }
            $document->current_department_id = $request->department_id;
        } elseif ($request->action === 'out') {
            // If document is being sent out, we don't clear current department yet
            // because the next 'in' will set it. But we can leave as is.
            // Optionally check if next department exists via routing rule.
            $nextDept = $document->getNextDepartment();
            if ($nextDept && $document->status !== 'completed') {
                // Suggestion can be sent back to frontend
                $suggestedNext = $nextDept->id;
            }
        }

        $document->save();

        $scan = DocumentScan::create([
            'document_id' => $document->id,
            'scanned_by' => auth()->id(),
            'department_id' => $request->department_id,
            'action' => $request->action,
            'scanned_at' => $request->scanned_at ?? now(),
            'location_ip' => $request->ip(),
            'synced' => true,
        ]);

        // If action is 'out' and there is a next department, you can optionally create a pending notification
        // For simplicity, we return suggested next department
        $suggestedNextDept = null;
        if ($request->action === 'out') {
            $next = $document->getNextDepartment();
            if ($next) {
                $suggestedNextDept = $next->id;
            } else {
                // No next department means document is completed
                if ($document->status !== 'completed') {
                    $document->status = 'completed';
                    $document->completed_at = now();
                    $document->save();
                }
            }
        }

        // Trigger overdue check (can be queued)
        if ($document->isOverdue()) {
            // Send email via notification (see below)
            dispatch(new \App\Jobs\SendOverdueAlert($document));
        }

        return response()->json([
            'message' => 'Scan recorded',
            'scan' => $scan,
            'document' => $document,
            'suggested_next_department' => $suggestedNextDept
        ]);
    }
}