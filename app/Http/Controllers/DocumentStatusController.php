<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Events\DocumentCommentPosted;
use App\Events\DocumentStatusUpdated;
use App\Mail\ActionNeededMail;
use App\Mail\StatusUpdated;
use App\Models\Document;
use App\Support\StatusGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Manual status progression. The document's assigned staff member (and admins)
 * move it through the DocumentStatus flow with explicit Advance / Move back /
 * Return controls — no scans, no routing decide this.
 */
class DocumentStatusController extends Controller
{
    public function advance(Request $request, Document $document)
    {
        $this->authorizeChange($document);

        $validated = $request->validate([
            'expected_status' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        StatusGate::assertExpectedStatus($document, $validated['expected_status']);

        $next = $document->statusEnum()->next();
        if (! $next) {
            return $this->fail('This document is already at the final stage.');
        }

        if ($next === DocumentStatus::Completed) {
            return $this->fail('Use Approve on the Requests page to mark this document completed and move it to History.');
        }

        // Hard stage gates: unmet requirements (and a missing review note for
        // →Approved) block the transition with a per-requirement message.
        StatusGate::validate($document, $next, $validated['note'] ?? null);

        return $this->transition($document, $next, 'Advanced', $validated['note'] ?? null);
    }

    public function revert(Request $request, Document $document)
    {
        $this->authorizeChange($document);

        // Moving a document backwards always carries a "why" for the audit trail.
        $validated = $request->validate([
            'expected_status' => ['required', 'string'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        StatusGate::assertExpectedStatus($document, $validated['expected_status']);

        $previous = $document->statusEnum()->previous();
        if (! $previous) {
            return $this->fail('This document is already at the first stage.');
        }

        return $this->transition($document, $previous, 'Moved back', $validated['reason']);
    }

    /** Set an explicit stage — used for the off-line "Returned / For Revision" state. */
    public function set(Request $request, Document $document)
    {
        $this->authorizeChange($document);

        $validated = $request->validate([
            'expected_status' => ['required', 'string'],
            'status' => ['required', 'string'],
            // "Return for revision" must tell the citizen what to fix.
            'reason' => ['required_if:status,'.DocumentStatus::Returned->value, 'nullable', 'string', 'max:1000'],
        ], [
            'reason.required_if' => 'A reason is required when returning a document for revision.',
        ]);

        StatusGate::assertExpectedStatus($document, $validated['expected_status']);

        $target = DocumentStatus::tryFrom($validated['status']);
        if (! $target || in_array($target, [DocumentStatus::OnHold, DocumentStatus::Completed], true)) {
            // On Hold has its own endpoint (it needs a reason); Completed is reached via advance().
            throw ValidationException::withMessages(['status' => 'Unknown or disallowed status stage.']);
        }

        // The reason is stored as remarks (transition saves) so the citizen
        // tracking page can show what to fix — mirrors the triage revision path.
        if (! empty($validated['reason'])) {
            $document->remarks = $validated['reason'];
        }

        return $this->transition($document, $target, 'Set status', $validated['reason'] ?? null);
    }

    /**
     * Park a blocked document (waiting on the citizen, an external office, or a
     * future date). Requires a reason. The SLA clock PAUSES: we leave
     * status_changed_at and the SLA-notified markers untouched so the stage
     * resumes cleanly on un-hold, and On Hold has a null SLA so the sweep skips it.
     */
    public function hold(Request $request, Document $document)
    {
        $this->authorizeChange($document);

        $stage = $document->statusEnum();
        if ($stage === DocumentStatus::OnHold) {
            return $this->fail('This document is already on hold.');
        }
        if ($stage->isTerminal()) {
            return $this->fail('A completed document cannot be put on hold.');
        }

        $validated = $request->validate([
            'hold_reason' => ['required', 'string', 'max:1000'],
            'blocked_by' => ['required', Rule::in(Document::BLOCKED_BY)],
            'hold_until' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $document->forceFill([
            'status_before_hold' => $document->status,
            'status' => DocumentStatus::OnHold->value,
            'hold_reason' => $validated['hold_reason'],
            'blocked_by' => $validated['blocked_by'],
            'hold_until' => $validated['hold_until'] ?? null,
            'held_at' => now(),
            'held_by' => auth()->id(),
        ])->save();

        $actor = auth()->user()?->name ?? 'Staff';

        activity()
            ->performedOn($document)
            ->causedBy(auth()->user())
            ->withProperties(['blocked_by' => $validated['blocked_by'], 'hold_until' => $validated['hold_until'] ?? null])
            ->log("On hold (waiting on {$validated['blocked_by']}): {$validated['hold_reason']}");

        DocumentStatusUpdated::dispatch($document, auth()->user());

        $systemComment = $document->logSystemComment("Put on hold by {$actor} — waiting on {$validated['blocked_by']}: {$validated['hold_reason']}");
        DocumentCommentPosted::dispatch($systemComment);

        if ($validated['blocked_by'] === 'citizen') {
            $this->notifyCitizenActionNeeded($document);
        }

        return $this->respond($document, DocumentStatus::OnHold, 'Document placed on hold.');
    }

    /**
     * Manually lift a hold (never automatic). Restores the pre-hold stage and
     * resumes the SLA exactly where it paused by shifting status_changed_at
     * forward by the hold duration — the hold window is not counted against SLA.
     */
    public function unhold(Document $document)
    {
        $this->authorizeChange($document);

        if ($document->statusEnum() !== DocumentStatus::OnHold) {
            return $this->fail('This document is not on hold.');
        }

        $restored = DocumentStatus::fromLoose($document->status_before_hold, DocumentStatus::InProgress);

        // Resume the stage clock: push the anchor forward by however long the hold lasted.
        if ($document->status_changed_at && $document->held_at) {
            $paused = $document->held_at->diffInSeconds(now());
            $document->status_changed_at = $document->status_changed_at->copy()->addSeconds($paused);
        } elseif (! $document->status_changed_at) {
            $document->status_changed_at = now();
        }

        $document->forceFill([
            'status' => $restored->value,
            'status_before_hold' => null,
            'hold_reason' => null,
            'hold_until' => null,
            'blocked_by' => null,
            'held_at' => null,
            'held_by' => null,
        ])->save();

        $actor = auth()->user()?->name ?? 'Staff';

        activity()
            ->performedOn($document)
            ->causedBy(auth()->user())
            ->log("Resumed from hold → {$restored->label()}");

        DocumentStatusUpdated::dispatch($document, auth()->user());

        $systemComment = $document->logSystemComment("Resumed from hold → {$restored->label()} (by {$actor})");
        DocumentCommentPosted::dispatch($systemComment);

        return $this->respond($document, $restored, "Hold lifted — back to {$restored->label()}.");
    }

    /** Citizen "action needed" email when a hold is blocked on the citizen. Same gate as notifyCitizen(). */
    private function notifyCitizenActionNeeded(Document $document): void
    {
        if (! config('tracking.notify_citizen.enabled', true)) {
            return;
        }
        if (! ($document->notify_citizen ?? true) || ! $document->citizen_email) {
            return;
        }
        if (! config('tracking.notify_citizen.stages.'.DocumentStatus::OnHold->value, false)) {
            return;
        }

        Mail::to($document->citizen_email)->send(new ActionNeededMail($document));

        activity()
            ->performedOn($document)
            ->causedBy(auth()->user())
            ->log('Emailed ActionNeeded (on hold) to citizen');
    }

    private function respond(Document $document, DocumentStatus $stage, string $message)
    {
        if (request()->expectsJson()) {
            return response()->json([
                'message' => $message,
                'status' => $document->status,
                'status_label' => $stage->label(),
            ]);
        }

        return back()->with('status', $message);
    }

    private function transition(Document $document, DocumentStatus $to, string $verb, ?string $note = null)
    {
        $from = $document->statusEnum();
        $note = trim((string) $note) ?: null;

        $document->applyStatus($to);
        $document->save();

        activity()
            ->performedOn($document)
            ->causedBy(auth()->user())
            ->withProperties(array_filter([
                'from' => $from->value,
                'to' => $to->value,
                'note' => $note,
            ]))
            ->log("{$verb}: {$from->label()} → {$to->label()}");

        DocumentStatusUpdated::dispatch($document, auth()->user());

        // Mirror into the unified per-document feed (staff timeline).
        $actor = auth()->user()?->name ?? 'Staff';
        $line = "{$verb}: {$from->label()} → {$to->label()} (by {$actor})";
        if ($note) {
            $line .= " — {$note}";
        }
        $systemComment = $document->logSystemComment($line);
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
