<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StoresDocumentAttachments;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Support\AssignmentScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    use StoresDocumentAttachments;

    /**
     * Upload one or more images to an existing document (e.g. from Movements review).
     */
    public function store(Request $request, Document $document)
    {
        abort_unless(AssignmentScope::userCanAccessDocument($document), 403);
        abort_unless(
            auth()->user()?->can('scan documents') || auth()->user()?->can('create documents'),
            403,
        );

        $request->validate([
            'attachments' => 'required|array|min:1|max:10',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:10240',
        ]);

        $created = $this->storeAttachmentsForDocument(
            $document,
            $request->file('attachments', []),
            null,
        );

        return response()->json([
            'message' => count($created).' photo(s) added to the document.',
            'attachments' => collect($created)->map(fn ($a) => [
                'id' => $a->id,
                'url' => route('attachments.show', $a),
            ])->values(),
        ]);
    }

    /**
     * Stream a document attachment from the private disk, but only to an
     * authenticated staff member whose department is allowed to see the
     * parent document. Replaces the old public /storage/... URLs so that
     * citizen documents are never anonymously fetchable.
     */
    public function show(DocumentAttachment $attachment): StreamedResponse
    {
        $document = $attachment->document;

        abort_if($document === null, 404);
        abort_unless(AssignmentScope::userCanAccessDocument($document), 403);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($attachment->file_path), 404);

        // Inline so images render in <img>; not forced as a download.
        return $disk->response($attachment->file_path);
    }

    /**
     * Delete a wrongly-placed attachment. Same access gate as store(): the
     * assignee / creator / admin who can act on the document may remove its files.
     */
    public function destroy(DocumentAttachment $attachment)
    {
        $document = $attachment->document;

        abort_if($document === null, 404);
        abort_unless(AssignmentScope::userCanAccessDocument($document), 403);
        abort_unless(
            auth()->user()?->can('scan documents') || auth()->user()?->can('create documents'),
            403,
        );

        Storage::disk('local')->delete($attachment->file_path);

        // If this was the document's cover file, repoint it to the next attachment.
        if ($document->attachment_path === $attachment->file_path) {
            $next = $document->attachments()->whereKeyNot($attachment->id)->orderBy('sort_order')->first();
            $document->update(['attachment_path' => $next?->file_path]);
        }

        $attachment->delete();

        activity()->performedOn($document)->causedBy(auth()->user())->log('Removed an attachment');

        if (request()->expectsJson()) {
            return response()->json(['message' => 'Attachment removed.']);
        }

        return back()->with('status', 'Attachment removed.');
    }
}
