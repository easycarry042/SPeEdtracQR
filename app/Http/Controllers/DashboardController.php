<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Concerns\ScopesToAssignedWork;
use App\Models\Document;
use App\Models\RequestStep;
use App\Models\User;
use App\Support\AssignmentScope;
use App\Support\RequestReview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ScopesToAssignedWork;

    public function index()
    {
        $user = auth()->user();
        $isOrgWide = AssignmentScope::canViewAll($user);
        if (! $isOrgWide) {
            return to_route('staff.profile', ['user' => $user->id]);
        }
        // Department-head Supervisors manage only their OWN department; org-wide
        // admins (super_admin / manage system) see everything.
        $deptId = $user->can('manage system') ? null : $user->department_id;
        $dept = $deptId ? $user->department : null;

        // Whether this desk looks across more than one office. Not the same as
        // $isOrgWide, which only says the viewer may see every document — a
        // department head may, and is still looking at one office. The queue
        // below names each request's department only when that can vary.
        $spansDepartments = $deptId === null;

        $base = fn (): Builder => $this->deptScope($this->scopeDocuments(Document::query()), $deptId);

        $totalRequests = $base()->count();
        $pendingRequest = $base()->whereIn('status', DocumentStatus::activeValues())->count();
        $completed = $base()->where('status', DocumentStatus::Completed->value)->count();

        // The frame's tile row splits the open work three ways rather than
        // showing one "in progress" lump: what has not been started, what has
        // been started but not handed to anyone, and what is actually moving.
        $pendingOnly = $base()->where('status', DocumentStatus::Pending->value)->count();
        $inProgress = max(0, $pendingRequest - $pendingOnly);

        // Department completion rate — a headline of departmental performance.
        $completionRate = $totalRequests > 0 ? (int) round($completed / $totalRequests * 100) : null;

        $recentActivity = $base()->latest('created_at')->take(5)->get();

        // Staff workload / assignment distribution across the department's staff.
        $staffWorkload = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'staff'))
            ->when($deptId, fn ($q) => $q->where('department_id', $deptId))
            ->withCount([
                'assignedDocuments as active_count' => fn ($q) => $q->whereIn('status', DocumentStatus::activeValues()),
                'assignedDocuments as completed_count' => fn ($q) => $q->where('status', DocumentStatus::Completed->value),
            ])
            ->orderByDesc('active_count')
            ->orderBy('name')
            ->get(['id', 'name', 'department_id']);

        // The "Requests" table lists awaiting-approval work only: still Pending
        // and not yet assigned to a staff member. Approving (assign) removes it.
        // Internal dept-to-dept requests are excluded — they travel their own
        // endorsement chain (see the Internal Requests inbox), never this desk.
        //
        // Intake is scoped INCLUSIVELY: a citizen request whose request type has
        // no handling department is left unrouted (department_id null), and strict
        // department scoping hid those from every Supervisor — so tickets that had
        // already pinged them on the bell were nowhere to be found on this desk.
        // Unowned intake is visible to every triager, which is also who gets
        // notified about it (see DocumentEvent::departmentTriagers).
        $pendingRequests = $this->deptScopeIncludingUnrouted($this->scopeDocuments(
            Document::with('attachments', 'department', 'requirements')
                ->where('origin', '!=', Document::ORIGIN_INTERNAL)
                ->where('status', DocumentStatus::Pending->value)
                ->whereNull('assigned_to')
                ->latest('created_at')
        ), $deptId)->get();

        $pendingPayload = $pendingRequests->map(fn (Document $d): array => RequestReview::forModal($d))->values();

        // Staff who can be assigned a request — a department-head Supervisor sees
        // only their own department's staff; org-wide admins see everyone.
        $assignableStaff = RequestReview::assignableStaff(
            $user->can('manage system') ? null : $user->department_id,
        );

        $statusSummary = $this->deptScope($this->scopeDocuments(Document::query()), $deptId)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // At-risk = documents sitting in a non-terminal stage past 75% of that
        // stage's SLA budget (anchored to status_changed_at — the manual model).
        // The slip below names only the single worst offender; a department head
        // needs the whole list, because the second-worst is the one they can
        // still save.
        $activeQuery = $this->deptScope($this->scopeDocuments(
            Document::with('assignedTo', 'department')
                ->whereIn('status', DocumentStatus::activeValues())
        ), $deptId);

        $atRiskDocuments = $activeQuery->get()->map(function ($doc) {
            $sla = $doc->statusEnum()->slaHours();
            $anchor = $doc->status_changed_at ?? $doc->updated_at ?? $doc->created_at;
            if (! $sla || ! $anchor) {
                return null;
            }
            $elapsed = $anchor->diffInHours(now());
            $doc->sla_elapsed_hours = $elapsed;
            $doc->sla_remaining_hours = max(0, $sla - $elapsed);
            $doc->sla_overdue = $elapsed > $sla;
            $doc->sla_ratio = $elapsed / $sla;

            return $doc;
        })->filter(fn ($doc): bool => $doc && $doc->sla_ratio >= 0.75)
            ->sortBy('sla_remaining_hours')
            ->values();

        $atRiskCount = $atRiskDocuments->count();

        // ── Routing slip: the single most at-risk active document ─────────────
        $slip = $this->buildRoutingSlip($deptId);

        // Internal requests currently sitting at this supervisor's office (0
        // for org-wide accounts — they triage from the inbox instead).
        $internalAwaiting = $user->department_id
            ? Document::where('origin', Document::ORIGIN_INTERNAL)
                ->whereIn('status', DocumentStatus::activeValues())
                ->whereHas('requestSteps', fn ($q) => $q
                    ->where('status', RequestStep::STATUS_CURRENT)
                    ->where('department_id', $user->department_id))
                ->count()
            : 0;

        return view('dashboard', ['totalRequests' => $totalRequests, 'pendingRequest' => $pendingRequest, 'completed' => $completed, 'pendingOnly' => $pendingOnly, 'inProgress' => $inProgress, 'unassigned' => $pendingRequests->count(), 'completionRate' => $completionRate, 'recentActivity' => $recentActivity, 'pendingRequests' => $pendingRequests, 'pendingPayload' => $pendingPayload, 'assignableStaff' => $assignableStaff, 'staffWorkload' => $staffWorkload, 'statusSummary' => $statusSummary, 'atRiskDocuments' => $atRiskDocuments, 'atRiskCount' => $atRiskCount, 'dept' => $dept, 'isOrgWide' => $isOrgWide, 'spansDepartments' => $spansDepartments, 'slip' => $slip, 'internalAwaiting' => $internalAwaiting]);
    }

    /** Restrict a document query to one department (no-op when $deptId is null). */
    private function deptScope(Builder $query, ?int $deptId): Builder
    {
        return $query->when($deptId, fn (Builder $q) => $q->where('department_id', $deptId));
    }

    /**
     * Department scope for queues of UNOWNED work: this department's requests
     * plus every request no department owns yet. Without the null branch,
     * unrouted citizen submissions are invisible to all Supervisors.
     */
    private function deptScopeIncludingUnrouted(Builder $query, ?int $deptId): Builder
    {
        return $query->when($deptId, fn (Builder $q) => $q->where(
            fn (Builder $inner) => $inner
                ->where('department_id', $deptId)
                ->orWhereNull('department_id')
        ));
    }

    /**
     * Pick the single most at-risk in-transit document and shape it for the
     * dashboard routing slip (<x-routing-slip>). Largest overdue wins; if none
     * are overdue, the one closest to breaching its current department's SLA.
     *
     * @return array{type: string, citizen: string, code: string, overdue: bool, status_text: string, stages: array<int, string>, current: int, public_url: string}|null
     */
    private function buildRoutingSlip(?int $deptId = null): ?array
    {
        $doc = $this->deptScope($this->scopeDocuments(
            Document::whereIn('status', DocumentStatus::activeValues())
        ), $deptId)->get()
            ->map(function ($doc) {
                $sla = $doc->statusEnum()->slaHours();
                $anchor = $doc->status_changed_at ?? $doc->updated_at ?? $doc->created_at;

                // Signed hours past the stage SLA: positive = overdue, negative = time left.
                $doc->slipHoursOver = ($sla && $anchor)
                    ? $anchor->diffInHours(now()) - $sla
                    : null;

                return $doc;
            })
            ->filter(fn ($doc): bool => $doc->slipHoursOver !== null)
            ->sortByDesc('slipHoursOver')
            ->first();

        if (! $doc) {
            return null;
        }

        // The four-stage rail, not the five-status flow. Look Up, the review
        // modal and this slip all show the same document, and a citizen who is
        // told "stage 3 of 4" at the counter must not find "stage 3 of 5" on
        // the supervisor's screen. In Progress and In Review are one stage on
        // the rail; the status underneath keeps its own label elsewhere.
        $stages = array_column(DocumentStatus::railStages(), 'label');
        $current = $doc->statusEnum()->railStage(
            DocumentStatus::tryFrom((string) $doc->status_before_hold)
        );

        $over = (int) round($doc->slipHoursOver);
        $overdue = $over > 0;

        return [
            'type' => $doc->document_type,
            'citizen' => $doc->citizen_name ?? '—',
            'code' => $doc->tracking_number,
            'overdue' => $overdue,
            'status_text' => $overdue ? '+'.$over.'h overdue' : '~'.abs($over).'h left',
            'stages' => $stages,
            'current' => $current,
            'public_url' => route('track.show', $doc->tracking_number),
        ];
    }
}
