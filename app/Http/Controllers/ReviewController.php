<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Events\DocumentCommentPosted;
use App\Events\DocumentStatusUpdated;
use App\Mail\AssignmentNotice;
use App\Models\Document;
use App\Models\User;
use App\Notifications\DocumentEvent;
use App\Support\AssignmentScope;
use App\Support\RequestReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Staff review lifecycle: opening a review moves a request to "In Review", and
 * approving it completes the request (Approved → Completed). Only the assigned
 * staff member (or an admin) may act. Mirrors DocumentStatusController's logging
 * and broadcasting so the timeline and Reverb stay consistent.
 */
class ReviewController extends Controller
{
    /**
     * Supervisor approve = assign the request to a staff member and move it to
     * In Progress. Gated by role (canViewAll) rather than the `assign documents`
     * permission row, so it works even on a partially-seeded database.
     */
    public function assignApprove(Request $request, Document $document)
    {
        abort_unless(AssignmentScope::canViewAll(auth()->user()), 403);

        $validated = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
        ]);

        $assignee = User::findOrFail($validated['assigned_to']);

        // Mirrors AssignmentController::assign — an assignee without this
        // permission would see a live Advance button on their dashboard that
        // DocumentStatusController then rejects.
        if (! $assignee->can('advance documents')) {
            throw ValidationException::withMessages([
                'assigned_to' => 'That user does not have permission to advance documents.',
            ]);
        }

        // Guest → Supervisor → Staff: a department head assigns only within their
        // own department (super admins may assign across departments).
        if (! AssignmentScope::canAssignWithinDepartment(auth()->user(), $assignee)) {
            throw ValidationException::withMessages([
                'assigned_to' => 'You can only assign staff in your own department.',
            ]);
        }

        $document->update([
            'assigned_to' => $assignee->id,
            'assigned_by' => auth()->id(),
            'assigned_at' => now(),
            'accepted_at' => null,
        ]);

        event(new DocumentStatusUpdated($document->fresh(), auth()->user()));

        activity()
            ->performedOn($document)
            ->causedBy(auth()->user())
            ->log("Assigned to {$assignee->name}");

        $comment = $document->logSystemComment("Assigned to {$assignee->name} — awaiting staff acceptance");
        event(new DocumentCommentPosted($comment));

        if ($assignee->email && config('tracking.notify_staff_on_assignment', true)) {
            Mail::to($assignee->email)->send(new AssignmentNotice($document->fresh(), $assignee));
            activity()->performedOn($document)->causedBy(auth()->user())->log("Emailed AssignmentNotice to {$assignee->name}");
        }

        // Header-bell ping for the assignee.
        $assignee->notify(DocumentEvent::assigned($document, auth()->user()->name));

        return $this->done($document, "Assigned {$document->tracking_number} to {$assignee->name}.");
    }

    /**
     * Supervisor deny = reject the request (terminal). Supervisor/admin only.
     */
    public function deny(Request $request, Document $document)
    {
        abort_unless(AssignmentScope::canViewAll(auth()->user()), 403);

        if ($document->statusEnum()->isTerminal()) {
            return $this->done($document, 'This request is already closed.');
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $reason = $validated['reason'] ?? null;

        $from = $document->statusEnum();
        $document->applyStatus(DocumentStatus::Denied);
        if ($reason) {
            $document->remarks = $reason;
        }
        // Attribute the decision so the citizen page can name who denied it.
        $document->decided_by = auth()->id();
        $document->decided_at = now();
        $document->save();

        activity()
            ->performedOn($document)
            ->causedBy(auth()->user())
            ->withProperties(['from' => $from->value, 'to' => DocumentStatus::Denied->value])
            ->log('Denied request'.($reason ? ': '.$reason : ''));

        event(new DocumentStatusUpdated($document, auth()->user()));

        $actor = auth()->user()?->name ?? 'Supervisor';
        $comment = $document->logSystemComment("Denied by {$actor}".($reason ? " — {$reason}" : ''));
        event(new DocumentCommentPosted($comment));

        return $this->done($document, "{$document->tracking_number} denied.");
    }

    /** Clicking "Review" transitions In Progress → In Review. */
    public function open(Document $document)
    {
        $this->authorizeReview($document);

        $current = $document->statusEnum();

        // Only advance forward from In Progress; never regress a later stage.
        if ($current === DocumentStatus::InProgress) {
            $this->applyAndLog($document, DocumentStatus::InReview, 'Opened review');
        }

        return response()->json([
            'status' => $document->status,
            'status_label' => $document->statusEnum()->label(),
        ]);
    }

    /** Final staff sign-off: Approved → Completed (history). */
    public function complete(Document $document)
    {
        $this->authorizeReview($document);

        if ($document->statusEnum()->isTerminal()) {
            return $this->done($document, 'This request is already closed.');
        }

        if ($document->statusEnum() !== DocumentStatus::Approved) {
            $message = 'Advance this request to Approved on the status stepper before approving it for completion.';

            if (request()->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withErrors(['status' => $message]);
        }

        $this->applyAndLog($document, DocumentStatus::Completed, 'Completed');

        return $this->done($document, "{$document->tracking_number} marked completed and moved to History.");
    }

    /** Staff accepts a supervisor assignment: Pending → In Progress. */
    public function acceptAssignment(Document $document)
    {
        $this->authorizeAssignedStaff($document);

        if (! RequestReview::needsTriage($document)) {
            return $this->assignmentError('This request is no longer awaiting acceptance.');
        }

        if ($document->statusEnum() === DocumentStatus::Pending) {
            $this->applyAndLog($document, DocumentStatus::InProgress, 'Accepted assignment');
            $document->refresh();
        } else {
            activity()
                ->performedOn($document)
                ->causedBy(auth()->user())
                ->log('Accepted assignment');

            $actor = auth()->user()?->name ?? 'Staff';
            $comment = $document->logSystemComment("Accepted assignment by {$actor}");
            event(new DocumentCommentPosted($comment));
        }

        $document->accepted_at = now();
        $document->save();

        event(new DocumentStatusUpdated($document->fresh(), auth()->user()));

        return $this->done($document, "Accepted {$document->tracking_number}.");
    }

    /** Staff declines an assignment — returns it to the supervisor queue. */
    public function declineAssignment(Request $request, Document $document)
    {
        $this->authorizeAssignedStaff($document);

        if (! RequestReview::needsTriage($document)) {
            return $this->assignmentError('This request is no longer awaiting acceptance.');
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $reason = $validated['reason'] ?? null;

        $document->update([
            'assigned_to' => null,
            'assigned_by' => null,
            'assigned_at' => null,
            'accepted_at' => null,
        ]);

        if ($document->statusEnum() === DocumentStatus::InProgress) {
            $document->applyStatus(DocumentStatus::Pending);
            $document->save();
        }

        $actor = auth()->user()?->name ?? 'Staff';
        activity()
            ->performedOn($document)
            ->causedBy(auth()->user())
            ->log('Declined assignment'.($reason ? ': '.$reason : ''));

        $comment = $document->logSystemComment("Assignment declined by {$actor}".($reason ? " — {$reason}" : ''));
        event(new DocumentCommentPosted($comment));
        // oversightNotified: the dedicated "Assignment declined" ping below says
        // more than a generic status notice would.
        event(new DocumentStatusUpdated($document->fresh(), auth()->user(), oversightNotified: true));

        // Header-bell ping for supervisors: the request is back in the queue.
        Notification::send(
            DocumentEvent::supervisors(auth()->id()),
            DocumentEvent::declined($document, $actor, $reason),
        );

        return $this->done($document, "Declined {$document->tracking_number}.");
    }

    /** Staff sends the request back for citizen revision. */
    public function requestRevision(Request $request, Document $document)
    {
        $this->authorizeAssignedStaff($document);

        if (! RequestReview::needsTriage($document)) {
            return $this->assignmentError('This request cannot be returned for revision at its current stage.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $from = $document->statusEnum();
        $document->applyStatus(DocumentStatus::Returned);
        $document->remarks = $validated['reason'];
        $document->save();

        activity()
            ->performedOn($document)
            ->causedBy(auth()->user())
            ->withProperties(['from' => $from->value, 'to' => DocumentStatus::Returned->value])
            ->log('Returned for revision: '.$validated['reason']);

        event(new DocumentStatusUpdated($document, auth()->user()));

        $actor = auth()->user()?->name ?? 'Staff';
        $comment = $document->logSystemComment("Returned for revision by {$actor} — {$validated['reason']}");
        event(new DocumentCommentPosted($comment));

        return $this->done($document, "{$document->tracking_number} returned for revision.");
    }

    private function assignmentError(string $message)
    {
        if (request()->expectsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withErrors(['status' => $message]);
    }

    private function applyAndLog(Document $document, DocumentStatus $to, string $verb): void
    {
        $from = $document->statusEnum();
        $document->applyStatus($to);
        $document->save();

        activity()
            ->performedOn($document)
            ->causedBy(auth()->user())
            ->withProperties(['from' => $from->value, 'to' => $to->value])
            ->log("{$verb}: {$from->label()} → {$to->label()}");

        event(new DocumentStatusUpdated($document, auth()->user()));

        $actor = auth()->user()?->name ?? 'Staff';
        $comment = $document->logSystemComment("{$verb}: {$from->label()} → {$to->label()} (by {$actor})");
        event(new DocumentCommentPosted($comment));
    }

    private function done(Document $document, string $message)
    {
        if (request()->expectsJson()) {
            return response()->json(['message' => $message, 'status' => $document->status]);
        }

        return to_route('staff.dashboard')->with('status', $message);
    }

    /**
     * Only the assigned staff member may drive the review forward; admins
     * (super_admin / supervisor) may act on any document. Authorization is based
     * on the assignment itself and role — not the `advance documents` permission
     * row, which may be absent on a partially-seeded database.
     */
    private function authorizeReview(Document $document): void
    {
        $user = auth()->user();

        abort_if($user === null, 403);

        // Admins / supervisors can act on anything in their scope.
        if (AssignmentScope::canViewAll($user)) {
            return;
        }

        // Otherwise you must be the staff member the document is assigned to.
        $isAssignedStaff = $document->assigned_to !== null
            && (int) $document->assigned_to === (int) $user->id;

        abort_unless($isAssignedStaff, 403, 'Only the assigned staff member can review this request.');
    }

    private function authorizeAssignedStaff(Document $document): void
    {
        $user = auth()->user();

        abort_if($user === null, 403);

        $isAssignedStaff = $document->assigned_to !== null
            && (int) $document->assigned_to === (int) $user->id;

        abort_unless($isAssignedStaff, 403, 'Only the assigned staff member can act on this request.');
    }
}
