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

        $base = fn (): Builder => $this->deptScope($this->scopeDocuments(Document::query()), $deptId);

        $totalRequests = $base()->count();
        $pendingRequest = $base()->whereIn('status', DocumentStatus::activeValues())->count();
        $completed = $base()->where('status', DocumentStatus::Completed->value)->count();

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
        $pendingRequests = $this->deptScope($this->scopeDocuments(
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

        return view('dashboard', ['totalRequests' => $totalRequests, 'pendingRequest' => $pendingRequest, 'completed' => $completed, 'completionRate' => $completionRate, 'recentActivity' => $recentActivity, 'pendingRequests' => $pendingRequests, 'pendingPayload' => $pendingPayload, 'assignableStaff' => $assignableStaff, 'staffWorkload' => $staffWorkload, 'statusSummary' => $statusSummary, 'atRiskDocuments' => $atRiskDocuments, 'atRiskCount' => $atRiskCount, 'dept' => $dept, 'isOrgWide' => $isOrgWide, 'slip' => $slip, 'internalAwaiting' => $internalAwaiting]);
    }

    /** Restrict a document query to one department (no-op when $deptId is null). */
    private function deptScope(Builder $query, ?int $deptId): Builder
    {
        return $query->when($deptId, fn (Builder $q) => $q->where('department_id', $deptId));
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

        $stages = array_map(fn (DocumentStatus $s): string => $s->label(), DocumentStatus::flow());
        $current = $doc->statusEnum()->position() ?: 1;

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
