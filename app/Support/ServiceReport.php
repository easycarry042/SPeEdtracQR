<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Models\RequestType;
use App\Models\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read model for the Services report (Phase 3): citizen-facing request types
 * and resource bookings introduced in Phases 1–2.
 *
 * Volume is keyed off documents.document_type == RequestType.name (the same link
 * the public form writes). Bookings and requirements are read through their own
 * tables. Every figure is a "now" snapshot — no date window — so the report is a
 * standing operational picture, not a trend chart.
 *
 * **Scoped to one office.** The report was org-wide, so the General Services
 * head opened it and read the Treasury's and Engineering's services alongside
 * their own. A department head is answerable for the request types routed to
 * their department and nothing else; `forDepartment()` narrows every figure to
 * those types. A super admin passes null and still sees everything.
 */
class ServiceReport
{
    /** Department whose services this report describes; null = every office. */
    private ?int $departmentId = null;

    public function forDepartment(?int $departmentId): self
    {
        $this->departmentId = $departmentId;

        return $this;
    }

    /** The request types this report covers, by name — the key documents cite. */
    private function typeNames(): Collection
    {
        return $this->types()->pluck('name');
    }

    /** @return Builder<RequestType> */
    private function types()
    {
        return RequestType::query()
            ->when($this->departmentId, fn ($q) => $q->where('department_id', $this->departmentId));
    }

    /**
     * Requests citing one of this office's types. Matched by name because that
     * is the link the public form writes (documents.document_type).
     *
     * @return Builder<Document>
     */
    private function scopedDocuments()
    {
        return Document::query()
            ->when($this->departmentId, fn ($q) => $q->whereIn('document_type', $this->typeNames()));
    }

    /** Reservations made against this office's own request types. */
    private function scopedBookings()
    {
        return Booking::query()
            ->when($this->departmentId, fn ($q) => $q->whereHas(
                'document',
                fn ($d) => $d->whereIn('document_type', $this->typeNames())
            ));
    }

    /** Headline vitals for the report banner. */
    public function kpis(): array
    {
        $requirements = $this->requirementStats();

        return [
            'request_types' => $this->types()->active()->count(),
            'bookings_upcoming' => $this->scopedBookings()
                ->where('status', Booking::STATUS_APPROVED)
                ->where('ends_at', '>=', now())
                ->count(),
            'pending_approvals' => $this->scopedBookings()->where('status', Booking::STATUS_PENDING)->count(),
            'requirement_completion_pct' => $requirements['submitted_pct'],
        ];
    }

    /**
     * Volume per request type: how many documents cite each type's name, newest
     * activity first. Inactive/retired types with no volume are omitted.
     *
     * @return Collection<int, array{name: string, kind: string, resource: ?string, total: int, this_month: int}>
     */
    public function requestVolumeByType(): Collection
    {
        $totals = $this->scopedDocuments()
            ->select('document_type', DB::raw('COUNT(*) as total'))
            ->whereNotNull('document_type')
            ->groupBy('document_type')
            ->pluck('total', 'document_type');

        $thisMonth = $this->scopedDocuments()
            ->select('document_type', DB::raw('COUNT(*) as total'))
            ->whereNotNull('document_type')
            ->where('created_at', '>=', now()->startOfMonth())
            ->groupBy('document_type')
            ->pluck('total', 'document_type');

        return $this->types()
            ->with('resource')
            ->orderBy('kind')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (RequestType $type): array => [
                'name' => $type->name,
                'kind' => $type->kind,
                'resource' => $type->resource?->name,
                'total' => (int) ($totals[$type->name] ?? 0),
                'this_month' => (int) ($thisMonth[$type->name] ?? 0),
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Utilisation per bookable resource: lifetime totals plus the count of
     * approved reservations still ahead on the calendar.
     *
     * @return Collection<int, array{name: string, is_active: bool, total: int, approved: int, pending: int, upcoming: int}>
     */
    public function bookingsByResource(): Collection
    {
        // Only the resources this office's own request types reserve.
        $resourceIds = $this->types()->whereNotNull('resource_id')->distinct()->pluck('resource_id');

        return Resource::query()
            ->when($this->departmentId, fn ($q) => $q->whereIn('id', $resourceIds))
            ->withCount([
                'bookings as total_count',
                'bookings as approved_count' => fn ($q) => $q->where('status', Booking::STATUS_APPROVED),
                'bookings as pending_count' => fn ($q) => $q->where('status', Booking::STATUS_PENDING),
                'bookings as upcoming_count' => fn ($q) => $q
                    ->where('status', Booking::STATUS_APPROVED)
                    ->where('ends_at', '>=', now()),
            ])
            ->orderByDesc('total_count')
            ->orderBy('name')
            ->get()
            ->map(fn (Resource $resource): array => [
                'name' => $resource->name,
                'is_active' => (bool) $resource->is_active,
                'total' => (int) $resource->total_count,
                'approved' => (int) $resource->approved_count,
                'pending' => (int) $resource->pending_count,
                'upcoming' => (int) $resource->upcoming_count,
            ]);
    }

    /**
     * Requirement checklist health across all document requests: how much of the
     * mandatory paperwork citizens have actually submitted and staff verified.
     *
     * @return array{mandatory_total: int, submitted: int, verified: int, submitted_pct: int, verified_pct: int}
     */
    public function requirementStats(): array
    {
        $mandatory = DocumentRequirement::query()
            ->where('is_mandatory', true)
            ->when($this->departmentId, fn ($q) => $q->whereHas(
                'document',
                fn ($d) => $d->whereIn('document_type', $this->typeNames())
            ));

        $total = (clone $mandatory)->count();
        $submitted = (clone $mandatory)->whereNotNull('uploaded_file_path')->count();
        $verified = (clone $mandatory)->whereNotNull('verified_at')->count();

        return [
            'mandatory_total' => $total,
            'submitted' => $submitted,
            'verified' => $verified,
            'submitted_pct' => $total > 0 ? (int) round($submitted / $total * 100) : 0,
            'verified_pct' => $total > 0 ? (int) round($verified / $total * 100) : 0,
        ];
    }
}
