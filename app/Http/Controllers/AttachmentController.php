<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StoresDocumentAttachments;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Support\DepartmentScope;
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
        abort_unless(DepartmentScope::userCanAccessDocument($document), 403);
        abort_unless(
            auth()->user()?->can('scan documents') || auth()->user()?->can('create documents'),
            403,
        );

        $request->validate([
            'attachments' => 'required|array|min:1|max:10',
            'attachments.*' => 'image|max:10240',
        ]);

        $deptId = DepartmentScope::departmentId();
        $created = $this->storeAttachmentsForDocument(
            $document,
            $request->file('attachments', []),
            $deptId,
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
        abort_unless(DepartmentScope::userCanAccessDocument($document), 403);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($attachment->file_path), 404);

        // Inline so images render in <img>; not forced as a download.
        return $disk->response($attachment->file_path);
    }
}
