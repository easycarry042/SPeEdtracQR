<?php

namespace App\Http\Controllers;

use App\Events\DocumentCommentPosted;
use App\Mail\StaffMessage;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\User;
use App\Notifications\DocumentEvent;
use App\Support\AssignmentScope;
use App\Support\UploadRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * The staff half of a request's conversation.
 *
 * Two threads, one table (see DocumentComment):
 *   - 'public'   messages are the citizen ↔ staff conversation: they appear on
 *                the tracking page and email the citizen.
 *   - 'internal' notes are the staff-only thread: a question posted there pings
 *                the assignee, and an answer pings whoever asked.
 *
 * Any staff member who may act on the request may post and answer, so a question
 * does not stall while the assignee is away.
 */
class CommentController extends Controller
{
    public function store(Request $request, Document $document)
    {
        $this->authorizePost($document);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'visibility' => ['required', Rule::in([DocumentComment::VISIBILITY_INTERNAL, DocumentComment::VISIBILITY_PUBLIC])],
            'attachment' => UploadRules::rules(),
            // One level of nesting only: the feed renders a message and its
            // replies, so a reply-to-a-reply would be saved and never shown.
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('document_comments', 'id')
                    ->where('document_id', $document->id)
                    ->whereNull('parent_id'),
            ],
        ]);

        $attachment = $this->storeAttachment($request, $document);

        $comment = $document->comments()->create([
            'author_id' => auth()->id(),
            'author_type' => DocumentComment::AUTHOR_STAFF,
            'author_name' => auth()->user()->name,
            'body' => $validated['body'],
            'visibility' => $validated['visibility'],
            'parent_id' => $validated['parent_id'] ?? null,
            // Staff have obviously read what staff wrote; the citizen has not.
            'staff_read_at' => now(),
            'attachment_path' => $attachment['path'],
            'attachment_name' => $attachment['name'],
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

        if ($comment->isInternal()) {
            $this->notifyInternalThread($document, $comment);
        }

        event(new DocumentCommentPosted($comment));

        return back()->with('status', $comment->isPublic()
            ? 'Message sent — the citizen has been notified.'
            : 'Internal note added.');
    }

    /**
     * Mark the citizen's messages on this request as read. Called when a staff
     * member opens the conversation, which is what clears the ticket's badge.
     */
    public static function markCitizenMessagesRead(Document $document): void
    {
        $document->allComments()->unreadByStaff()->update(['staff_read_at' => now()]);
    }

    /** Stream a message attachment to staff who may see the request. */
    public function attachment(DocumentComment $comment)
    {
        abort_unless($comment->hasAttachment(), 404);

        $document = $comment->document;
        abort_if($document === null, 404);
        abort_unless(AssignmentScope::userCanAccessDocument($document), 403);
        abort_unless(Storage::disk('local')->exists($comment->attachment_path), 404);

        return Storage::disk('local')->download(
            $comment->attachment_path,
            $comment->attachment_name ?: basename($comment->attachment_path),
        );
    }

    /**
     * @return array{path: ?string, name: ?string}
     */
    private function storeAttachment(Request $request, Document $document): array
    {
        if (! $request->hasFile('attachment')) {
            return ['path' => null, 'name' => null];
        }

        $file = $request->file('attachment');

        return [
            'path' => $file->store("message-attachments/{$document->tracking_number}", 'local'),
            'name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * Internal-thread pings (bell only — email is reserved for citizen traffic):
     *   - a new question reaches the assignee, who is expected to answer;
     *   - an answer reaches whoever asked, so they need not keep checking back.
     */
    private function notifyInternalThread(Document $document, DocumentComment $comment): void
    {
        $actor = auth()->user();

        if ($comment->parent_id === null) {
            $assignee = $document->assignedTo;

            if ($assignee && (int) $assignee->id !== (int) $actor->id) {
                $assignee->notify(DocumentEvent::internalQuestion($document, $actor->name, $comment->body));
            }

            return;
        }

        $asker = $comment->parent?->author;

        if ($asker instanceof User && (int) $asker->id !== (int) $actor->id) {
            $asker->notify(DocumentEvent::internalAnswer($document, $actor->name, $comment->body));
        }
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
