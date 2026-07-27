<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\RequirementRevisionRequested;
use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Support\AssignmentScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentRequirementController extends Controller
{
    /**
     * Staff review of a single supporting document: Approved / Needs revision /
     * Rejected, with a comment. Needs-revision and rejected outcomes email the
     * citizen the comment; needs-revision items are then re-uploadable by the
     * citizen from the tracking page.
     */
    public function review(Request $request, Document $document, DocumentRequirement $requirement)
    {
        abort_unless($requirement->document_id === $document->id, 404);
        abort_unless($document->canBeAdvancedBy(auth()->user()), 403, 'Only the assigned staff member or an admin can review requirements.');

        $validated = $request->validate([
            'review_status' => ['required', Rule::in(DocumentRequirement::REVIEW_STATUSES)],
            // A comment is required when returning or rejecting so the citizen
            // knows what to fix / why it failed.
            'review_comment' => [
                Rule::requiredIf(fn (): bool => in_array($request->input('review_status'), [
                    DocumentRequirement::REVIEW_NEEDS_REVISION,
                    DocumentRequirement::REVIEW_REJECTED,
                ], true)),
                'nullable', 'string', 'max:1000',
            ],
        ], [
            'review_comment.required' => 'Please explain what needs to be corrected.',
        ]);

        $requirement->update([
            'review_status' => $validated['review_status'],
            'review_comment' => $validated['review_comment'] ?? null,
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ]);

        activity()->performedOn($document)->causedBy(auth()->user())
            ->log("Reviewed requirement \"{$requirement->label}\": {$requirement->reviewStatusLabel()}");

        // Email the citizen when a document is returned or rejected.
        if (in_array($requirement->review_status, [DocumentRequirement::REVIEW_NEEDS_REVISION, DocumentRequirement::REVIEW_REJECTED], true)
            && $document->citizen_email) {
            Mail::to($document->citizen_email)->send(new RequirementRevisionRequested($document, $requirement));
            activity()->performedOn($document)->log("Emailed the citizen about \"{$requirement->label}\" ({$requirement->reviewStatusLabel()})");
        }

        return back()->with('status', "Marked \"{$requirement->label}\" as {$requirement->reviewStatusLabel()}.");
    }

    /**
     * Toggle a requirement's verified state — staff confirm they've seen the
     * original. Only the assignee/admin who can advance the document may verify.
     */
    public function toggle(Document $document, DocumentRequirement $requirement)
    {
        abort_unless($requirement->document_id === $document->id, 404);
        abort_unless($document->canBeAdvancedBy(auth()->user()), 403, 'Only the assigned staff member or an admin can verify requirements.');

        if ($requirement->isVerified()) {
            $requirement->update(['verified_at' => null, 'verified_by' => null]);
            $message = "Marked \"{$requirement->label}\" as not yet verified.";
        } else {
            $requirement->update(['verified_at' => now(), 'verified_by' => auth()->id()]);
            $message = "Verified \"{$requirement->label}\".";
        }

        activity()->performedOn($document)->log($message);

        return back()->with('status', $message);
    }

    /**
     * Stream a citizen-uploaded requirement file from the private disk to an
     * authorized staff member only (never anonymously fetchable).
     */
    public function file(Document $document, DocumentRequirement $requirement): StreamedResponse
    {
        abort_unless($requirement->document_id === $document->id, 404);
        abort_unless(AssignmentScope::userCanAccessDocument($document), 403);
        abort_if($requirement->uploaded_file_path === null, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($requirement->uploaded_file_path), 404);

        return $disk->response($requirement->uploaded_file_path);
    }
}
