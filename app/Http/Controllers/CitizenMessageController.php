<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentComment;
use App\Support\CitizenThread;
use App\Support\CitizenThreadAccess;
use App\Support\UploadRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The citizen's half of the conversation, behind Speedy's "Office messages" tab.
 *
 * Reading and writing both need the citizen to confirm a detail already on the
 * request (see CitizenThreadAccess). Writing always did — staff act on what this
 * thread says, so a forwarded link must not speak as the requester. Reading
 * used to need only the tracking number, but staff replies can carry personal
 * details, and the number is printed on slips and QR stickers.
 *
 * Every action answers JSON for Speedy and redirects back for a plain form.
 *
 * Everything a citizen writes or reads here is visibility=public. Internal staff
 * notes are never loaded, never rendered, and never reachable from this
 * controller.
 */
class CitizenMessageController extends Controller
{
    /** Confirm the email or contact number on file, unlocking the thread. */
    public function verify(Request $request, string $trackingNumber): RedirectResponse|JsonResponse
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

        if ($request->expectsJson()) {
            return $this->thread($request, $trackingNumber);
        }

        return back()->with('status', 'Verified — you can now message the office about this request.');
    }

    /**
     * The conversation, for a confirmed citizen. Opening it is reading it, so
     * the office's messages stop counting as unread.
     */
    public function thread(Request $request, string $trackingNumber): JsonResponse
    {
        $document = $this->trackableDocument($trackingNumber);

        if (! CitizenThreadAccess::isVerified($request, $document)) {
            return response()->json([
                'verified' => false,
                'can_verify' => CitizenThreadAccess::canBeVerified($document),
            ], 403);
        }

        CitizenThread::markRead($document);

        return response()->json([
            'verified' => true,
            'messages' => CitizenThread::messages($document),
        ]);
    }

    /** Post a message (or a reply) in the citizen-visible thread. */
    public function store(Request $request, string $trackingNumber): RedirectResponse|JsonResponse
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

        CitizenThread::post(
            $document,
            $validated['body'],
            $request->file('attachment'),
            $validated['parent_id'] ?? null,
        );

        if ($request->expectsJson()) {
            return $this->thread($request, $trackingNumber);
        }

        return back()->with('status', 'Message sent — the office will see it with your request.');
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
