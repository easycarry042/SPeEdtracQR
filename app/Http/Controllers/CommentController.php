<?php

namespace App\Http\Controllers;

use App\Events\DocumentCommentPosted;
use App\Mail\StaffMessage;
use App\Models\Document;
use App\Models\DocumentComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Staff collaboration feed. Assigned staff (and admins) post updates/notes on a
 * document. 'internal' notes are staff-only; 'public' posts appear on the
 * citizen tracking page and email the citizen.
 */
class CommentController extends Controller
{
    public function store(Request $request, Document $document)
    {
        $this->authorizePost($document);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'visibility' => ['required', Rule::in([DocumentComment::VISIBILITY_INTERNAL, DocumentComment::VISIBILITY_PUBLIC])],
            'parent_id' => ['nullable', 'integer', Rule::exists('document_comments', 'id')->where('document_id', $document->id)],
        ]);

        $comment = $document->comments()->create([
            'author_id' => auth()->id(),
            'author_type' => 'staff',
            'body' => $validated['body'],
            'visibility' => $validated['visibility'],
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        activity()
            ->performedOn($document)
            ->causedBy(auth()->user())
            ->log(($comment->isPublic() ? 'Posted public message' : 'Posted internal note').' on document');

        // Citizen-facing post: email the citizen (respecting the per-ticket opt-out).
        if ($comment->isPublic() && $document->citizen_email && ($document->notify_citizen ?? true)) {
            Mail::to($document->citizen_email)->send(new StaffMessage($comment));
            activity()->performedOn($document)->causedBy(auth()->user())->log('Emailed StaffMessage to citizen');
        }

        event(new DocumentCommentPosted($comment));

        return back()->with('status', $comment->isPublic()
            ? 'Public message posted — the citizen has been notified.'
            : 'Internal note added.');
    }

    /**
     * Only the assigned staff member or an admin may post on a document — same
     * rule as advancing its status (see DocumentStatusController).
     */
    private function authorizePost(Document $document): void
    {
        $user = auth()->user();

        if ($user?->can('manage system') || $user?->can('assign documents')) {
            return;
        }

        $isAssignedStaff = $user?->can('advance documents')
            && $document->assigned_to !== null
            && (int) $document->assigned_to === (int) $user->id;

        abort_unless($isAssignedStaff, 403, 'Only the assigned staff member or an admin can post on this document.');
    }
}
