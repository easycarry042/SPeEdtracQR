<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Document;
use App\Notifications\DocumentEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class DocumentNudgeController extends Controller
{
    /**
     * Flag an overdue document for the responsible party's attention. This is a
     * nudge, not a reassignment: the super admin never claims or reroutes the
     * work, they only notify whoever should act on it.
     *
     * Recipients: the current assignee if there is one, otherwise the
     * supervisors of the office handling it (DocumentEvent::departmentTriagers,
     * minus the actor). It used to be every supervisor in the city, so one
     * reminder about a GSO request rang ten offices' bells.
     */
    public function store(Request $request, Document $document): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:280'],
        ]);

        $actor = $request->user();
        $note = $validated['note'] ?? null;

        $recipients = $document->assignedTo
            ? collect([$document->assignedTo])
            : DocumentEvent::departmentTriagers($document->department_id, $actor->id);

        if ($recipients->isEmpty()) {
            return back()->with('status', 'No one is available to notify for this document yet.');
        }

        Notification::send(
            $recipients,
            DocumentEvent::nudge($document, $actor->name, $note),
        );

        activity()
            ->performedOn($document)
            ->causedBy($actor)
            ->log('Nudged the responsible staff about an overdue document');

        $who = $document->assignedTo?->name
            ?? ($document->department ? "the {$document->department->name} supervisors" : 'the supervisors');

        return back()->with('status', "Notified {$who} about {$document->tracking_number}.");
    }
}
