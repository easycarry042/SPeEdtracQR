<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Events\DocumentCommentPosted;
use App\Events\DocumentStatusUpdated;
use App\Mail\AssignmentNotice;
use App\Models\Document;
use App\Models\User;
use App\Support\AssignmentScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

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

        $document->update([
            'assigned_to' => $assignee->id,
            'assigned_by' => auth()->id(),
            'assigned_at' => now(),
        ]);

        if ($document->statusEnum() === DocumentStatus::Pending) {
            $document->applyStatus(DocumentStatus::InProgress);
            $document->save();
        }

        DocumentStatusUpdated::dispatch($document->fresh(), auth()->user());

        activity()
            ->performedOn($document)
            ->causedBy(auth()->user())
            ->log("Assigned to {$assignee->name}");

        $comment = $document->logSystemComment("Assigned to {$assignee->name} — In Progress");
        DocumentCommentPosted::dispatch($comment);

        if ($assignee->email && config('tracking.notify_staff_on_assignment', true)) {
            Mail::to($assignee->email)->send(new AssignmentNotice($document->fresh(), $assignee));
            activity()->performedOn($document)->causedBy(auth()->user())->log("Emailed AssignmentNotice to {$assignee->name}");
        }

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
        $document->save();

        activity()
            ->performedOn($document)
            ->causedBy(auth()->user())
            ->withProperties(['from' => $from->value, 'to' => DocumentStatus::Denied->value])
            ->log('Denied request'.($reason ? ': '.$reason : ''));

        DocumentStatusUpdated::dispatch($document, auth()->user());

        $actor = auth()->user()?->name ?? 'Supervisor';
        $comment = $document->logSystemComment("Denied by {$actor}".($reason ? " — {$reason}" : ''));
        DocumentCommentPosted::dispatch($comment);

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

    /** Approving completes the request: Approved → Completed. */
    public function complete(Document $document)
    {
        $this->authorizeReview($document);

        if ($document->statusEnum()->isTerminal()) {
            return $this->done($document, 'This request is already completed.');
        }

        // Staff approval = completion. Record the Approved step for the audit
        // trail, then finalize as Completed.
        if ($document->statusEnum() !== DocumentStatus::Approved) {
            $this->applyAndLog($document, DocumentStatus::Approved, 'Approved');
        }
        $this->applyAndLog($document, DocumentStatus::Completed, 'Completed');

        return $this->done($document, "{$document->tracking_number} marked completed.");
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

        DocumentStatusUpdated::dispatch($document, auth()->user());

        $actor = auth()->user()?->name ?? 'Staff';
        $comment = $document->logSystemComment("{$verb}: {$from->label()} → {$to->label()} (by {$actor})");
        DocumentCommentPosted::dispatch($comment);
    }

    private function done(Document $document, string $message)
    {
        if (request()->expectsJson()) {
            return response()->json(['message' => $message, 'status' => $document->status]);
        }

        return redirect()->route('staff.dashboard')->with('status', $message);
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
}
