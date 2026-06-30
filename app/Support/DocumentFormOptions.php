<?php

namespace App\Support;

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

    public static function departments(): Collection
    {
        return collect();
    }

    /**
     * Suggested department chain per document type, derived from routing rules.
     */
    public static function defaultRoutesByType(): Collection
    {
        return collect();
    }
}
