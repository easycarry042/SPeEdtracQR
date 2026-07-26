<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\RequestType;
use App\Models\Resource;
use Illuminate\Database\Seeder;

/**
 * Seeds a starter set of bookable municipal resources, each with a matching
 * booking-kind request type. Admin-editable. Idempotent.
 */
class ResourceSeeder extends Seeder
{
    public function run(): void
    {
        $resources = [
            ['Covered Court', 'Covered Court Reservation', 'Reserve the covered court for events, practices, or ceremonies.'],
            ['Municipal Plaza', 'Plaza Reservation', 'Reserve the municipal plaza for public gatherings.'],
            ['Sound System', 'Sound System Request', 'Borrow the municipal sound system for a scheduled event.'],
        ];

        foreach ($resources as $order => [$resourceName, $typeName, $description]) {
            $resource = Resource::updateOrCreate(
                ['name' => $resourceName],
                ['is_active' => true, 'sort_order' => $order],
            );

            RequestType::updateOrCreate(
                ['name' => $typeName],
                [
                    'kind' => RequestType::KIND_BOOKING,
                    'resource_id' => $resource->id,
                    'description' => $description,
                    'is_active' => true,
                    'sort_order' => 100 + $order,
                ],
            );
        }
    }
}
