<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesByDepartment;
use App\Models\Department;
use App\Models\Document;
use App\Support\DepartmentScope;
use App\Support\SlaQuery;
use Illuminate\Http\Request;

class MovementController extends Controller
{
    use ScopesByDepartment;

    public function index(Request $request)
    {
        $user = auth()->user();
        $isOrgWide = DepartmentScope::isOrgWide($user);
        $deptId = DepartmentScope::departmentId($user);
        $tab = $request->get('tab', 'inbox');

        $documentWith = [
            'currentDepartment',
            'creator',
            'routeSteps.department',
            'attachments',
            'scans' => fn ($q) => $q->where('action', 'in')->orderBy('scanned_at', 'desc'),
        ];

        // ── Inbox: documents at this department now ───────────────────────────
        $inboxQuery = $this->scopeCurrentDocuments(
            Document::with($documentWith)
                ->whereIn('status', ['pending', 'in_transit'])
                ->whereNotNull('current_department_id')
        );

        if ($isOrgWide && $dept = $request->get('department')) {
            $inboxQuery->where('current_department_id', $dept);
        }

        if ($request->boolean('overdue')) {
            $inboxQuery->whereHas('scans', function ($q) {
                $q->where('action', 'in')
                    ->whereColumn('document_scans.department_id', 'documents.current_department_id')
                    ->whereRaw(SlaQuery::scanOverdueHoursSql());
            });
        }

        // ── Tracking: documents you follow but are not in your inbox ──────────
        $trackingQuery = $this->scopeDocuments(
            Document::with($documentWith)
                ->whereIn('status', ['pending', 'in_transit'])
        );

        if ($deptId) {
            $trackingQuery->where(function ($q) use ($deptId) {
                $q->where('current_department_id', '!=', $deptId)
                    ->orWhereNull('current_department_id');
            });
        } elseif ($isOrgWide && $dept = $request->get('department')) {
            $trackingQuery->where(function ($q) use ($dept) {
                $q->where('current_department_id', '!=', $dept)
                    ->orWhereNull('current_department_id');
            });
        }

        // ── Sent: documents scanned OUT from this department today ────────────
        $sentQuery = Document::with($documentWith);

        if (! $isOrgWide && $deptId) {
            $sentQuery->whereHas('scans', fn ($q) => $q
                ->where('action', 'out')
                ->where('department_id', $deptId)
                ->whereDate('scanned_at', today())
            );
        } else {
            $sentQuery->whereHas('scans', fn ($q) => $q
                ->where('action', 'out')
                ->whereDate('scanned_at', today())
            );
        }

        $inboxDocuments = $inboxQuery->orderBy('updated_at', 'asc')->paginate(25, ['*'], 'inbox_page')->withQueryString();
        $trackingDocuments = $trackingQuery->orderBy('updated_at', 'desc')->paginate(25, ['*'], 'tracking_page')->withQueryString();
        $sentDocuments = $sentQuery->orderBy('updated_at', 'desc')->paginate(25, ['*'], 'sent_page')->withQueryString();

        $departments = Department::orderBy('name')->get();

        $attachMeta = function ($doc) use ($deptId) {
            $doc->routingChain = $doc->getRoutingChain();
            $doc->nextDepartment = $doc->getNextDepartment();
            $doc->isLastStop = $doc->isAtLastRouteStop();
            $doc->canAct = $deptId
                ? (int) $doc->current_department_id === $deptId
                : true;

            $lastIn = $doc->scans
                ->where('department_id', $doc->current_department_id)
                ->first();

            // Tracking/sent lists (esp. for org-wide super admins) can include
            // documents with no current department yet, so guard the access.
            $slaHours = $doc->currentDepartment?->sla_hours ?? 0;
            $elapsedHours = $lastIn ? $lastIn->scanned_at->diffInMinutes(now()) / 60 : 0;
            $doc->slaPct = $slaHours > 0 ? min(round(($elapsedHours / $slaHours) * 100), 100) : 0;
            $doc->slaOverdue = $slaHours > 0 && $elapsedHours > $slaHours;
            $doc->slaHoursLeft = $slaHours > 0 ? round($slaHours - $elapsedHours) : null;
            $doc->slaHoursOver = $doc->slaOverdue ? round($elapsedHours - $slaHours) : 0;
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
