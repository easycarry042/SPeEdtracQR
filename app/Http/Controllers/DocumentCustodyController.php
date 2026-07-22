<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentCustodyEvent;
use App\Support\AssignmentScope;
use Illuminate\Http\Request;

/**
 * Physical custody trail. Scanning a folder's QR (or clicking "Take custody")
 * records that the authenticated staff member now physically holds the paper.
 * Purely additive — it never changes the document's status.
 */
class DocumentCustodyController extends Controller
{
    public function store(Request $request, Document $document)
    {
        $user = $request->user();

        // Anyone who may see the document may hold its folder — including
        // counter staff receiving a walk-in folder.
        abort_unless(
            AssignmentScope::userCanAccessDocument($document, $user) || $user->can('scan documents'),
            403,
        );

        $current = $document->currentCustody();
        if ($current && (int) $current->user_id === (int) $user->id) {
            return $this->respond($request, $document, 'You already hold this folder.');
        }

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        DocumentCustodyEvent::create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'note' => $validated['note'] ?? null,
        ]);

        activity()
            ->performedOn($document)
            ->causedBy($user)
            ->log('Took physical custody of the folder');

        $document->logSystemComment("Physical custody: folder now with {$user->name}");

        return $this->respond($request, $document, 'Custody recorded — the folder is now with you.');
    }

    private function respond(Request $request, Document $document, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('status', $message);
    }
}
