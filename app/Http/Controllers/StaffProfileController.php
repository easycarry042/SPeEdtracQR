<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\StaffHighlight;
use App\Models\User;
use App\Support\AssignmentScope;
use App\Support\StaffProfile;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Staff profile (Direction 1b): identity rail + tabbed feed. Any authenticated
 * staff user may view any profile (peer view is read-only); only the owner sees
 * the highlight composer. Citizens are not users and cannot reach this page.
 */
class StaffProfileController extends Controller
{
    /**
     * Staff directory: roster of every user account with light accountability
     * stats (currently assigned + completed). Any authenticated staff may view it.
     */
    public function index(Request $request): Factory|View
    {
        $q = trim((string) $request->get('q'));
        $roles = Role::orderBy('name')->pluck('name');
        $role = in_array($request->get('role'), $roles->all(), true) ? $request->get('role') : '';
        $sort = in_array($request->get('sort'), ['name', 'load', 'completed'], true) ? $request->get('sort') : 'name';

        $users = User::query()
            ->with('roles:id,name')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")))
            ->when($role !== '', fn ($query) => $query->role($role))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        // One grouped query each — avoids per-user KPI recomputation (N+1).
        $assigned = Document::whereNotNull('assigned_to')
            ->whereIn('status', DocumentStatus::activeValues())
            ->select('assigned_to', DB::raw('COUNT(*) as total'))
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        $completed = Document::whereNotNull('assigned_to')
            ->where('status', DocumentStatus::Completed->value)
            ->select('assigned_to', DB::raw('COUNT(*) as total'))
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        $completedThisMonth = Document::whereNotNull('assigned_to')
            ->where('status', DocumentStatus::Completed->value)
            ->where('status_changed_at', '>=', now()->startOfMonth())
            ->select('assigned_to', DB::raw('COUNT(*) as total'))
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        // Overdue = active docs past their per-stage SLA budget. The budget is
        // per-stage PHP logic (DocumentStatus::slaHours), so evaluate the small
        // active set in memory rather than duplicating it in SQL.
        $overdue = Document::whereNotNull('assigned_to')
            ->whereIn('status', DocumentStatus::activeValues())
            ->get(['id', 'assigned_to', 'status', 'status_changed_at', 'updated_at', 'created_at'])
            ->filter(fn (Document $d): bool => $d->isOverdue())
            ->countBy('assigned_to');

        // Presence: most recent activity-log entry per user.
        $lastActive = DB::table('activity_log')
            ->where('causer_type', User::class)
            ->whereNotNull('causer_id')
            ->select('causer_id', DB::raw('MAX(created_at) as last'))
            ->groupBy('causer_id')
            ->pluck('last', 'causer_id');

        $staff = $users->map(fn (User $u): array => [
            'id' => $u->id,
            'name' => $u->name,
            'role' => $u->getRoleNames()->first(),
            'initials' => collect(explode(' ', $u->name))->filter()->take(2)->map(fn ($p): string => mb_strtoupper(mb_substr((string) $p, 0, 1)))->implode(''),
            'assigned' => (int) ($assigned[$u->id] ?? 0),
            'completed' => (int) ($completed[$u->id] ?? 0),
            'month' => (int) ($completedThisMonth[$u->id] ?? 0),
            'overdue' => (int) ($overdue[$u->id] ?? 0),
            'last_active' => isset($lastActive[$u->id])
                ? Date::parse($lastActive[$u->id])->diffForHumans(short: true)
                : null,
            'url' => route('staff.profile', $u->id),
        ]);

        $staff = match ($sort) {
            'load' => $staff->sortByDesc('assigned')->values(),
            'completed' => $staff->sortByDesc('completed')->values(),
            default => $staff,
        };

        // Scale for the open-load bar: the heaviest current load on the page.
        $loadMax = max(1, (int) $staff->max('assigned'));

        return view('staff.directory', ['staff' => $staff, 'q' => $q, 'role' => $role, 'sort' => $sort, 'roles' => $roles, 'loadMax' => $loadMax]);
    }

    /** JSON quick-search for the top-bar "jump to a person" dropdown. */
    public function search(Request $request)
    {
        $q = trim((string) $request->get('q'));
        if ($q === '') {
            return response()->json([]);
        }

        $results = User::query()
            ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name'])
            ->map(fn (User $u): array => [
                'name' => $u->name,
                'role' => $u->getRoleNames()->first(),
                'url' => route('staff.profile', $u->id),
            ]);

        return response()->json($results);
    }

    public function show(Request $request, User $user): Factory|View
    {
        $profile = new StaffProfile($user);
        $tab = in_array($request->get('tab'), ['activity', 'assigned', 'completions'], true)
            ? $request->get('tab')
            : 'activity';

        $isOwn = (int) auth()->id() === (int) $user->id;

        return view('staff.profile', [
            'profileUser' => $user,
            'isOwn' => $isOwn,
            'roleLabel' => $user->getRoleNames()->first(),
            'tab' => $tab,
            'kpis' => $profile->kpis(),
            'lastActiveAt' => $profile->lastActiveAt(),
            'heatmap' => $profile->heatmap(),
            'feed' => $tab === 'activity' ? $profile->feed() : collect(),
            'assigned' => $tab === 'assigned' ? $profile->assigned() : collect(),
            'completions' => $tab === 'completions' ? $profile->completions() : collect(),
            'worklist' => $isOwn ? $this->worklist($user) : collect(),
            'unclaimed' => $isOwn ? $this->unclaimed($user) : collect(),
            // The composer document picker lists only the author's own documents.
            'ownDocuments' => $isOwn ? $this->ownDocuments($user) : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'highlight_type' => ['required', Rule::in(StaffHighlight::TYPES)],
            'document_id' => ['nullable', 'integer', 'exists:documents,id'],
        ]);

        // A highlight may only reference a document the author owns (assigned or created).
        if (! empty($validated['document_id'])) {
            $owns = Document::where('id', $validated['document_id'])
                ->where(fn ($q) => $q->where('assigned_to', $user->id)->orWhere('created_by', $user->id))
                ->exists();

            if (! $owns) {
                throw ValidationException::withMessages([
                    'document_id' => 'You can only attach a document assigned to or created by you.',
                ]);
            }
        }

        $user->highlights()->create([
            'document_id' => $validated['document_id'] ?? null,
            'highlight_type' => $validated['highlight_type'],
            'body' => $validated['body'],
        ]);

        return to_route('staff.profile', ['user' => $user->id, 'tab' => 'activity'])
            ->with('status', 'Highlight posted.');
    }

    /** The author's own documents (assigned or created), for the composer picker. */
    private function ownDocuments(User $user)
    {
        return Document::where('assigned_to', $user->id)
            ->orWhere('created_by', $user->id)
            ->latest()
            ->limit(50)
            ->get(['id', 'tracking_number', 'document_type', 'status']);
    }

    private function worklist(User $user)
    {
        return AssignmentScope::applyDocumentScope(
            Document::query()
                ->with('creator:id,name')
                ->whereIn('status', ['pending', 'in_progress', 'in_review', 'approved', 'on_hold']),
            $user
        )->latest()->limit(20)->get();
    }

    private function unclaimed(User $user)
    {
        if (! AssignmentScope::canViewUnclaimedQueue($user)) {
            return collect();
        }

        return Document::query()
            ->whereNull('assigned_to')
            ->whereIn('status', ['pending', 'in_progress', 'in_review', 'approved'])
            ->latest()
            ->limit(10)
            ->get(['id', 'tracking_number', 'document_type', 'status']);
    }
}
