<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Concerns\ScopesToAssignedWork;
use App\Models\Document;
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

        $pendingPayload = RequestReview::collection($pendingRequests);

        // The office's work in flight. This is what the Look Up desk used to be
        // for: the desk is gone, and without this a Supervisor could only see
        // the requests nobody had started yet — the moment one was assigned it
        // dropped off their screen entirely until it came back completed.
        $activeRequests = $this->deptScope($this->scopeDocuments(
            Document::with('attachments', 'department', 'assignedTo', 'requirements')
                ->where('origin', '!=', Document::ORIGIN_INTERNAL)
                ->whereIn('status', DocumentStatus::activeValues())
                ->whereNotNull('assigned_to')
                ->latest('status_changed_at')
        ), $deptId)->get();

        $activePayload = RequestReview::collection($activeRequests);

        // Staff who can be assigned a request — a department-head Supervisor sees
        // only their own department's staff; org-wide admins see everyone.
        $assignableStaff = RequestReview::assignableStaff(
            $user->can('manage system') ? null : $user->department_id,
        );

        $statusSummary = $this->deptScope($this->scopeDocuments(Document::query()), $deptId)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // No SLA slip here: the supervisor assigns, the staff member advances,
        // so the at-risk warning lives on each staff member's own dashboard.
        // Internal requests have their own Internal tab (with a badge in the
        // sidebar when one arrives), not a section on this page.

        return view('dashboard', ['totalRequests' => $totalRequests, 'pendingRequest' => $pendingRequest, 'completed' => $completed, 'pendingOnly' => $pendingOnly, 'inProgress' => $inProgress, 'unassigned' => $pendingRequests->count(), 'completionRate' => $completionRate, 'recentActivity' => $recentActivity, 'pendingRequests' => $pendingRequests, 'pendingPayload' => $pendingPayload, 'activeRequests' => $activeRequests, 'activePayload' => $activePayload, 'assignableStaff' => $assignableStaff, 'staffWorkload' => $staffWorkload, 'statusSummary' => $statusSummary, 'dept' => $dept, 'isOrgWide' => $isOrgWide, 'spansDepartments' => $spansDepartments]);
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
}
