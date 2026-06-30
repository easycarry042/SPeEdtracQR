<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Concerns\ScopesByDepartment;
use App\Models\Department;
use App\Models\Document;
use App\Support\DepartmentScope;
use Illuminate\Http\Request;

class MovementController extends Controller
{
    use ScopesByDepartment;

    public function index(Request $request)
    {
        $user = auth()->user();
        $isOrgWide = DepartmentScope::isOrgWide($user);
        $tab = $request->get('tab', 'inbox');

        $documentWith = ['assignedTo', 'creator', 'attachments'];

        // ── Inbox: documents assigned to me and still active ──────────────────
        $inboxQuery = $this->scopeDocuments(
            Document::with($documentWith)
                ->whereIn('status', DocumentStatus::activeValues())
                ->where('assigned_to', $user->id)
        );

        // ── Tracking: other active documents (not assigned to me) ─────────────
        $trackingQuery = $this->scopeDocuments(
            Document::with($documentWith)
                ->whereIn('status', DocumentStatus::activeValues())
                ->where(fn ($q) => $q->where('assigned_to', '!=', $user->id)->orWhereNull('assigned_to'))
        );

        // ── Done: completed documents ─────────────────────────────────────────
        $sentQuery = $this->scopeDocuments(
            Document::with($documentWith)->where('status', DocumentStatus::Completed->value)
        );

        if ($request->boolean('overdue')) {
            // status_changed_at filter is coarse; do the precise SLA check in-memory below.
            $inboxQuery->whereNotNull('status_changed_at');
        }

        $inboxDocuments = $inboxQuery->orderBy('status_changed_at', 'asc')->paginate(25, ['*'], 'inbox_page')->withQueryString();
        $trackingDocuments = $trackingQuery->orderBy('updated_at', 'desc')->paginate(25, ['*'], 'tracking_page')->withQueryString();
        $sentDocuments = $sentQuery->orderBy('completed_at', 'desc')->paginate(25, ['*'], 'sent_page')->withQueryString();

        $departments = Department::orderBy('name')->get();

        $attachMeta = function ($doc) {
            // SLA anchored to the current status stage (manual model).
            $sla = $doc->statusEnum()->slaHours();
            $anchor = $doc->status_changed_at ?? $doc->updated_at ?? $doc->created_at;
            $elapsedHours = $anchor ? $anchor->diffInMinutes(now()) / 60 : 0;

            $doc->slaPct = $sla ? min(round(($elapsedHours / $sla) * 100), 100) : 0;
            $doc->slaOverdue = $sla && $elapsedHours > $sla;
            $doc->slaHoursLeft = $sla ? round($sla - $elapsedHours) : null;
            $doc->slaHoursOver = $doc->slaOverdue ? round($elapsedHours - $sla) : 0;
        };

        $inboxDocuments->each($attachMeta);
        $trackingDocuments->each($attachMeta);
        $sentDocuments->each($attachMeta);

        return view('movements.index', compact(
            'inboxDocuments',
            'trackingDocuments',
            'sentDocuments',
            'departments',
            'isOrgWide',
            'user',
            'tab'
        ));
    }
}
