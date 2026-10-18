<?php

declare(strict_types=1);

namespace App\Support;

use App\Events\DocumentCommentPosted;
use App\Mail\CitizenMessage;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Notifications\DocumentEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * A request's conversation as the citizen sees it: the citizen-visible messages
 * only, oldest first, every staff reply named and marked as staff.
 *
 * Shared by the two places a citizen talks to the office — Speedy's "Office
 * messages" tab and the form endpoints behind it — so posting, notifying and
 * marking read behave the same either way. Internal staff notes are never
 * loaded here (citizenVisible()), so they cannot leak into either.
 *
 * Every method assumes the caller has already confirmed the citizen
 * (CitizenThreadAccess): reading the thread needs that, not only writing it.
 */
final class CitizenThread
{
    /**
     * The thread, flattened: replies sit in time order with everything else,
     * the way a chat reads.
     *
     * @return list<array{id: int, from: string, name: string, body: string, at: ?string, attachment: ?array{name: string, url: string}}>
     */
    public static function messages(Document $document): array
    {
        return $document->allComments()
            ->citizenVisible()
            ->with(['author.department', 'document.department'])
            ->reorder()
            ->oldest()
            ->oldest('id')
            ->get()
            ->map(fn (DocumentComment $comment): array => [
                'id' => $comment->id,
                // citizen | staff | system — how the bubble is drawn.
                'from' => $comment->author_type,
                'name' => $comment->publicAuthorLabel(),
                'body' => $comment->body,
                'at' => $comment->created_at?->format('M j, g:i A'),
                'attachment' => $comment->hasAttachment() ? [
                    'name' => $comment->attachment_name ?: 'Attachment',
                    'url' => route('track.messages.attachment', $comment),
                ] : null,
            ])
            ->values()
            ->all();
    }

    /** Office messages the citizen has not seen yet. */
    public static function unreadCount(Document $document): int
    {
        return $document->allComments()
            ->citizenVisible()
            ->where('author_type', '!=', DocumentComment::AUTHOR_CITIZEN)
            ->whereNull('citizen_read_at')
            ->count();
    }

    /** The citizen has the thread open: the office's messages are read. */
    public static function markRead(Document $document): void
    {
        $document->allComments()
            ->citizenVisible()
            ->where('author_type', '!=', DocumentComment::AUTHOR_CITIZEN)
            ->whereNull('citizen_read_at')
            ->update(['citizen_read_at' => now()]);
    }

    /** The citizen writes to the office; the office is told. */
    public static function post(Document $document, string $body, ?UploadedFile $attachment = null, ?int $parentId = null): DocumentComment
    {
        $message = $document->comments()->create([
            'author_id' => null,
            'author_type' => DocumentComment::AUTHOR_CITIZEN,
            'author_name' => $document->citizen_name,
            'body' => $body,
            'visibility' => DocumentComment::VISIBILITY_PUBLIC,
            'parent_id' => $parentId,
            // The citizen has obviously read their own message; staff have not.
            'citizen_read_at' => now(),
            'attachment_path' => $attachment?->store("message-attachments/{$document->tracking_number}", 'local'),
            'attachment_name' => $attachment?->getClientOriginalName(),
        ]);

        activity()->performedOn($document)->log('Citizen posted a message on the request');

        // Staff screens update live; the citizen's public channel gets its
        // content-free ping from DocumentComment::booted().
        event(new DocumentCommentPosted($message));

        self::notifyStaff($document, $message);

        return $message;
    }

    /**
     * Tell the office a citizen is waiting on an answer: the assignee gets the
     * bell and an email; with nobody assigned yet, the triagers get the bell so
     * the question is not left unread until assignment.
     */
    private static function notifyStaff(Document $document, DocumentComment $message): void
    {
        $assignee = $document->assignedTo;

        if ($assignee) {
            $assignee->notify(DocumentEvent::citizenMessage($document, $message->body));

            if ($assignee->email) {
                Mail::to($assignee->email)->send(new CitizenMessage($message, $assignee));
            }

            return;
        }

        Notification::send(
            DocumentEvent::departmentTriagers($document->department_id),
            DocumentEvent::citizenMessage($document, $message->body),
        );
    }
}
