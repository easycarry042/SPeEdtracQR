<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentCustodyEvent;
use App\Models\RequestStep;
use App\Models\User;
use App\Support\AssignmentScope;
use App\Support\ScannedCode;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Physical custody trail. Scanning a folder's QR (or clicking "Take custody")
 * records that the authenticated staff member now physically holds the paper.
 * It never changes the document's status — but for internal requests it does
 * grow the endorsement chain, logging the office that just received the folder.
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
            return $this->respond($request, 'You already hold this folder.');
        }

        $validated = $request->validate([
            'capture_method' => ['required', 'in:scan,manual'],
            'scanned_value' => ['required_if:capture_method,scan', 'nullable', 'string', 'max:2000'],
            'override_reason' => ['required_if:capture_method,manual', 'nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'scanned_value.required_if' => 'Scan the folder\'s QR code to take custody.',
            'override_reason.required_if' => 'A reason is required to record custody without scanning.',
        ]);

        $isScan = $validated['capture_method'] === 'scan';

        // A scan must decode to THIS document's tracking number, and the code must
        // be one WE issued (our own tracking/verify URL, or a bare tracking
        // number). A foreign QR that merely mentions a tracking number is refused
        // — the browser applies the same rule, this is the authority.
        if ($isScan) {
            $scanned = ScannedCode::trackingNumber((string) $validated['scanned_value']);

            if ($scanned !== strtoupper($document->tracking_number)) {
                throw ValidationException::withMessages([
                    'custody' => $scanned
                        ? "That QR belongs to a different folder ({$scanned}). Scan this document's folder."
                        : ScannedCode::FOREIGN_CODE_MESSAGE,
                ]);
            }
        }

        DocumentCustodyEvent::create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'capture_method' => $validated['capture_method'],
            'override_reason' => $isScan ? null : $validated['override_reason'],
            'note' => $validated['note'] ?? null,
        ]);

        activity()
            ->performedOn($document)
            ->causedBy($user)
            ->withProperties(array_filter([
                'capture_method' => $validated['capture_method'],
                'override_reason' => $isScan ? null : $validated['override_reason'],
            ]))
            ->log($isScan
                ? 'Took physical custody of the folder (scanned)'
                : "Recorded custody manually (QR unreadable): {$validated['override_reason']}");

        $document->logSystemComment($isScan
            ? "Physical custody: folder now with {$user->name} (scanned)"
            : "Physical custody: folder now with {$user->name} (manual: {$validated['override_reason']})");

        $appended = $this->logDepartmentOntoChain($document, $user);

        return $this->respond($request, $appended
            ? "Custody recorded — {$appended->department->name} added to the endorsement chain."
            : 'Custody recorded — the folder is now with you.');
    }

    /**
     * Growing the chain from scans: whichever office takes custody of an
     * internal request is logged as the hop now holding it. Nothing is appended
     * when that office already holds it, when the request is finished, or for
     * citizen tickets (which have no endorsement chain).
     */
    private function logDepartmentOntoChain(Document $document, User $user): ?RequestStep
    {
        if (! $document->isInternal() || $user->department_id === null) {
            return null;
        }

        if (in_array($document->statusEnum(), [DocumentStatus::Completed, DocumentStatus::Denied], true)) {
            return null;
        }

        $current = $document->currentRequestStep();
        if ($current && (int) $current->department_id === (int) $user->department_id) {
            return null;
        }

        $step = $document->appendEndorsementStep($user->department_id);

        activity()->performedOn($document)->causedBy($user)
            ->log("Endorsement chain: {$step->department->name} received the request");

        return $step;
    }

    private function respond(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('status', $message);
    }
}
