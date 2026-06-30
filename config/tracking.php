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
        ],
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

];
