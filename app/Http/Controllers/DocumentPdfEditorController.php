<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DocumentAttachment;
use App\Support\AssignmentScope;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Built-in PDF editor: staff open an attached PDF, place their registered
 * e-signature and typed notes on it, and save.
 *
 * Two rules shape the design:
 *   1. The original is never touched. Saving writes a NEW attachment, so the
 *      file the citizen submitted stays on record next to the signed version —
 *      a records office must be able to show what it received.
 *   2. Editing happens in the browser (pdf.js to render, pdf-lib to write), so
 *      no PDF toolchain has to be installed on the office server. This endpoint
 *      only authorizes and stores the resulting bytes.
 */
class DocumentPdfEditorController extends Controller
{
    /** Same 10 MB ceiling as every other upload path. */
    private const MAX_BYTES = 10 * 1024 * 1024;

    public function edit(DocumentAttachment $attachment): Factory|View
    {
        $document = $this->authorizeEditing($attachment);

        return view('documents.pdf-editor', [
            'attachment' => $attachment,
            'document' => $document,
            'hasSignature' => (bool) auth()->user()->signature_path,
        ]);
    }

    public function store(Request $request, DocumentAttachment $attachment): JsonResponse
    {
        $document = $this->authorizeEditing($attachment);

        $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:'.(int) (self::MAX_BYTES / 1024)],
        ], [
            'pdf.required' => 'The edited document could not be read. Please try saving again.',
        ]);

        $binary = (string) file_get_contents($request->file('pdf')->getRealPath());

        // Defence in depth: mimes: trusts the client's extension/type, so confirm
        // the bytes really start with a PDF header before storing them.
        if (! str_starts_with($binary, '%PDF-')) {
            throw ValidationException::withMessages(['pdf' => 'That file is not a valid PDF.']);
        }

        $version = $document->attachments()->count() + 1;
        $path = "document-attachments/{$document->tracking_number}-signed-v{$version}.pdf";
        Storage::disk('local')->put($path, $binary);

        $saved = $document->attachments()->create([
            'file_path' => $path,
            'uploaded_by' => auth()->id(),
        ]);

        activity()
            ->performedOn($document)
            ->causedBy(auth()->user())
            ->log('Saved an edited copy of an attachment (e-signed in the PDF editor)');

        $document->logSystemComment('Edited PDF saved by '.auth()->user()->name.' (original kept on file).');

        return response()->json([
            'message' => 'Saved as a new version — the original is still on file.',
            'attachment' => [
                'id' => $saved->id,
                'url' => $saved->authorizedUrl(),
            ],
            'redirect' => route('track.show', $document->tracking_number),
        ]);
    }

    /**
     * Editing is stricter than viewing: only someone who may edit the document
     * (its assignee, a supervisor, or an admin) may sign or annotate its files,
     * and only PDFs open in the editor.
     */
    private function authorizeEditing(DocumentAttachment $attachment)
    {
        $document = $attachment->document;

        abort_if($document === null, 404);
        abort_unless(AssignmentScope::userCanEditDocument($document), 403);
        abort_unless(
            strtolower(pathinfo((string) $attachment->file_path, PATHINFO_EXTENSION)) === 'pdf',
            404,
        );

        return $document;
    }
}
