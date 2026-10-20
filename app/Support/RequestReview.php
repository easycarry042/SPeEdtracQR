<?php

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Http\Controllers\CommentController;
use App\Models\Booking;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\DocumentRequirement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * Shapes a Document into the flat payload the Review modal needs (supervisor and
 * staff share the same modal markup). Attachments are exposed as authorized URLs
 * with their extension so the modal can show a thumbnail or a file chip.
 *
 * The status log and the citizen conversation used to be assembled separately by
 * the Look Up desk, which is why the two modals could do different things to the
 * same request. The desk is gone and its panels moved into the Review modal, so
 * everything that modal needs is built here — use collection() for a queue, not
 * forModal() in a loop, or the activity log costs a query per row.
 */
class RequestReview
{
    /**
     * Staff who can be assigned a request. Queried by role name via a join so it
     * never throws if the Spatie permission/role rows happen to be missing
     * (unlike User::permission()/User::role(), which assert existence).
     *
     * @return Collection<int, User>
     */
    public static function assignableStaff(?int $departmentId = null): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'staff'))
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->orderBy('name')
            ->get(['id', 'name', 'department_id']);
    }

    /**
     * A whole queue, shaped for the Review modal.
     *
     * The status log is fetched for every row in one query — the desk learned
     * this the hard way; per-row it is an activity query per request on a page
     * that lists ten of them.
     *
     * @param  Collection<int, Document>|\Illuminate\Support\Collection<int, Document>  $documents
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public static function collection($documents): \Illuminate\Support\Collection
    {
        // The Messages panel renders the citizen-facing thread, so it travels
        // with the row. Internal staff notes are deliberately excluded — that
        // thread is not this panel's.
        $documents->loadMissing([
            'booking.resource',
            'allComments' => fn ($q) => $q
                ->where('visibility', DocumentComment::VISIBILITY_PUBLIC)
                ->oldest(),
        ]);

        $logs = self::statusLogs($documents->pluck('id')->all());

        return $documents->map(fn (Document $document): array => self::forModal($document) + [
            'date_short' => $document->created_at?->format('m/d/y'),
            'logs' => $logs[$document->id] ?? [],
            'messages' => $document->allComments->map(
                fn (DocumentComment $comment): array => $comment->deskPayload()
            )->values()->all(),
            // Drives whether the panel offers a composer or reads as an
            // archive: a colleague may see the thread without owning it.
            'can_message' => CommentController::userCanPost($document, auth()->user()),
        ])->values();
    }

    /**
     * Status changes per document, newest first, keyed by document id. Only
     * entries that actually moved the status are kept — the activity log also
     * records edits to unrelated columns, which read as noise in a stage log.
     *
     * @param  array<int, int>  $documentIds
     * @return array<int, array<int, array{event: string, time: string}>>
     */
    public static function statusLogs(array $documentIds): array
    {
        if ($documentIds === []) {
            return [];
        }

        return Activity::query()
            ->where('subject_type', (new Document)->getMorphClass())
            ->whereIn('subject_id', $documentIds)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('subject_id')
            ->map(fn ($activities): array => $activities
                ->map(function (Activity $activity): ?array {
                    $to = data_get($activity->properties, 'attributes.status');

                    if (! $to) {
                        return null;
                    }

                    return [
                        'event' => 'Updated to '.DocumentStatus::fromLoose($to)->label(),
                        'time' => $activity->created_at?->format('M j, g:i A') ?? '',
                    ];
                })
                ->filter()
                ->values()
                ->all())
            ->all();
    }

    /** @return array<string, mixed> */
    public static function forModal(Document $document): array
    {
        $document->loadMissing('attachments', 'department', 'assignedTo', 'requirements', 'booking.resource');

        return [
            'id' => $document->id,
            'tracking_number' => $document->tracking_number,
            // The request's generated QR, and its print-ready claim slip —
            // shown from the tracking-number tile in the staff modals.
            'qr_url' => PublicStorage::url($document->qr_code_path),
            'slip_url' => route('track.slip', $document->tracking_number),
            'document_type' => $document->document_type,
            // Tracking columns: who/where is responsible for this ticket.
            'thed_id' => $document->department?->code,
            'department' => $document->department?->name,
            'assigned_staff' => $document->assignedTo?->name,
            'citizen_name' => $document->citizen_name,
            'citizen_email' => $document->citizen_email,
            'citizen_contact' => $document->citizen_contact,
            'purpose' => $document->purpose,
            'description' => $document->description,
            'status' => $document->status,
            'status_label' => $document->statusEnum()->label(),
            'needs_triage' => self::needsTriage($document),
            // Whether the viewing user can actually drive this document's status
            // forward — lets the cockpit disable the advance/revert/hold controls
            // instead of rendering an action that the server will then 403.
            'can_advance' => $document->canBeAdvancedBy(auth()->user()),
            // Stage-gate context: whether →In Review's evidence requirement is
            // already met, so the cockpit can ask for a note up front.
            'has_work_evidence' => $document->attachments->isNotEmpty()
                || $document->comments()->where('author_type', 'staff')->exists(),
            'assigned_to' => $document->assigned_to,
            'submitted_at' => $document->created_at?->format('M j, Y g:i A'),
            // Machine-readable day, for the staff tables' date-range filter.
            'submitted_on' => $document->created_at?->toDateString(),
            // Look Up desk: the long-form date the More Info table shows.
            'request_date' => $document->created_at?->format('F j, Y'),
            // Two different dates, deliberately. `needed_by` is what the citizen
            // asked for on the request form; `claim_date` is what staff have
            // actually committed to. The desk shows the first and lets staff
            // adjust into the second.
            'requested_claim_date' => $document->needed_by?->format('F j, Y'),
            'claim_date' => $document->claim_date?->format('F j, Y'),
            // Machine-readable versions, for marking them on the date picker.
            'requested_claim_on' => $document->needed_by?->toDateString(),
            'claim_on' => $document->claim_date?->toDateString(),
            // Citizen messages nobody has read yet — the "waiting on a reply" badge.
            'unread_messages' => $document->allComments()->unreadByStaff()->count(),
            // Cockpit context: lets the in-modal status controls render the hold
            // banner, the returned reason, and restore the stepper position on hold.
            'status_before_hold' => $document->status_before_hold,
            // Position on the four-stage progress bar (DocumentStatus::railStages()).
            'rail_stage' => $document->statusEnum()->railStage(
                DocumentStatus::tryFrom((string) $document->status_before_hold)
            ),
            'blocked_by' => $document->blocked_by,
            'hold_reason' => $document->hold_reason,
            'hold_until' => $document->hold_until?->format('M j, Y'),
            // A hold pauses the SLA clock, so this count is the only thing in the
            // cockpit that says a parked request has overrun its own deadline.
            'hold_overdue_days' => $document->isHoldOverdue() ? $document->holdOverdueDays() : null,
            'remarks' => $document->remarks,
            // The chairs or venue this request reserves, so the modal shows
            // the reservation beside the stages it gates.
            'booking' => self::bookingPayload($document),
            'attachments' => $document->attachments->map(function ($a): array {
                $ext = strtolower(pathinfo((string) $a->file_path, PATHINFO_EXTENSION));

                return [
                    'url' => route('attachments.show', $a),
                    'ext' => $ext,
                    'is_image' => self::isImageExtension($ext),
                ];
            })->values()->all(),
            // Requirement uploads live on their own table, not document_attachments,
            // so they need listing separately or the reviewer sees no evidence at all.
            'requirements' => $document->requirements->map(function (DocumentRequirement $r) use ($document): array {
                $ext = strtolower(pathinfo((string) $r->uploaded_file_path, PATHINFO_EXTENSION));
                $hasFile = $r->uploaded_file_path !== null;

                return [
                    'label' => $r->label,
                    'is_mandatory' => $r->is_mandatory,
                    'review_status' => $r->review_status,
                    'has_file' => $hasFile,
                    'url' => $hasFile ? route('documents.requirements.file', [$document, $r]) : null,
                    'ext' => $ext,
                    'is_image' => $hasFile && self::isImageExtension($ext),
                ];
            })->values()->all(),
        ];
    }

    /**
     * A request's reservation for the Review modal — what, how many, when,
     * where it stands — and whether this viewer can approve it from there.
     * Hand-over and returns stay on the Resources page's lending desk.
     *
     * @return array<string, mixed>|null
     */
    public static function bookingPayload(Document $document): ?array
    {
        $booking = $document->booking;
        $resource = $booking?->resource;

        if (! $booking || ! $resource) {
            return null;
        }

        $state = $booking->lendingState();
        $isEquipment = $resource->isEquipment();
        $pending = $state === Booking::LENDING_PENDING;

        return [
            'id' => $booking->id,
            'item' => $resource->name,
            'is_equipment' => $isEquipment,
            'state' => $state,
            'state_label' => $booking->lendingLabel(),
            'tone' => match ($state) {
                Booking::LENDING_OVERDUE, Booking::LENDING_CANCELLED => 'red',
                Booking::LENDING_PENDING, Booking::LENDING_MISSED => 'amber',
                Booking::LENDING_RETURNED => 'muted',
                default => 'green',
            },
            'requested' => $booking->unitsRequested(),
            'units' => $booking->units(),
            'outstanding' => $booking->unitsOutstanding(),
            'window' => Lending::window($booking),
            'pickup' => $booking->starts_at->format('D, M j · g:i A'),
            'return_by' => $booking->ends_at->format('D, M j · g:i A'),
            'days_overdue' => $booking->daysOverdue(),
            'free' => $pending && $isEquipment ? $resource->unitsAvailable($booking->starts_at, $booking->ends_at, $booking->id) : null,
            'serviceable' => $isEquipment ? $resource->serviceableUnits() : null,
            'clash' => $pending && ! $isEquipment && $resource->approvedConflicts($booking->starts_at, $booking->ends_at, $booking->id)->isNotEmpty(),
            'not_ready' => $pending ? Lending::requestNotReady($booking) : null,
            'can_manage' => (bool) auth()->user()?->can('manage inventory'),
            'approve_url' => route('inventory.loans.approve', $booking),
            'desk_url' => route('inventory.index'),
        ];
    }

    private static function isImageExtension(string $extension): bool
    {
        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    /** Assigned ticket awaiting staff Accept / Decline / Revision on the Requests page. */
    public static function needsTriage(Document $document): bool
    {
        if ($document->assigned_to === null) {
            return false;
        }

        return $document->assigned_to !== null
            && $document->accepted_at === null
            && in_array($document->status, [
                DocumentStatus::Pending->value,
                DocumentStatus::InProgress->value,
            ], true);
    }
}
