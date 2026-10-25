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
use App\Support\RequestReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Assigns the staff member responsible for advancing a request through its
 * stages — from the dashboard's Review modal. (The old Assignments page that
 * listed requests here is gone; index() sends its links to the dashboard.)
 */
class AssignmentController extends Controller
{
    /**
     * The Assignments page is gone: the dashboard's Unassigned / In Progress /
     * Completed tabs list the same requests, and its Review modal assigns and
     * reassigns. Old links (and the staff directory's "their requests") land
     * on the matching tab, searched for the person when one is named.
     */
    public function index(Request $request): RedirectResponse
    {
        $tab = match (true) {
            $request->string('filter')->toString() === 'unclaimed' => 'pending',
            $request->string('status')->toString() === DocumentStatus::Completed->value => 'done',
            default => 'active',
        };

        $assignee = $request->filled('assignee') ? User::find((int) $request->get('assignee'))?->name : null;

        return to_route('dashboard', array_filter(['tab' => $tab, 'q' => $assignee ?? $request->get('q')]));
    }

    /** Legacy standalone Unclaimed queue — now the dashboard's Unassigned tab. */
    public function unclaimed(): RedirectResponse
    {
        return to_route('dashboard', ['tab' => 'pending']);
    }

    public function assign(Request $request, Document $document)
    {
        $validated = $request->validate([
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $assignedTo = $validated['assigned_to'] ?? null;

        // Only work this user can see on their own desk: a department head
        // reassigns within their office's requests, not another office's.
        abort_unless(AssignmentScope::applyDocumentScope(Document::whereKey($document->id))->exists(), 403);

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
        $message = $assignedTo
            ? "Assigned {$document->tracking_number} to {$name}."
            : "Cleared assignment for {$document->tracking_number}.";

        // The dashboard's Review modal reassigns in place and redraws the row.
        if ($request->expectsJson()) {
            $fresh = $document->fresh();

            return response()->json(['message' => $message, 'request' => [
                'assigned_to' => $fresh->assigned_to,
                'assigned_staff' => $assignee?->name,
                'needs_triage' => RequestReview::needsTriage($fresh),
            ]]);
        }

        return back()->with('status', $message);
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
     * Staff (or an authorized supervisor) releases an active task back to the
     * office pool (e.g. leaving early for field work or an emergency).
     */
    public function release(Request $request, Document $document)
    {
        $user = auth()->user();
        abort_unless($user, 403);
        abort_unless(
            $document->assigned_to === $user->id || $user->can('assign documents'),
            403,
            'Only the assigned staff member or an office supervisor can release this request.'
        );

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $reason = trim($validated['reason'] ?? '');

        $document->update([
            'assigned_to' => null,
            'assigned_by' => null,
            'assigned_at' => null,
            'accepted_at' => null,
            'status' => DocumentStatus::Pending->value,
            'status_changed_at' => now(),
        ]);

        event(new DocumentStatusUpdated($document->fresh(), $user));

        $note = "Released to office pool by {$user->name}".($reason ? ": {$reason}" : '');
        activity()
            ->performedOn($document)
            ->causedBy($user)
            ->log($note);

        $systemComment = $document->logSystemComment(
            "Released to office pool by {$user->name}".($reason ? " — {$reason}" : '').'. Returned to unassigned pool for peers to pick up.'
        );
        event(new DocumentCommentPosted($systemComment));

        // Notify department supervisors
        $supervisors = DocumentEvent::departmentTriagers($document->department_id, $user->id);
        foreach ($supervisors as $supervisor) {
            $supervisor->notify(new DocumentEvent(
                event: 'released_to_pool',
                title: "Request returned to pool: {$document->tracking_number}",
                body: "{$user->name} released ".($document->document_type ?? 'a document')." back to the office pool".($reason ? " ({$reason})" : '').'.',
                url: route('staff.dashboard', ['tab' => 'pending', 'q' => $document->tracking_number]),
                tracking: $document->tracking_number,
            ));
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => "Released {$document->tracking_number} back to the office pool.",
                'request' => $document->fresh(),
            ]);
        }

        return back()->with('status', "Released {$document->tracking_number} back to the office pool.");
    }
}
