<?php

namespace App\Support;

use App\Models\Department;
use App\Models\RoutingRule;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Shared option lists for the document submission form (modal) and edit form.
 */
class DocumentFormOptions
{
    /**
     * @return string[]
     */
    public static function categoryOptions(): array
    {
        return [
            'Business Permit',
            'Barangay Clearance',
            'Building Permit',
            "Mayor's Permit",
            'Real Property Tax',
            'Birth Certificate Request',
            'Community Tax Certificate',
            'Other',
        ];
    }

    public static function departments(): EloquentCollection
    {
        return Department::orderBy('name')->get();
    }

    /**
     * Suggested department chain per document type, derived from routing rules.
     */
    public static function defaultRoutesByType(): Collection
    {
        return RoutingRule::with(['fromDepartment', 'toDepartment'])
            ->orderBy('document_type')
            ->orderBy('step_order')
            ->get()
            ->groupBy('document_type')
            ->map(function ($rules) {
                $chain = collect([$rules->first()->fromDepartment?->id]);
                foreach ($rules as $rule) {
                    if ($rule->toDepartment) {
                        $chain->push($rule->toDepartment->id);
                    }
                }

                return $chain->filter()->unique()->values()->all();
            });
    }
}
