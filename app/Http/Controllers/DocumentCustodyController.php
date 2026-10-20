<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentCustodyEvent;
use App\Models\RequestStep;
use App\Models\User;
use App\Notifications\DocumentEvent;
use App\Support\AssignmentScope;
use App\Support\ScannedCode;
use Illuminate\Http\JsonResponse;
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

        // A soft copy is received by the person it was sent to (or their
        // office's supervisor) — never by someone at another office, which for
        // paper means the folder moved, but for a soft copy would just let a
        // stranger pull the request onto their desk.
        if ($document->isSoftCopy() && ! $document->canActOnCurrentStep($user)) {
            throw ValidationException::withMessages([
                'custody' => 'This request was sent to someone else. Only they, or their office\'s supervisor, can receive it.',
            ]);
        }

        $validated = $request->validate([
            // image / screen: the QR read from a saved picture, or snipped off the
            // screen — soft copies only (there is no paper to point a camera at).
            'capture_method' => ['required', 'in:scan,manual,image,screen'],
            'scanned_value' => ['required_if:capture_method,scan,image,screen', 'nullable', 'string', 'max:2000'],
            'override_reason' => ['required_if:capture_method,manual', 'nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'scanned_value.required_if' => 'Scan the folder\'s QR code to take custody.',
            'override_reason.required_if' => 'A reason is required to record custody without scanning.',
        ]);

        // A paper request is received by scanning the paper: a QR image proves
        // only that someone had the file, not that the folder arrived.
        if (in_array($validated['capture_method'], ['image', 'screen'], true) && ! $document->isSoftCopy()) {
            throw ValidationException::withMessages([
                'custody' => 'This request has a paper folder. Scan the QR on the folder with the camera.',
            ]);
        }

        $isScan = in_array($validated['capture_method'], ['scan', 'image', 'screen'], true);

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

        $message = $this->record($document, $user, $validated['capture_method'], $isScan ? null : $validated['override_reason'], $validated['note'] ?? null);

        return $this->respond($request, $message);
    }

    /**
     * Open what the header's Scan QR Code (or Look Up) just read — and, for an
     * internal request nobody at the holding office has received yet, receive
     * it with that same scan when the scanner is someone who may: one scan
     * pulls the request up and logs it as received, rather than a second scan
     * on the request's own Receive card. Answers with where to go next.
     *
     * A paper request is still received by camera only: a QR picture proves
     * someone had the file, not that the folder arrived.
     */
    public function receiveByScan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scanned_value' => ['required', 'string', 'max:2000'],
            'capture_method' => ['required', 'in:scan,image'],
        ]);

        $tracking = ScannedCode::trackingNumber($validated['scanned_value']);

        if ($tracking === null) {
            throw ValidationException::withMessages(['scanned_value' => ScannedCode::FOREIGN_CODE_MESSAGE]);
        }

        $document = Document::where('tracking_number', $tracking)->first();

        if (! $document || ! $document->isInternal()) {
            return response()->json(['url' => route('track.show', $tracking), 'received' => false]);
        }

        $url = route('requests.show', $document);
        $user = $request->user();

        if (! $document->canReceiveCurrentStep($user) || $document->currentStepHasCustody()) {
            return response()->json(['url' => $url, 'received' => false]);
        }

        if ($validated['capture_method'] === 'image' && ! $document->isSoftCopy()) {
            return response()->json(['url' => $url, 'received' => false]);
        }

        $message = $this->record($document, $user, $validated['capture_method']);

        // Shown on the request page the scanner is sent to.
        session()->flash('status', $message);

        return response()->json(['url' => $url, 'received' => true, 'message' => $message]);
    }

    /**
     * Write one custody event and everything that follows from it: the
     * activity line, the request's own feed, the endorsement chain, and the
     * filer's "received" bell. Returns the confirmation to show.
     */
    private function record(Document $document, User $user, string $method, ?string $overrideReason = null, ?string $note = null): string
    {
        $isScan = in_array($method, ['scan', 'image', 'screen'], true);

        // Whether this scan is the one that RECEIVES an internal request at an
        // office (the first since it got there) — the filer hears about that.
        $stepBefore = $document->isInternal() ? $document->currentRequestStep() : null;
        $heldBefore = $stepBefore !== null && $document->currentStepHasCustody();

        DocumentCustodyEvent::create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'capture_method' => $method,
            'override_reason' => $isScan ? null : $overrideReason,
            'note' => $note,
        ]);

        $how = match ($method) {
            'scan' => 'scanned',
            'image' => 'QR image',
            'screen' => 'QR on screen',
            default => "manual: {$overrideReason}",
        };

        activity()
            ->performedOn($document)
            ->causedBy($user)
            ->withProperties(array_filter([
                'capture_method' => $method,
                'override_reason' => $isScan ? null : $overrideReason,
            ]))
            ->log(match (true) {
                $document->isSoftCopy() => "Received the request ({$how})",
                $isScan => 'Took physical custody of the folder (scanned)',
                default => "Recorded custody manually (QR unreadable): {$overrideReason}",
            });

        $document->logSystemComment($document->isSoftCopy()
            ? "Received by {$user->name} ({$how})"
            : "Physical custody: folder now with {$user->name} ({$how})");

        $appended = $this->logDepartmentOntoChain($document, $user);

        $this->tellTheFilerItWasReceived($document, $user, $appended !== null || ($stepBefore !== null && ! $heldBefore));

        return match (true) {
            $document->isSoftCopy() => 'Received — you can now approve, return or deny this request.',
            $appended !== null => "Custody recorded — {$appended->department->name} added to the endorsement chain.",
            $document->isInternal() && ! $document->canActOnCurrentStep($user) => 'Received — the folder is logged as at your office. Your supervisor approves, returns or denies it.',
            default => 'Custody recorded — the folder is now with you.',
        };
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

        if ($document->statusEnum()->isTerminal()) {
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

    /**
     * The office that filed an internal request hears when the next office
     * picks it up: "received by Paolo Mendoza at General Services Office". Only
     * on the scan that receives it — not on every later hand-off inside the
     * office — and never to the person who scanned it themselves.
     */
    private function tellTheFilerItWasReceived(Document $document, User $user, bool $receivedNow): void
    {
        $filer = $document->creator;

        if (! $receivedNow || ! $document->isInternal() || ! $filer || (int) $filer->id === (int) $user->id) {
            return;
        }

        $office = $document->currentRequestStep()?->department?->name ?? $user->department?->name ?? 'the next office';

        $filer->notify(DocumentEvent::internalReceived($document, $user->name, $office));
    }

    private function respond(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        // Taken from the Internal page's modal: go back there, modal reopened.
        if ($request->input('from') === 'internal' && $request->route('document')) {
            return to_route('requests.index', ['open' => $request->route('document')->id])->with('status', $message);
        }

        return back()->with('status', $message);
    }
}
