<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Support\AssignmentScope;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentRequirementController extends Controller
{
    /**
     * Toggle a requirement's verified state — staff confirm they've seen the
     * original. Only the assignee/admin who can advance the document may verify.
     */
    public function toggle(Document $document, DocumentRequirement $requirement)
    {
        abort_unless($requirement->document_id === $document->id, 404);
        abort_unless($document->canBeAdvancedBy(auth()->user()), 403, 'Only the assigned staff member or an admin can verify requirements.');

        if ($requirement->isVerified()) {
            $requirement->update(['verified_at' => null, 'verified_by' => null]);
            $message = "Marked \"{$requirement->label}\" as not yet verified.";
        } else {
            $requirement->update(['verified_at' => now(), 'verified_by' => auth()->id()]);
            $message = "Verified \"{$requirement->label}\".";
        }

        activity()->performedOn($document)->log($message);

        return back()->with('status', $message);
    }

    /**
     * Stream a citizen-uploaded requirement file from the private disk to an
     * authorized staff member only (never anonymously fetchable).
     */
    public function file(Document $document, DocumentRequirement $requirement): StreamedResponse
    {
        abort_unless($requirement->document_id === $document->id, 404);
        abort_unless(AssignmentScope::userCanAccessDocument($document), 403);
        abort_if($requirement->uploaded_file_path === null, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($requirement->uploaded_file_path), 404);

        return $disk->response($requirement->uploaded_file_path);
    }
}
