<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Events\DocumentCommentPosted;
use App\Events\DocumentStatusUpdated;
use App\Mail\StatusUpdated;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Manual status progression. The document's assigned staff member (and admins)
 * move it through the DocumentStatus flow with explicit Advance / Move back /
 * Return controls — no scans, no routing decide this.
 */
class DocumentStatusController extends Controller
{
    public function advance(Document $document)
    {
        $this->authorizeChange($document);

        $next = $document->statusEnum()->next();
        if (! $next) {
            return $this->fail('This document is already at the final stage.');
        }

        return $this->transition($document, $next, 'Advanced');
    }

    public function revert(Document $document)
    {
        $this->authorizeChange($document);

        $previous = $document->statusEnum()->previous();
        if (! $previous) {
            return $this->fail('This document is already at the first stage.');
        }

        return $this->transition($document, $previous, 'Moved back');
    }

    /** Set an explicit stage — used for the off-line "Returned / For Revision" state. */
    public function set(Request $request, Document $document)
    {
        $this->authorizeChange($document);

        $validated = $request->validate([
            'status' => ['required', 'string'],
        ]);

        $target = DocumentStatus::tryFrom($validated['status']);
        if (! $target) {
            throw ValidationException::withMessages(['status' => 'Unknown status stage.']);
        }

        return $this->transition($document, $target, 'Set status');
    }

    private function transition(Document $document, DocumentStatus $to, string $verb)
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

        // Mirror into the unified per-document feed (staff timeline).
        $actor = auth()->user()?->name ?? 'Staff';
        $systemComment = $document->logSystemComment("{$verb}: {$from->label()} → {$to->label()} (by {$actor})");
        DocumentCommentPosted::dispatch($systemComment);

        $this->notifyCitizen($document, $to);

        $message = "Status updated to {$to->label()}.";

        if (request()->expectsJson()) {
            return response()->json([
                'message' => $message,
                'status' => $document->status,
                'status_label' => $to->label(),
            ]);
        }

        return back()->with('status', $message);
    }

    /**
     * Email the citizen about a status change, gated by:
     *   global config kill switch · per-stage config toggle · the document's
     *   notify_citizen opt-out · a citizen_email being present.
     */
    private function notifyCitizen(Document $document, DocumentStatus $to): void
    {
        if (! config('tracking.notify_citizen.enabled', true)) {
            return;
        }
        if (! ($document->notify_citizen ?? true) || ! $document->citizen_email) {
            return;
        }
        if (! (config('tracking.notify_citizen.stages.'.$to->value, false))) {
            return;
        }

        Mail::to($document->citizen_email)->send(new StatusUpdated($document));

        activity()
            ->performedOn($document)
            ->causedBy(auth()->user())
            ->log("Emailed StatusUpdated ({$to->label()}) to citizen");
    }

    private function fail(string $message)
    {
        if (request()->expectsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withErrors(['status' => $message]);
    }

    /**
     * Only the assigned staff member or an admin may change a document's stage.
     * `advance documents` covers staff; `assign documents` / `manage system`
     * cover admins (who can act on any document).
     */
    private function authorizeChange(Document $document): void
    {
        $user = auth()->user();

        $isAdmin = $user?->can('manage system') || $user?->can('assign documents');
        if ($isAdmin) {
            return;
        }

        $isAssignedStaff = $user?->can('advance documents')
            && $document->assigned_to !== null
            && (int) $document->assigned_to === (int) $user->id;

        abort_unless($isAssignedStaff, 403, 'Only the assigned staff member or an admin can change this document\'s status.');
    }
}
