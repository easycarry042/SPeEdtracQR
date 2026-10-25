<?php

namespace App\Console\Commands;

use App\Enums\DocumentStatus;
use App\Mail\SlaBreachMail;
use App\Mail\SlaWarningMail;
use App\Models\Document;
use App\Notifications\DocumentEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Re-anchored for the manual tracking model: SLA is measured per status STAGE
 * (time since status_changed_at vs DocumentStatus::slaHours()), and the
 * warning/breach emails go to the document's ASSIGNED staff member — not a
 * department mailbox. One idempotent hourly sweep, deduped via the
 * sla_*_notified_at columns (reset on every stage change).
 *
 * Also checks for unaccepted requests sitting in Pending past the acceptance
 * window (2 hours) and auto-escalates them to secondary handlers or reclaims
 * them to the department open pool.
 */
class CheckDocumentSla extends Command
{
    protected $signature = 'documents:check-sla {--hours=2 : Acceptance window in hours before auto-reclaiming unaccepted requests}';

    protected $description = 'Email SLA warnings/breaches and auto-reclaim unaccepted pending requests';

    /** Fraction of the stage SLA at which a warning is sent. */
    private const float WARNING_RATIO = 0.75;

    public function handle(): int
    {
        $documents = Document::with('assignedTo')
            ->whereNotNull('assigned_to')
            ->whereNotNull('status_changed_at')
            ->get();

        $warned = 0;
        $breached = 0;

        foreach ($documents as $document) {
            $stage = $document->statusEnum();
            $sla = $stage->slaHours();

            // Terminal / no-budget stages never breach.
            if ($sla === null || $stage->isTerminal()) {
                continue;
            }

            $assignee = $document->assignedTo;
            if (! $assignee || ! $assignee->email) {
                continue;
            }

            $elapsed = $document->status_changed_at->diffInHours(now());

            if ($elapsed >= $sla && ! $document->sla_breach_notified_at) {
                Mail::to($assignee->email)->send(new SlaBreachMail($document));
                $document->forceFill([
                    'sla_breach_notified_at' => now(),
                    // Sticky lifetime marker for the staff SLA-rate KPI (set once).
                    'sla_breached_at' => $document->sla_breached_at ?? now(),
                ])->save();
                $breached++;
            } elseif ($elapsed >= $sla * self::WARNING_RATIO
                && ! $document->sla_warning_notified_at
                && ! $document->sla_breach_notified_at) {
                Mail::to($assignee->email)->send(new SlaWarningMail($document));
                $document->forceFill(['sla_warning_notified_at' => now()])->save();
                $warned++;
            }
        }

        // 2. Reactive Auto-Escalation: unaccepted requests past acceptance window (default 2 hours)
        $acceptanceHours = (int) ($this->option('hours') ?: 2);
        $unacceptedCutoff = now()->subHours($acceptanceHours);

        $stalled = Document::with(['assignedTo', 'requestType.handlers.user'])
            ->where('status', DocumentStatus::Pending->value)
            ->whereNotNull('assigned_to')
            ->whereNull('accepted_at')
            ->where(function ($q) use ($unacceptedCutoff) {
                $q->where('assigned_at', '<', $unacceptedCutoff)
                    ->orWhere(function ($sub) use ($unacceptedCutoff) {
                        $sub->whereNull('assigned_at')
                            ->where('status_changed_at', '<', $unacceptedCutoff);
                    })
                    ->orWhere(function ($sub) use ($unacceptedCutoff) {
                        $sub->whereNull('assigned_at')
                            ->whereNull('status_changed_at')
                            ->where('created_at', '<', $unacceptedCutoff);
                    });
            })
            ->get();

        $reassigned = 0;
        $reclaimed = 0;

        foreach ($stalled as $document) {
            $prevAssignee = $document->assignedTo;
            $prevAssigneeName = $prevAssignee?->name ?? 'Assigned staff';
            $departmentId = $document->department_id ?? $prevAssignee?->department_id;

            // Attempt to cycle to next active and available handler in request_type_handlers
            $nextHandler = null;
            if ($document->requestType) {
                $candidateHandlers = $document->requestType->handlers
                    ->map(fn ($h) => $h->user)
                    ->filter(fn ($u) => $u && $u->is_active && $u->is_available && $u->id !== $document->assigned_to);

                if ($candidateHandlers->isNotEmpty()) {
                    $candidateIds = $candidateHandlers->pluck('id')->all();
                    $workloads = Document::query()
                        ->whereIn('assigned_to', $candidateIds)
                        ->whereIn('status', DocumentStatus::activeValues())
                        ->groupBy('assigned_to')
                        ->selectRaw('assigned_to, count(*) as count')
                        ->pluck('count', 'assigned_to');

                    $nextHandler = $candidateHandlers->sortBy(fn ($u) => $workloads[$u->id] ?? 0)->first();
                }
            }

            if ($nextHandler) {
                $document->forceFill([
                    'assigned_to' => $nextHandler->id,
                    'assigned_by' => null,
                    'assigned_at' => now(),
                    'accepted_at' => null,
                ])->save();

                $document->activityLogs()->create([
                    'action' => "Auto-escalated: Reassigned to {$nextHandler->name} after {$acceptanceHours}h unaccepted by {$prevAssigneeName}",
                    'user_id' => null,
                ]);

                $nextHandler->notify(DocumentEvent::assigned($document, 'System (Auto-escalation)'));

                $triagers = DocumentEvent::departmentTriagers($departmentId);
                foreach ($triagers as $supervisor) {
                    $supervisor->notify(DocumentEvent::autoEscalated($document, $prevAssigneeName, $nextHandler->name, $acceptanceHours));
                }

                $reassigned++;
            } else {
                $document->forceFill([
                    'assigned_to' => null,
                    'assigned_by' => null,
                    'assigned_at' => null,
                    'accepted_at' => null,
                ])->save();

                $document->activityLogs()->create([
                    'action' => "Unattended assignment returned to department pool (unaccepted for {$acceptanceHours}+ hours by {$prevAssigneeName})",
                    'user_id' => null,
                ]);

                $triagers = DocumentEvent::departmentTriagers($departmentId);
                foreach ($triagers as $supervisor) {
                    $supervisor->notify(DocumentEvent::unattendedReturned($document, $prevAssigneeName, $acceptanceHours));
                }

                $reclaimed++;
            }
        }

        $this->info("SLA sweep complete: {$warned} warning(s), {$breached} breach(es). Auto-escalation: {$reassigned} reassigned, {$reclaimed} returned to pool.");

        return self::SUCCESS;
    }
}
