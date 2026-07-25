<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\RequestStep;
use App\Notifications\DocumentEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Hop actions on an internal request's endorsement chain. Only a supervisor of
 * the department holding the current hop may act, and every action re-confirms
 * their password. Approving affixes a frozen copy of their registered
 * e-signature and advances the chain; deny/return halt it.
 */
class RequestStepController extends Controller
{
    public function approve(Request $request, Document $document)
    {
        $step = $this->authorizeAction($request, $document);
        $user = $request->user();

        $this->validateAction($request, remarksRequired: false);
        $this->confirmPassword($request);

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

        $next = $document->requestSteps()
            ->where('status', RequestStep::STATUS_PENDING)
            ->orderBy('step_order')->orderBy('id')
            ->first();

        if ($next) {
            $next->update(['status' => RequestStep::STATUS_CURRENT, 'started_at' => now()]);

            if ($document->statusEnum() === DocumentStatus::Pending) {
                $document->applyStatus(DocumentStatus::InProgress);
                $document->save();
            }

            Notification::send(
                DocumentEvent::departmentSupervisors($next->department_id, $user->id),
                DocumentEvent::internalHopArrived($document, $next->action),
            );
        } else {
            // Chain finished: the request has passed every office.
            $document->applyStatus(DocumentStatus::Completed);
            $document->save();
        }

        $this->recordOutcome($document, $step, sprintf(
            '%s approved at %s by %s%s',
            $step->action,
            $step->department->name,
            $user->name,
            $next ? " — now with {$next->department->name}" : ' — chain complete',
        ));

        return $this->respond($request, $document, $next
            ? "Approved — the request moves on to {$next->department->name}."
            : 'Approved — the chain is complete.');
    }

    public function deny(Request $request, Document $document)
    {
        $step = $this->authorizeAction($request, $document);
        $user = $request->user();

        $this->validateAction($request, remarksRequired: true);
        $this->confirmPassword($request);

        $step->update([
            'status' => RequestStep::STATUS_DENIED,
            'acted_by' => $user->id,
            'acted_at' => now(),
            'remarks' => $request->input('remarks'),
        ]);

        $document->applyStatus(DocumentStatus::Denied);
        $document->save();

        $this->recordOutcome($document, $step, sprintf(
            'Denied at %s by %s: %s',
            $step->department->name,
            $user->name,
            $request->input('remarks'),
        ));

        return $this->respond($request, $document, 'Request denied. The filing office has been notified.');
    }

    public function returnToRequester(Request $request, Document $document)
    {
        $step = $this->authorizeAction($request, $document);
        $user = $request->user();

        $this->validateAction($request, remarksRequired: true);
        $this->confirmPassword($request);

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

        return $this->respond($request, $document, 'Request returned to the filing office for revision.');
    }

    /** Serve a step's frozen signature to authenticated staff (audit view). */
    public function signature(RequestStep $requestStep)
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
            'password' => 'required|string',
            'remarks' => [$remarksRequired ? 'required' : 'nullable', 'string', 'max:500'],
        ], [
            'remarks.required' => 'Explain the decision so the filing office knows what to fix.',
        ]);
    }

    /** Every hop decision re-confirms identity — this is what makes the e-signature credible. */
    private function confirmPassword(Request $request): void
    {
        if (! Hash::check($request->input('password'), $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'The password is incorrect.']);
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

    private function respond(Request $request, Document $document, string $message)
    {
        return redirect()->route('requests.show', $document)->with('status', $message);
    }
}
