<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\RequestStep;
use App\Notifications\DocumentEvent;
use App\Support\ScannedCode;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Hop actions on an internal request's endorsement chain. Only a supervisor of
 * the department holding the current hop may act, and every action is confirmed
 * by scanning the request's own QR — proof the folder is on the desk. Approving
 * affixes a frozen copy of their registered e-signature and advances the chain;
 * deny/return halt it.
 */
class RequestStepController extends Controller
{
    public function approve(Request $request, Document $document)
    {
        $step = $this->authorizeAction($request, $document);
        $user = $request->user();

        $this->validateAction($request, remarksRequired: false);

        // Where next? A hop queued at filing already answers that; otherwise the
        // office holding the paper chooses, which is how the chain grows past
        // the first department. "Nowhere" means the work is done — that is the
        // Done action, not an empty approval.
        $queued = $document->requestSteps()
            ->where('status', RequestStep::STATUS_PENDING)
            ->orderBy('step_order')->orderBy('id')
            ->first();

        $request->validate([
            'next_department_id' => [
                Rule::requiredIf(fn (): bool => $queued === null),
                'nullable',
                Rule::exists('departments', 'id')->where('is_active', true),
                Rule::notIn([(string) $step->department_id]),
            ],
        ], [
            'next_department_id.required' => 'Choose the office this request goes to next — or use Done if your office is the last to handle it.',
            'next_department_id.not_in' => 'Pick a different office: the request is already with yours.',
        ]);

        $this->confirmScannedFolder($request, $document);

        if (! $user->signature_path || ! Storage::disk('local')->exists($user->signature_path)) {
            throw ValidationException::withMessages([
                'signature' => 'Register your e-signature on your Profile page before approving requests.',
            ]);
        }

        // Freeze the signature as of this approval — re-registering later must
        // never rewrite what was affixed here.
        $signatureCopy = "request-signatures/{$document->tracking_number}-step-{$step->id}.png";
        Storage::disk('local')->put($signatureCopy, Storage::disk('local')->get($user->signature_path));

        $step->update([
            'status' => RequestStep::STATUS_APPROVED,
            'acted_by' => $user->id,
            'acted_at' => now(),
            'remarks' => $request->input('remarks'),
            'signature_path' => $signatureCopy,
        ]);

        if ($document->statusEnum() === DocumentStatus::Pending) {
            $document->applyStatus(DocumentStatus::InProgress);
            $document->save();
        }

        // A hop queued at filing takes precedence; past that, the chain extends
        // to whichever office this one chose.
        $next = $queued;

        if ($next) {
            $next->update(['status' => RequestStep::STATUS_CURRENT, 'started_at' => now()]);
        } else {
            $next = $document->requestSteps()->create([
                'step_order' => (int) $document->requestSteps()->max('step_order') + 1,
                'department_id' => (int) $request->input('next_department_id'),
                'action' => 'Review and action',
                'status' => RequestStep::STATUS_CURRENT,
                'started_at' => now(),
            ]);

            $document->unsetRelation('requestSteps');
        }

        $next->loadMissing('department');

        Notification::send(
            DocumentEvent::departmentSupervisors($next->department_id, $user->id),
            DocumentEvent::internalHopArrived($document, $next->action),
        );

        // Approving never completes a request, however long the chain gets —
        // only "Mark as done" (see complete()) finishes one.
        $this->recordOutcome($document, $step, sprintf(
            '%s approved at %s by %s — forwarded to %s',
            $step->action,
            $step->department->name,
            $user->name,
            $next->department->name,
        ));

        return $this->respond($document, "Approved — the request moves on to {$next->department->name}.");
    }

    /**
     * The office holding the request declares the work finished. This is the
     * only path to Completed: the chain otherwise keeps growing as offices scan
     * the folder, because nothing else knows which hop is the last one.
     */
    public function complete(Request $request, Document $document)
    {
        $step = $this->authorizeAction($request, $document);
        $user = $request->user();

        $this->validateAction($request, remarksRequired: false);
        $this->confirmScannedFolder($request, $document);

        $step->update([
            'status' => RequestStep::STATUS_APPROVED,
            'acted_by' => $user->id,
            'acted_at' => now(),
            'remarks' => $request->input('remarks'),
        ]);

        // Anything still queued behind this hop is moot once the work is done.
        $document->requestSteps()
            ->whereIn('status', [RequestStep::STATUS_PENDING, RequestStep::STATUS_CURRENT])
            ->update(['status' => RequestStep::STATUS_SKIPPED]);

        $document->applyStatus(DocumentStatus::Completed);
        $document->decided_by = $user->id;
        $document->decided_at = now();
        $document->save();

        $this->recordOutcome($document, $step, sprintf(
            'Marked done at %s by %s — the request is complete%s',
            $step->department->name,
            $user->name,
            $request->filled('remarks') ? ': '.$request->input('remarks') : '.',
        ));

        return $this->respond($document, 'Marked as done — the request is complete.');
    }

    public function deny(Request $request, Document $document)
    {
        $step = $this->authorizeAction($request, $document);
        $user = $request->user();

        $this->validateAction($request, remarksRequired: true);
        $this->confirmScannedFolder($request, $document);

        $step->update([
            'status' => RequestStep::STATUS_DENIED,
            'acted_by' => $user->id,
            'acted_at' => now(),
            'remarks' => $request->input('remarks'),
        ]);

        $document->applyStatus(DocumentStatus::Denied);
        // Attribute the decision so the tracking page can name who denied it.
        $document->decided_by = $user->id;
        $document->decided_at = now();
        $document->save();

        $this->recordOutcome($document, $step, sprintf(
            'Denied at %s by %s: %s',
            $step->department->name,
            $user->name,
            $request->input('remarks'),
        ));

        return $this->respond($document, 'Request denied. The filing office has been notified.');
    }

    public function returnToRequester(Request $request, Document $document)
    {
        $step = $this->authorizeAction($request, $document);
        $user = $request->user();

        $this->validateAction($request, remarksRequired: true);
        $this->confirmScannedFolder($request, $document);

        $step->update([
            'status' => RequestStep::STATUS_RETURNED,
            'acted_by' => $user->id,
            'acted_at' => now(),
            'remarks' => $request->input('remarks'),
        ]);

        $document->applyStatus(DocumentStatus::Returned);
        $document->save();

        $this->recordOutcome($document, $step, sprintf(
            'Returned for revision at %s by %s: %s',
            $step->department->name,
            $user->name,
            $request->input('remarks'),
        ));

        return $this->respond($document, 'Request returned to the filing office for revision.');
    }

    /** Serve a step's frozen signature to authenticated staff (audit view). */
    public function signature(RequestStep $requestStep): ResponseFactory|Response
    {
        abort_unless(auth()->check(), 403);
        abort_unless($requestStep->signature_path && Storage::disk('local')->exists($requestStep->signature_path), 404);

        return response(Storage::disk('local')->get($requestStep->signature_path), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Gate + resolve the current hop. 403 unless this user holds it, and the
     * endorsement stays locked until the office has physically taken custody of
     * the folder for this hop — the QR scan that proves the paper is in hand
     * before any digital sign-off (approve, deny, or return) can be recorded.
     */
    private function authorizeAction(Request $request, Document $document): RequestStep
    {
        abort_unless($document->canActOnCurrentStep($request->user()), 403);

        if (! $document->currentStepHasCustody()) {
            throw ValidationException::withMessages([
                'custody' => 'Scan the folder\'s QR to take custody before acting on this request — the paper must be physically in your office first.',
            ]);
        }

        return $document->currentRequestStep();
    }

    private function validateAction(Request $request, bool $remarksRequired): void
    {
        $request->validate([
            // Either the folder's QR is scanned, or a written reason is given for
            // why it could not be. One of the two is always required: a decision
            // must never be recordable without SOME account of the paper.
            'document_scan' => ['required_without:scan_override_reason', 'nullable', 'string', 'max:2000'],
            'scan_override_reason' => ['required_without:document_scan', 'nullable', 'string', 'min:10', 'max:500'],
            'remarks' => [$remarksRequired ? 'required' : 'nullable', 'string', 'max:500'],
        ], [
            'document_scan.required_without' => "Scan this request's QR code to confirm the decision, or record why you could not.",
            'scan_override_reason.required_without' => 'Give a reason for confirming without a scan.',
            'scan_override_reason.min' => 'Explain briefly why the QR could not be scanned.',
            'remarks.required' => 'Explain the decision so the filing office knows what to fix.',
        ]);
    }

    /**
     * Every hop decision is confirmed by scanning the request's own QR — the
     * sticker on the folder in front of the signer. Only codes this system
     * issued count, and the code must be THIS request's, so a decision cannot
     * be recorded against a folder that is not in the room.
     *
     * Note this proves the paper is present, not who is signing; the signer is
     * the authenticated supervisor of the office holding the hop.
     *
     * A written override is accepted when the QR genuinely cannot be scanned —
     * a torn sticker, or (much more commonly) a machine with no working camera.
     * Without this escape hatch a broken webcam halts the office entirely, which
     * is a worse failure than a logged manual confirmation. The override is
     * recorded on the activity log and mirrored into the request's own feed, so
     * it is visible rather than silent.
     */
    private function confirmScannedFolder(Request $request, Document $document): void
    {
        $override = trim((string) $request->input('scan_override_reason'));

        // The hidden scan field always posts, so an unscanned folder arrives as
        // an empty string rather than null — compare on the trimmed value.
        $scan = trim((string) $request->input('document_scan'));

        if ($override !== '' && $scan === '') {
            activity()
                ->performedOn($document)
                ->causedBy(auth()->user())
                ->withProperties(['reason' => $override, 'tracking_number' => $document->tracking_number])
                ->log('Decision confirmed WITHOUT a QR scan — reason: '.$override);

            $document->logSystemComment(
                auth()->user()->name.' confirmed a decision without scanning the QR. Reason: '.$override
            );

            return;
        }

        $scanned = ScannedCode::trackingNumber($scan);

        if ($scanned === null) {
            throw ValidationException::withMessages([
                'document_scan' => 'Scan the QR on this request to confirm the decision. '.ScannedCode::FOREIGN_CODE_MESSAGE,
            ]);
        }

        if ($scanned !== strtoupper($document->tracking_number)) {
            throw ValidationException::withMessages([
                'document_scan' => "That QR belongs to a different request ({$scanned}). Scan the sticker on this folder.",
            ]);
        }
    }

    private function recordOutcome(Document $document, RequestStep $step, string $summary): void
    {
        activity()
            ->performedOn($document)
            ->causedBy(auth()->user())
            ->withProperties(['step_id' => $step->id, 'department' => $step->department->name, 'action' => $step->action])
            ->log($summary);

        $document->logSystemComment($summary);

        if ($document->created_by && (int) $document->created_by !== (int) auth()->id()) {
            $document->creator?->notify(DocumentEvent::internalOutcome($document, $summary));
        }
    }

    private function respond(Document $document, string $message)
    {
        return to_route('requests.show', $document)->with('status', $message);
    }
}
