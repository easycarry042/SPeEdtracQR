<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Concerns\ScopesByDepartment;
use App\Models\Document;
use App\Support\AssignmentScope;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

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
            ->with('attachments')
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

        // Timeline of status-stage changes, reconstructed from the activity log
        // (manual model — no IN/OUT scans).
        $timeline = Activity::where('subject_type', $document->getMorphClass())
            ->where('subject_id', $document->id)
            ->orderBy('created_at')
            ->get()
            ->map(function ($activity) {
                $to = data_get($activity->properties, 'attributes.status');
                if (! $to) {
                    return null;
                }

                return [
                    'event' => 'Updated to '.DocumentStatus::fromLoose($to)->label(),
                    'timestamp' => optional($activity->created_at)->format('M d, Y h:i A'),
                    'action' => 'in',
                ];
            })
            ->filter()
            ->values();

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
