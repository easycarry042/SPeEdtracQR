<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\RequestType;
use App\Models\Resource;
use Illuminate\Database\Seeder;

/**
 * Seeds a starter set of bookable municipal resources, each with a matching
 * request type. Two kinds:
 *   - facilities (KIND_BOOKING) — reserved for a time window on one day;
 *   - equipment (KIND_EQUIPMENT) — borrowed in a quantity across dates.
 *
 * Every reservation type carries a mandatory "Letter of Request" — the document
 * a real municipality expects for use of its facilities/equipment. Admin-editable
 * and idempotent.
 */
class ResourceSeeder extends Seeder
{
    /**
     * @var array<int, array{0: string, 1: string, 2: string, 3: string}>
     *                                                                    [resource name, kind, request-type name, description]
     */
    private const CATALOG = [
        ['Covered Court', RequestType::KIND_BOOKING, 'Covered Court Reservation', 'Reserve the covered court for events, practices, or ceremonies.'],
        ['Municipal Gymnasium', RequestType::KIND_BOOKING, 'Gymnasium Reservation', 'Reserve the gymnasium for sports, assemblies, or large events.'],
        ['Municipal Plaza', RequestType::KIND_BOOKING, 'Plaza Reservation', 'Reserve the municipal plaza for public gatherings.'],
        ['Session Hall', RequestType::KIND_BOOKING, 'Session Hall Reservation', 'Reserve the session hall for meetings and small functions.'],
        ['Monoblock Chairs', RequestType::KIND_EQUIPMENT, 'Chairs Borrowing', 'Borrow monoblock chairs for a scheduled event.'],
        ['Folding Tables', RequestType::KIND_EQUIPMENT, 'Tables Borrowing', 'Borrow folding tables for a scheduled event.'],
        ['Tents', RequestType::KIND_EQUIPMENT, 'Tent Borrowing', 'Borrow tents for a scheduled event.'],
        ['Sound System', RequestType::KIND_EQUIPMENT, 'Sound System Request', 'Borrow the municipal sound system for a scheduled event.'],
    ];

    public function run(): void
    {
        foreach (self::CATALOG as $order => [$resourceName, $kind, $typeName, $description]) {
            $resource = Resource::updateOrCreate(
                ['name' => $resourceName],
                ['is_active' => true, 'sort_order' => $order],
            );

            $type = RequestType::updateOrCreate(
                ['name' => $typeName],
                [
                    'kind' => $kind,
                    'resource_id' => $resource->id,
                    'description' => $description,
                    'is_active' => true,
                    'sort_order' => 100 + $order,
                ],
            );

            // Facilities and equipment both need a letter of request on file.
            $type->requirements()->firstOrCreate(
                ['label' => 'Letter of Request addressed to the Mayor'],
                ['is_mandatory' => true, 'sort_order' => 0],
            );
        }
    }
}
