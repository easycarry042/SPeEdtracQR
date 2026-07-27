<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DocumentStatus;
use App\Events\DocumentCommentPosted;
use App\Events\DocumentStatusUpdated;
use App\Http\Controllers\Controller;
use App\Mail\AssignmentNotice;
use App\Models\Document;
use App\Models\User;
use App\Notifications\DocumentEvent;
use App\Support\AssignmentScope;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Admin assignment desk: lists documents and lets an admin assign the staff
 * member responsible for advancing each file through its status stages.
 */
class AssignmentController extends Controller
{
    public function index(Request $request): Factory|View
    {
        $query = Document::with(['assignedTo', 'creator'])->latest();
        $query = AssignmentScope::applyDocumentScope($query);

        // Unclaimed tab: same desk, filtered to documents nobody owns yet.
        $filter = $request->string('filter')->toString();
        if ($filter === 'unclaimed') {
            abort_unless(AssignmentScope::canViewUnclaimedQueue(auth()->user()), 403);
            $query->whereNull('assigned_to');
        }

        // Deep link from the staff directory: documents owned by one person.
        if ($request->filled('assignee')) {
            $query->where('assigned_to', (int) $request->get('assignee'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where(function ($q) use ($term): void {
                $q->where('tracking_number', 'like', $term)
                    ->orWhere('citizen_name', 'like', $term)
                    ->orWhere('document_type', 'like', $term);
            });
        }

        $documents = $query->paginate(20)->withQueryString();
        $staff = $this->assignableStaff();
        $statuses = DocumentStatus::cases();

        return view('admin.assignments.index', ['documents' => $documents, 'staff' => $staff, 'statuses' => $statuses, 'filter' => $filter]);
    }

    /**
     * Legacy standalone Unclaimed queue — folded into the Assignments desk as
     * a tab; the old URL redirects into it.
     */
    public function unclaimed()
    {
        return to_route('admin.assignments.index', ['filter' => 'unclaimed']);
    }

    public function assign(Request $request, Document $document)
    {
        $validated = $request->validate([
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $assignedTo = $validated['assigned_to'] ?? null;

        // The dashboard's rendered advance control trusts this assignment to mean
        // the assignee can actually act on the document — so the assignee must
        // hold `advance documents`, not just exist. Otherwise the request would
        // show a live Advance button that always 403s (see StatusController).
        if ($assignedTo && ! User::find($assignedTo)?->can('advance documents')) {
            throw ValidationException::withMessages([
                'assigned_to' => 'That user does not have permission to advance documents.',
            ]);
        }

        // Department heads assign only within their own department (org-wide admins
        // may assign across departments).
        if ($assignedTo && ! AssignmentScope::canAssignWithinDepartment(auth()->user(), User::find($assignedTo))) {
            throw ValidationException::withMessages([
                'assigned_to' => 'You can only assign staff in your own department.',
            ]);
        }

        $document->update([
            'assigned_to' => $assignedTo,
            'assigned_by' => $assignedTo ? auth()->id() : null,
            'assigned_at' => $assignedTo ? now() : null,
            'accepted_at' => null,
        ]);

        event(new DocumentStatusUpdated($document->fresh(), auth()->user()));

        $assignee = $assignedTo ? User::find($assignedTo) : null;
        $name = $assignee?->name ?? ($assignedTo ? 'a staff member' : null);

        activity()
            ->performedOn($document)
            ->causedBy(auth()->user())
            ->log($assignedTo ? "Assigned to {$name}" : 'Cleared assignment');

        // Mirror into the unified per-document feed (staff timeline).
        $systemComment = $document->logSystemComment($assignedTo ? "Assigned to {$name}" : 'Assignment cleared');
        event(new DocumentCommentPosted($systemComment));

        // Notify the newly assigned staff member by email (queued, configurable).
        if ($assignee && $assignee->email && config('tracking.notify_staff_on_assignment', true)) {
            Mail::to($assignee->email)->send(new AssignmentNotice($document->fresh(), $assignee));
            activity()->performedOn($document)->causedBy(auth()->user())->log("Emailed AssignmentNotice to {$name}");
        }

        // Header-bell ping for the assignee (not for self-assignment).
        if ($assignee && (int) $assignee->id !== (int) auth()->id()) {
            $assignee->notify(DocumentEvent::assigned($document, auth()->user()->name));
        }

        // Assignment is purely manual (admin picks the staff member); it does NOT
        // change the document's status. Status only moves when the assigned staff
        // advance it via the status controls (DocumentStatusController).
        return back()->with('status', $assignedTo
            ? "Assigned {$document->tracking_number} to {$name}."
            : "Cleared assignment for {$document->tracking_number}.");
    }

    public function accept(Document $document)
    {
        $user = auth()->user();
        abort_unless($user?->can('accept documents') || $user?->can('assign documents'), 403);
        abort_unless($document->assigned_to === null, 422, 'Document is already assigned.');
        abort_unless(AssignmentScope::canViewUnclaimedQueue($user), 403);

        $document->update([
            'assigned_to' => $user->id,
            'assigned_by' => $user->id,
            'assigned_at' => now(),
            'accepted_at' => now(),
        ]);

        if ($document->statusEnum() === DocumentStatus::Pending) {
            $document->applyStatus(DocumentStatus::InProgress);
            $document->save();
        }
        event(new DocumentStatusUpdated($document->fresh(), $user));

        activity()
            ->performedOn($document)
            ->causedBy($user)
            ->log("Accepted assignment by {$user->name}");

        $systemComment = $document->logSystemComment("Accepted assignment by {$user->name}");
        event(new DocumentCommentPosted($systemComment));

        return back()->with('status', "Accepted {$document->tracking_number}.");
    }

    /**
     * Staff eligible to be responsible for a document — those who can advance it.
     * Scoped to the admin's department unless they are org-wide.
     */
    private function assignableStaff()
    {
        $user = auth()->user();

        return User::query()
            ->permission('advance documents')
            ->when(
                $user && ! $user->can('manage system') && $user->department_id,
                fn ($q) => $q->where('department_id', $user->department_id),
            )
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
