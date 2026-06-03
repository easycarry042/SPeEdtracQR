<?php

namespace App\Http\Controllers;

use App\Models\DocumentAttachment;
use App\Support\DepartmentScope;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
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
