<?php

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Shapes a Document into the flat payload the Review modal needs (supervisor and
 * staff share the same modal markup). Attachments are exposed as authorized URLs
 * with their extension so the modal can show a thumbnail or a file chip.
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

    /** @return array<string, mixed> */
    public static function forModal(Document $document): array
    {
        $document->loadMissing('attachments', 'department', 'assignedTo', 'requirements');

        return [
            'id' => $document->id,
            'tracking_number' => $document->tracking_number,
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
            // Citizen messages nobody has read yet — the "waiting on a reply" badge.
            'unread_messages' => $document->allComments()->unreadByStaff()->count(),
            // Cockpit context: lets the in-modal status controls render the hold
            // banner, the returned reason, and restore the stepper position on hold.
            'status_before_hold' => $document->status_before_hold,
            'blocked_by' => $document->blocked_by,
            'hold_reason' => $document->hold_reason,
            'hold_until' => $document->hold_until?->format('M j, Y'),
            'remarks' => $document->remarks,
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
