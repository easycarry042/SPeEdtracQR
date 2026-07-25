<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Document;
use App\Models\DocumentAttachment;
use Illuminate\Http\UploadedFile;

trait StoresDocumentAttachments
{
    /**
     * @param  iterable<UploadedFile|null>  $files  Entries may be null (e.g. an
     *                                              optional file input that was
     *                                              left empty); guarded below.
     * @return array<int, DocumentAttachment>
     */
    protected function storeAttachmentsForDocument(
        Document $document,
        iterable $files,
        ?int $uploadedBy = null,
    ): array {
        $uploadedBy ??= auth()->id();
        $sort = (int) $document->attachments()->max('sort_order');
        $created = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $path = $file->store('document-attachments', 'local');
            $attachment = $document->attachments()->create([
                'file_path' => $path,
                'uploaded_by' => $uploadedBy,
                'sort_order' => ++$sort,
            ]);
            $created[] = $attachment;

            if (! $document->attachment_path) {
                $document->update(['attachment_path' => $path]);
            }
        }

        return $created;
    }
}
