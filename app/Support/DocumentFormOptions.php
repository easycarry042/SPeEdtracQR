<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\RequestType;
use Throwable;

/**
 * Shared option lists for the document submission form (modal) and edit form.
 */
class DocumentFormOptions
{
    /**
     * Active document-kind request types, sourced from the admin-managed catalog.
     * Falls back to the built-in defaults when the catalog is empty or the table
     * isn't migrated yet (fresh installs / tests).
     *
     * @return string[]
     */
    public static function categoryOptions(): array
    {
        try {
            $names = RequestType::query()
                ->where('is_active', true)
                ->where('kind', RequestType::KIND_DOCUMENT)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('name')
                ->all();

            if ($names !== []) {
                return $names;
            }
        } catch (Throwable) {
            // request_types not migrated yet — fall through to defaults.
        }

        return self::defaultCategoryOptions();
    }

    /**
     * @return string[]
     */
    public static function defaultCategoryOptions(): array
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
}
