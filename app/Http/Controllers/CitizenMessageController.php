<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\DocumentCommentPosted;
use App\Mail\CitizenMessage;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Notifications\DocumentEvent;
use App\Support\CitizenThreadAccess;
use App\Support\UploadRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The citizen's half of the conversation.
 *
 * A citizen may read their request's thread with the tracking number alone, but
 * may only POST after confirming a detail already on the request (see
 * CitizenThreadAccess) — staff act on what this thread says, so a forwarded link
 * must not be able to speak as the requester.
 *
 * Everything a citizen writes or reads here is visibility=public. Internal staff
 * notes are never loaded, never rendered, and never reachable from this
 * controller.
 */
class CitizenMessageController extends Controller
{
    /** Confirm the email or contact number on file, unlocking the composer. */
    public function verify(Request $request, string $trackingNumber): RedirectResponse
    {
        $document = $this->trackableDocument($trackingNumber);

        $validated = $request->validate([
            'detail' => ['required', 'string', 'max:255'],
        ], [
            'detail.required' => 'Enter the email address or mobile number on this request.',
        ]);

        if (! CitizenThreadAccess::detailMatches($document, $validated['detail'])) {
            // One message for both cases: never confirm which detail is on file.
            throw ValidationException::withMessages([
                'detail' => "That doesn't match the contact details on this request. Try the email address or mobile number you filed it with.",
            ]);
        }

        CitizenThreadAccess::markVerified($request, $document);

        return back()->with('status', 'Verified — you can now message the office about this request.');
    }

    /** Post a message (or a reply) in the citizen-visible thread. */
    public function store(Request $request, string $trackingNumber): RedirectResponse
    {
        $document = $this->trackableDocument($trackingNumber);

        abort_unless(CitizenThreadAccess::isVerified($request, $document), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'attachment' => UploadRules::rules(),
            // A reply may only attach to a message in this request's PUBLIC
            // thread: passing an internal note's id must not thread a citizen
            // message under it (nor confirm that the note exists).
            'parent_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, callable $fail) use ($document): void {
                    $exists = DocumentComment::where('id', $value)
                        ->where('document_id', $document->id)
                        ->citizenVisible()
                        ->whereNull('parent_id')
                        ->exists();

                    if (! $exists) {
                        $fail('That message is no longer available to reply to.');
                    }
                },
            ],
        ], [
            'body.required' => 'Write your message first.',
        ]);

        $attachment = $this->storeAttachment($request, $document);

        $message = $document->comments()->create([
            'author_id' => null,
            'author_type' => DocumentComment::AUTHOR_CITIZEN,
            'author_name' => $document->citizen_name,
            'body' => $validated['body'],
            'visibility' => DocumentComment::VISIBILITY_PUBLIC,
            'parent_id' => $validated['parent_id'] ?? null,
            // The citizen has obviously read their own message; staff have not.
            'citizen_read_at' => now(),
            'attachment_path' => $attachment['path'],
            'attachment_name' => $attachment['name'],
        ]);

        activity()->performedOn($document)->log('Citizen posted a message on the request');

        event(new DocumentCommentPosted($message));

        $this->notifyStaff($document, $message);

        return back()->with('status', 'Message sent — the office will see it with your request.');
    }

    /**
     * Store the optional attachment on the PRIVATE disk. Message attachments are
     * served only through the gated route, never from a public URL.
     *
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
     * Tell the office a citizen is waiting on an answer: the assignee gets the
     * bell and an email; with nobody assigned yet, the triagers get the bell so
     * the question is not left unread until assignment.
     */
    private function notifyStaff(Document $document, DocumentComment $message): void
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

    /**
     * The same rule the public tracking page uses: a real, non-internal request.
     * Internal dept-to-dept requests have no citizen and are 404 to the public.
     */
    private function trackableDocument(string $trackingNumber): Document
    {
        $document = Document::where('tracking_number', strtoupper(trim($trackingNumber)))
            ->where('origin', '!=', Document::ORIGIN_INTERNAL)
            ->first();

        abort_if($document === null, 404);

        return $document;
    }

    /** Stream a message attachment to the citizen who owns the thread. */
    public function attachment(Request $request, DocumentComment $comment)
    {
        abort_unless($comment->hasAttachment(), 404);
        abort_unless($comment->isPublic(), 404);

        $document = $comment->document;
        abort_if($document === null || $document->origin === Document::ORIGIN_INTERNAL, 404);
        abort_unless(CitizenThreadAccess::isVerified($request, $document), 403);
        abort_unless(Storage::disk('local')->exists($comment->attachment_path), 404);

        return Storage::disk('local')->download(
            $comment->attachment_path,
            $comment->attachment_name ?: basename($comment->attachment_path),
        );
    }
}
