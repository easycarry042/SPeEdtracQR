<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Concerns\ScopesByDepartment;
use App\Models\Document;
use App\Support\AssignmentScope;
use Illuminate\Http\Request;

class TrackController extends Controller
{
    use ScopesByDepartment;

    public function index(Request $request)
    {
        if (auth()->user()?->can('manage system')) {
            return redirect()->route('admin.dashboard');
        }

        $trackingNumber = trim((string) $request->get('tracking_number'));

        if ($trackingNumber !== '') {
            return redirect()->route('track.show', $trackingNumber);
        }

        // For logged-in staff, open the list + detail view on the most recent
        // in-progress document in their scope instead of a bare search box.
        // (The top search bar and the document list still let them jump to any
        // other document.)
        if (auth()->check()) {
            $latest = $this->scopeDocuments(
                Document::query()
                    ->whereIn('status', DocumentStatus::activeValues())
                    ->latest('created_at')
            )->first(['tracking_number']);

            if ($latest) {
                return redirect()->route('track.show', $latest->tracking_number);
            }
        }

        return view('track.index');
    }

    public function show($trackingNumber)
    {
        $document = Document::where('tracking_number', $trackingNumber)
            ->with(['scans' => function ($q) {
                $q->orderBy('scanned_at', 'asc');
            }, 'scans.user', 'attachments'])
            ->firstOrFail();

        if (auth()->user()?->can('manage system')) {
            return redirect()->route('admin.dashboard');
        }

        $documents = collect();
        if (auth()->check()) {
            $this->authorizeDocumentAccess($document);

            // Sidebar list: only documents still being processed
            $documents = $this->scopeDocuments(
                Document::query()
                    ->whereIn('status', DocumentStatus::activeValues())
                    ->latest('created_at')
            )->take(30)->get(['id', 'tracking_number', 'document_type', 'status', 'created_at']);
        }

        // Collaboration feed. Staff see everything; the public view is filtered
        // to citizen-facing posts only (see the citizen Blade).
        $document->load(['comments.author', 'comments.replies.author']);

        $user = auth()->user();
        $canAct = false;
        if ($user && $document->status !== 'completed') {
            $canAct = AssignmentScope::canViewAll($user)
                || ((int) $document->assigned_to === (int) $user->id && $user->can('advance documents'));
        }

        $isLastStop = true;
        $nextDepartment = null;

        $timeline = $document->scans->map(function ($scan) {
            $firstName = explode(' ', $scan->user->name ?? 'System')[0];
            $event = $scan->action === 'in'
                ? "Received by {$firstName}"
                : "Handed over by {$firstName}";

            return [
                'event' => $event,
                'timestamp' => optional($scan->scanned_at)->format('M d, Y h:i A'),
                'action' => $scan->action,
            ];
        })->values();

        $prediction = null;
        $anomaly = null;

        $isPublicView = ! auth()->check();
        $view = $isPublicView ? 'track.show-citizen' : 'track.show';

        return view($view, [
            'document' => $document,
            'documents' => $documents,
            'routingChain' => collect(),
            'routingSteps' => collect(),
            'timeline' => $timeline,
            'isPublicView' => $isPublicView,
            'canAct' => $canAct,
            'isLastStop' => $isLastStop,
            'nextDepartment' => $nextDepartment,
            'prediction' => $prediction,
            'anomaly' => $anomaly,
        ]);
    }

    public function status($trackingNumber)
    {
        $document = Document::where('tracking_number', $trackingNumber)->firstOrFail();

        return response()->json([
            'status' => $document->status,
            'current_department' => null,
            'updated_at' => $document->updated_at?->toISOString(),
        ]);
    }
}
