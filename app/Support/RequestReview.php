<?php

namespace App\Support;

use App\Models\Document;
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
    public static function assignableStaff(): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'staff'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** @return array<string, mixed> */
    public static function forModal(Document $document): array
    {
        $document->loadMissing('attachments');

        return [
            'id' => $document->id,
            'tracking_number' => $document->tracking_number,
            'document_type' => $document->document_type,
            'citizen_name' => $document->citizen_name,
            'citizen_email' => $document->citizen_email,
            'citizen_contact' => $document->citizen_contact,
            'purpose' => $document->purpose,
            'description' => $document->description,
            'status' => $document->status,
            'status_label' => $document->statusEnum()->label(),
            'needs_triage' => self::needsTriage($document),
            'assigned_to' => $document->assigned_to,
            'submitted_at' => optional($document->created_at)->format('M j, Y g:i A'),
            'attachments' => $document->attachments->map(function ($a) {
                $ext = strtolower(pathinfo($a->file_path, PATHINFO_EXTENSION));

                return [
                    'url' => route('attachments.show', $a),
                    'ext' => $ext,
                    'is_image' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true),
                ];
            })->values()->all(),
        ];
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
                \App\Enums\DocumentStatus::Pending->value,
                \App\Enums\DocumentStatus::InProgress->value,
            ], true);
    }
}
