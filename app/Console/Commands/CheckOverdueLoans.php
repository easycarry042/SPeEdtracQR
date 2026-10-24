<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\DocumentEvent;
use App\Support\Lending;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Chases borrowed items that are past their return date.
 *
 * A loan only closes when someone records the return, so a late borrower
 * keeps the items "out" — overdue — instead of them quietly rejoining the
 * stock. This sweep makes sure that is noticed: the borrower is emailed a
 * reminder, and the staff member handling the request (plus their office's
 * supervisors) get a bell. It repeats every tracking.loans.remind_every_days
 * while the loan stays overdue.
 */
class CheckOverdueLoans extends Command
{
    protected $signature = 'inventory:check-overdue';

    protected $description = 'Remind borrowers, and tell the office, about borrowed items past their return date';

    public function handle(): int
    {
        if (! config('tracking.loans.enabled', true)) {
            $this->info('Overdue loan sweep disabled (tracking.loans.enabled).');

            return self::SUCCESS;
        }

        $remindEvery = max(1, (int) config('tracking.loans.remind_every_days', 3));

        $overdue = Booking::query()
            ->open()
            ->whereNotNull('released_at')
            ->where('ends_at', '<', now())
            ->with(['resource', 'document.assignedTo'])
            ->get();

        $chased = 0;

        foreach ($overdue as $booking) {
            $last = $booking->overdue_reminded_at;

            if ($last && $last->diffInDays(now()) < $remindEvery) {
                continue;
            }

            Lending::remind($booking);

            $this->staffFor($booking)->each(fn (User $user) => $user->notify(DocumentEvent::loanOverdue($booking)));

            $chased++;
        }

        $this->info("Overdue loan sweep complete: {$overdue->count()} overdue, {$chased} chased.");

        return self::SUCCESS;
    }

    /**
     * The staff member handling the request, and the supervisors of its office.
     *
     * @return Collection<int, User>
     */
    private function staffFor(Booking $booking): Collection
    {
        $document = $booking->document;
        $people = collect([$document?->assignedTo])->filter();

        if ($document?->department_id) {
            $people = $people->merge(DocumentEvent::departmentSupervisors($document->department_id));
        }

        return $people
            ->filter(fn (User $user): bool => (bool) $user->is_active)
            ->unique('id')
            ->values();
    }
}
