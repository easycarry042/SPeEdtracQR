<?php

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\RequestStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * One office's internal (dept-to-dept) requests, in the three queues the
 * Internal page draws: what awaits the office, what it filed and is still
 * open, and what has closed. An account
 * with no office (a super admin) sees every open request and every closed one.
 */
final class InternalInbox
{
    /**
     * @return array{department: ?Department, awaiting: Collection<int, Document>, filed: Collection<int, Document>, closed: Collection<int, Document>, search: string}
     */
    public static function for(User $user, string $search = ''): array
    {
        $department = $user->department;
        $search = trim($search);

        $base = fn (): Builder => Document::where('origin', Document::ORIGIN_INTERNAL)
            ->with(['requestingDepartment', 'creator', 'requestSteps.department'])
            ->when($search !== '', fn ($q) => $q->where(function ($qq) use ($search): void {
                $qq->where('tracking_number', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%");
            }))
            ->latest();

        $active = DocumentStatus::activeValues();
        $terminal = [DocumentStatus::Completed->value, DocumentStatus::Denied->value];

        if ($department) {
            return [
                'department' => $department,
                'awaiting' => $base()->whereIn('status', $active)
                    ->whereHas('requestSteps', fn ($q) => $q
                        ->where('status', RequestStep::STATUS_CURRENT)
                        ->where('department_id', $department->id))
                    ->get(),
                'filed' => $base()->whereIn('status', $active)
                    ->where('requesting_department_id', $department->id)
                    ->get(),
                // Closed history stays office-scoped: filed here or passed through here.
                'closed' => $base()->whereIn('status', $terminal)
                    ->where(fn ($q) => $q
                        ->where('requesting_department_id', $department->id)
                        ->orWhereHas('requestSteps', fn ($qq) => $qq->where('department_id', $department->id)))
                    ->get(),
                'search' => $search,
            ];
        }

        return [
            'department' => null,
            'awaiting' => $base()->whereIn('status', $active)->get(),
            'filed' => collect(),
            'closed' => $base()->whereIn('status', $terminal)->get(),
            'search' => $search,
        ];
    }
}
