<?php

namespace App\Console\Commands;

use App\Enums\DocumentStatus;
use App\Mail\HoldOverdueMail;
use App\Models\Document;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

/**
 * The backstop for paused SLA clocks.
 *
 * Putting a document On Hold pauses its SLA deliberately (DocumentStatus::
 * slaHours() returns null for On Hold, documents:check-sla skips held rows, and
 * isOverdue() reports false). That is correct — a blocked assignee should not be
 * charged for waiting — but it means a held document answers to nothing. This
 * daily sweep gives every hold a second deadline and chases it:
 *
 *   - hold_until set  → overdue the day after the date staff themselves chose;
 *   - open-ended      → overdue after tracking.holds.stale_after_days.
 *
 * The assignee is emailed and the handling department's Supervisor is copied, so
 * a stall becomes visible to someone other than whoever parked it. Re-nags at
 * tracking.holds.remind_every_days while the hold stays overdue.
 */
class CheckDocumentHolds extends Command
{
    protected $signature = 'documents:check-holds';

    protected $description = 'Chase documents parked On Hold past their hold date, whose SLA clock is paused';

    public function handle(): int
    {
        if (! config('tracking.holds.enabled', true)) {
            $this->info('Hold sweep disabled (tracking.holds.enabled).');

            return self::SUCCESS;
        }

        $documents = Document::with(['assignedTo', 'department'])
            ->where('status', DocumentStatus::OnHold->value)
            ->get();

        $remindEvery = max(1, (int) config('tracking.holds.remind_every_days', 7));

        $chased = 0;
        $stalled = 0;

        foreach ($documents as $document) {
            if (! $document->isHoldOverdue()) {
                continue;
            }

            $stalled++;

            // Already chased recently? Wait out the re-nag window.
            $lastSent = $document->hold_reminder_sent_at;
            if ($lastSent && $lastSent->diffInDays(now()) < $remindEvery) {
                continue;
            }

            $recipients = $this->recipientsFor($document);

            if ($recipients['assignee'] === null && $recipients['supervisors']->isEmpty()) {
                // Nobody to tell — still log it so the audit trail shows the stall
                // was detected and why it went unreported.
                activity()
                    ->performedOn($document)
                    ->log("Hold overdue by {$document->holdOverdueDays()} day(s) — no assignee or supervisor mailbox to notify");

                continue;
            }

            if ($recipients['assignee'] !== null) {
                Mail::to($recipients['assignee'])->send(new HoldOverdueMail($document));
            }

            foreach ($recipients['supervisors'] as $supervisorEmail) {
                Mail::to($supervisorEmail)->send(new HoldOverdueMail($document, isEscalation: true));
            }

            $document->forceFill(['hold_reminder_sent_at' => now()])->save();

            activity()
                ->performedOn($document)
                ->withProperties([
                    'days_overdue' => $document->holdOverdueDays(),
                    'blocked_by' => $document->blocked_by,
                    'escalated_to' => $recipients['supervisors']->values()->all(),
                ])
                ->log("Hold overdue by {$document->holdOverdueDays()} day(s) — chased assignee and supervisor");

            $chased++;
        }

        $this->info("Hold sweep complete: {$stalled} stalled hold(s), {$chased} chased.");

        return self::SUCCESS;
    }

    /**
     * Who hears about a stalled hold: the assignee, plus every Supervisor of the
     * handling department (org-wide Supervisors have no department_id and are
     * included, since they oversee all offices).
     *
     * @return array{assignee: ?string, supervisors: Collection<int, string>}
     */
    private function recipientsFor(Document $document): array
    {
        $assignee = $document->assignedTo?->email;

        // Spatie's role() scope throws RoleDoesNotExist on an unseeded database.
        // A crashed sweep is the exact silent failure this command exists to
        // prevent, so fall back to "no escalation" rather than blowing up.
        if (! Role::where('name', 'Supervisor')->exists()) {
            return ['assignee' => $assignee, 'supervisors' => collect()];
        }

        $supervisors = User::role('Supervisor')
            ->when($document->department_id, fn ($query) => $query->where(function ($scoped) use ($document) {
                $scoped->where('department_id', $document->department_id)
                    ->orWhereNull('department_id');
            }))
            ->where('is_active', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->reject(fn (string $email): bool => $email === $assignee)
            ->unique();

        return ['assignee' => $assignee, 'supervisors' => $supervisors];
    }
}
