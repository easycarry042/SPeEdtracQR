<?php

use App\Enums\DocumentStatus;

return [

    /*
    |--------------------------------------------------------------------------
    | Citizen email notifications
    |--------------------------------------------------------------------------
    |
    | Controls the StatusUpdated emails sent to citizens. A send happens only
    | when ALL of these are true:
    |   - 'enabled' (global kill switch) is true
    |   - the target stage's per-stage flag below is true
    |   - the document has a citizen_email
    |   - the document's notify_citizen column is true (per-ticket opt-out)
    |
    | Toggling a stage on/off here is a one-line edit and avoids spamming the
    | citizen on every intermediate move.
    |
    */

    'notify_citizen' => [
        'enabled' => env('TRACKING_NOTIFY_CITIZEN', true),

        'stages' => [
            DocumentStatus::Pending->value => false,
            DocumentStatus::InProgress->value => true,
            DocumentStatus::InReview->value => false,
            DocumentStatus::Approved->value => true,
            DocumentStatus::Completed->value => true,
            DocumentStatus::Returned->value => true,
            // "Action needed" email when a hold is blocked on the citizen.
            DocumentStatus::OnHold->value => true,
        ],

        /*
        | Booking lifecycle emails (approved / rescheduled / cancelled). Gated by
        | 'enabled' above plus the document's citizen_email + notify_citizen flag,
        | exactly like status emails. Bookings carry their own statuses, so they
        | sit outside the DocumentStatus 'stages' map.
        */
        'bookings' => env('TRACKING_NOTIFY_BOOKINGS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notify assigned staff
    |--------------------------------------------------------------------------
    |
    | Send an AssignmentNotice email to a staff member when an admin assigns
    | them a document.
    |
    */

    'notify_staff_on_assignment' => env('TRACKING_NOTIFY_STAFF', true),

    /*
    |--------------------------------------------------------------------------
    | Hold backstop
    |--------------------------------------------------------------------------
    |
    | Putting a document On Hold pauses its SLA clock on purpose — a blocked
    | assignee should not be charged for waiting. The cost is that a held
    | document drops out of isOverdue(), documents:check-sla and the at-risk
    | dashboard, so a forgotten hold could sit unmeasured forever.
    |
    | The daily `documents:check-holds` sweep closes that hole: it chases holds
    | that have run past the date staff themselves set ('hold_until'), or — for
    | open-ended holds — past 'stale_after_days' from when the hold began. The
    | assignee is emailed and the Supervisor of the handling department is
    | copied, so a stall surfaces to someone other than the person who parked it.
    |
    */

    'holds' => [
        'enabled' => env('TRACKING_HOLD_SWEEP', true),

        /** Days an open-ended hold (no hold_until) may run before it is a stall. */
        'stale_after_days' => (int) env('TRACKING_HOLD_STALE_DAYS', 14),

        /** Re-nag cadence while a hold stays overdue, so one ignored email is not the end of it. */
        'remind_every_days' => (int) env('TRACKING_HOLD_REMIND_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Overdue loans
    |--------------------------------------------------------------------------
    |
    | A borrowing only closes when staff record the return on the Resources
    | page, so late items stay counted as out instead of rejoining the stock on
    | their return date. The hourly `inventory:check-overdue` sweep emails the
    | borrower a reminder and bells the handling staff and their supervisors,
    | then repeats every 'remind_every_days' while the loan stays overdue.
    |
    */

    'loans' => [
        'enabled' => env('TRACKING_LOAN_SWEEP', true),

        /** Re-remind cadence while borrowed items stay overdue. */
        'remind_every_days' => (int) env('TRACKING_LOAN_REMIND_DAYS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Public internal-request tracker
    |--------------------------------------------------------------------------
    |
    | Anyone holding an internal request's QR or number can see which office
    | has it (route internal.track, no sign-in). Whether it also names the
    | person handling it is this switch: the client asked for names, while the
    | citizen tracker keeps staff anonymous for legal reasons. Set
    | TRACKING_PUBLIC_INTERNAL_NAMES=false to show offices only.
    |
    */

    'public_internal_tracker' => [
        'show_names' => env('TRACKING_PUBLIC_INTERNAL_NAMES', true),
    ],

];
