<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Notifications\DocumentEvent;
use App\Support\StatusGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentReviewController extends Controller
{
    /**
     * Confirm supervisor clearance: Ongoing / InReview -> Completed (Ready for Claim).
     * Restricted to department_admin or users with review/assign permissions.
     */
    public function confirm(Request $request, Document $document): JsonResponse
    {
        $user = $request->user();

        $canReview = $user->hasRole('department_admin')
            || $user->hasRole('Supervisor')
            || $user->can('manage system')
            || $user->can('assign documents');

        if (! $canReview) {
            abort(403, 'Only a supervisor or department administrator can confirm clearance.');
        }

        if ($document->status->isTerminal()) {
            return response()->json(['message' => 'This request is already closed.'], 422);
        }

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
            'claim_date' => ['nullable', 'date'],
        ]);

        $note = $validated['note'] ?? null;
        $claimDate = $validated['claim_date'] ?? null;

        // Verify status gate
        StatusGate::validate($document, DocumentStatus::Completed, $note);

        $now = now();
        $document->update([
            'status' => DocumentStatus::Completed->value,
            'reviewed_by' => $user->id,
            'reviewed_at' => $now,
            'supervisor_notes' => $note,
            'decided_by' => $user->id,
            'decided_at' => $now,
            'completed_at' => $now,
            'status_changed_at' => $now,
            'claim_date' => $claimDate ?? $document->claim_date,
        ]);

        // Notify assigned staff
        if ($document->assigned_to && $document->assigned_to !== $user->id) {
            $document->assignee?->notify(DocumentEvent::approved($document, $user->name));
        }

        return response()->json([
            'message' => "Approved {$document->tracking_number}. It is now Ready for Claim.",
            'status' => DocumentStatus::Completed->value,
        ]);
    }
}
