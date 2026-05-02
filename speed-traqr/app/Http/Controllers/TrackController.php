<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;

class TrackController extends Controller
{
    public function index(Request $request)
    {
        $trackingNumber = trim((string) $request->get('tracking_number'));

        if ($trackingNumber !== '') {
            return redirect()->route('track.show', $trackingNumber);
        }

        return view('track.index');
    }

    public function show($trackingNumber)
    {
        $document = Document::where('tracking_number', $trackingNumber)
            ->with(['scans' => function ($q) {
                $q->orderBy('scanned_at', 'asc');
            }, 'scans.department', 'scans.user', 'currentDepartment'])
            ->firstOrFail();

        $documents = collect();
        if (auth()->check()) {
            $documents = Document::latest('created_at')
                ->take(30)
                ->get(['id', 'tracking_number', 'document_type', 'status', 'created_at']);
        }

        $routingSteps = $document->scans
            ->pluck('department.name')
            ->filter()
            ->unique()
            ->values();

        $timeline = $document->scans->map(function ($scan) {
            $firstName = explode(' ', $scan->user->name ?? 'System')[0];
            $event = $scan->action === 'in'
                ? "Received by {$firstName}"
                : "Handed over by {$firstName}";

            return [
                'event' => $event . ' (' . ($scan->department->name ?? 'Unknown Department') . ')',
                'timestamp' => optional($scan->scanned_at)->format('M d, Y h:i A'),
                'action' => $scan->action,
            ];
        })->values();

        return view('track.show', [
            'document' => $document,
            'documents' => $documents,
            'routingSteps' => $routingSteps,
            'timeline' => $timeline,
            'isPublicView' => ! auth()->check(),
        ]);
    }
}