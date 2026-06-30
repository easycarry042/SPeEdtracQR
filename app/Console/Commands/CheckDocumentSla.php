<?php

namespace App\Console\Commands;

use App\Enums\DocumentStatus;
use App\Mail\SlaBreachMail;
use App\Mail\SlaWarningMail;
use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Re-anchored for the manual tracking model: SLA is measured per status STAGE
 * (time since status_changed_at vs DocumentStatus::slaHours()), and the
 * warning/breach emails go to the document's ASSIGNED staff member — not a
 * department mailbox. One idempotent hourly sweep, deduped via the
 * sla_*_notified_at columns (reset on every stage change).
 */
class CheckDocumentSla extends Command
{
    protected $signature = 'documents:check-sla';

    protected $description = 'Email SLA warnings/breaches for documents sitting too long in their current status stage';

    /** Fraction of the stage SLA at which a warning is sent. */
    private const WARNING_RATIO = 0.75;

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

        $this->info("SLA sweep complete: {$warned} warning(s), {$breached} breach(es).");

        return self::SUCCESS;
    }
}
