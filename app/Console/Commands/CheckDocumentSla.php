<?php

namespace App\Console\Commands;

use App\Mail\SlaBreachMail;
use App\Mail\SlaWarningMail;
use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckDocumentSla extends Command
{
    protected $signature = 'documents:check-sla';

    protected $description = 'Email SLA warnings/breaches for documents sitting too long at a department';

    /** Fraction of the SLA at which a warning is sent. */
    private const WARNING_RATIO = 0.75;

    public function handle(): int
    {
        $documents = Document::with('currentDepartment')
            ->where('status', 'in_transit')
            ->whereNotNull('current_department_id')
            ->get();

        $warned = 0;
        $breached = 0;

        foreach ($documents as $document) {
            $department = $document->currentDepartment;
            if (! $department || ! $department->sla_hours || ! $department->email) {
                continue;
            }

            $lastIn = $document->scans()
                ->where('action', 'in')
                ->where('department_id', $document->current_department_id)
                ->latest('scanned_at')
                ->first();
            if (! $lastIn) {
                continue;
            }

            $elapsed = $lastIn->scanned_at->diffInHours(now());
            $sla = $department->sla_hours;

            if ($elapsed >= $sla && ! $document->sla_breach_notified_at) {
                Mail::to($department->email)->send(new SlaBreachMail($document, $department));
                $document->forceFill(['sla_breach_notified_at' => now()])->save();
                $breached++;
            } elseif ($elapsed >= $sla * self::WARNING_RATIO
                && ! $document->sla_warning_notified_at
                && ! $document->sla_breach_notified_at) {
                Mail::to($department->email)->send(new SlaWarningMail($document, $department));
                $document->forceFill(['sla_warning_notified_at' => now()])->save();
                $warned++;
            }
        }

        $this->info("SLA sweep complete: {$warned} warning(s), {$breached} breach(es).");

        return self::SUCCESS;
    }
}
