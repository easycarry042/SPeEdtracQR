<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Read model for a staff member's own assigned work — the review cockpit that
 * used to live behind a separate "Requests" tab and now renders inside My
 * Profile. Returns everything the Alpine `reviewPanel()` scope needs.
 */
class AssignedWorkQueue
{
    public function __construct(private readonly User $user) {}

    /**
     * Active assigned work, including newly assigned tickets awaiting acceptance
     * and items the staff member has parked (Returned / On Hold) — those stay in
     * the queue so they can be resumed from the same cockpit modal.
     *
     * @return Collection<int, Document>
     */
    public function requests(): Collection
    {
        return Document::with('attachments', 'requirements')
            ->where('assigned_to', $this->user->id)
            ->whereIn('status', [
                DocumentStatus::Pending->value,
                DocumentStatus::InProgress->value,
                DocumentStatus::InReview->value,
                DocumentStatus::Approved->value,
                DocumentStatus::Returned->value,
                DocumentStatus::OnHold->value,
            ])
            ->latest('assigned_at')
            ->get();
    }

    /**
     * View data for the cockpit: the modal payload plus the forward status line,
     * so each advance renders client-side without a reload.
     *
     * @return array{requests: Collection<int, Document>, requestPayload: Collection<int, array<string, mixed>>, flow: list<array{value: string, label: string}>, assignedCount: int, completedCount: int, scheduleResources: Collection<int, \App\Models\Resource>}
     */
    public function toViewData(): array
    {
        $requests = $this->requests();

        return [
            'requests' => $requests,
            'requestPayload' => $requests->map(fn (Document $d): array => RequestReview::forModal($d))->values(),
            'flow' => collect(DocumentStatus::flow())
                ->map(fn (DocumentStatus $s): array => ['value' => $s->value, 'label' => $s->label()])
                ->values()->all(),
            'assignedCount' => $requests->count(),
            'completedCount' => Document::where('assigned_to', $this->user->id)
                ->where('status', DocumentStatus::Completed->value)
                ->count(),
            // Bookable resources the cockpit can reserve a date on. With an empty
            // catalog the scheduling panel simply doesn't render.
            'scheduleResources' => Resource::active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }
}
