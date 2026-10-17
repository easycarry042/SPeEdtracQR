<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Department;
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
     * The starter catalogue. Inventory is seeded alongside each row so the
     * Inventory page has something true to show on a fresh install: a facility
     * carries where it is and how many it holds, equipment carries how many
     * units the office owns. Counts are starting figures — staff correct them
     * on the Inventory page as stock changes hands.
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: string, 4: array<string, mixed>}>
     *                                                                                             [resource name, kind, request-type name, description, inventory]
     */
    private const CATALOG = [
        ['Covered Court', RequestType::KIND_BOOKING, 'Covered Court Reservation', 'Reserve the covered court for events, practices, or ceremonies.',
            ['capacity' => 800, 'location' => 'Beside the Municipal Hall']],
        ['Municipal Gymnasium', RequestType::KIND_BOOKING, 'Gymnasium Reservation', 'Reserve the gymnasium for sports, assemblies, or large events.',
            ['capacity' => 1500, 'location' => 'Sports Complex, Barangay Poblacion']],
        ['Municipal Plaza', RequestType::KIND_BOOKING, 'Plaza Reservation', 'Reserve the municipal plaza for public gatherings.',
            ['capacity' => 2000, 'location' => 'Town centre, fronting the Municipal Hall']],
        ['Session Hall', RequestType::KIND_BOOKING, 'Session Hall Reservation', 'Reserve the session hall for meetings and small functions.',
            ['capacity' => 120, 'location' => 'Municipal Hall, 2nd floor']],
        ['Monoblock Chairs', RequestType::KIND_EQUIPMENT, 'Chairs Borrowing', 'Borrow monoblock chairs for a scheduled event.',
            ['total_quantity' => 500, 'damaged_quantity' => 18, 'location' => 'GSO stockroom']],
        ['Folding Tables', RequestType::KIND_EQUIPMENT, 'Tables Borrowing', 'Borrow folding tables for a scheduled event.',
            ['total_quantity' => 80, 'damaged_quantity' => 4, 'location' => 'GSO stockroom']],
        ['Tents', RequestType::KIND_EQUIPMENT, 'Tent Borrowing', 'Borrow tents for a scheduled event.',
            ['total_quantity' => 24, 'damaged_quantity' => 2, 'location' => 'GSO motor pool']],
        ['Sound System', RequestType::KIND_EQUIPMENT, 'Sound System Request', 'Borrow the municipal sound system for a scheduled event.',
            ['total_quantity' => 3, 'location' => 'GSO stockroom']],
    ];

    public function run(): void
    {
        // Facilities and equipment are the General Services Office's to lend,
        // so every booking and borrowing request routes there. Without a
        // department the ticket reaches no specific queue.
        $this->callOnce(DepartmentSeeder::class);
        $generalServices = Department::where('code', 'GSO')->value('id');

        foreach (self::CATALOG as $order => [$resourceName, $kind, $typeName, $description, $inventory]) {
            $resource = Resource::updateOrCreate(
                ['name' => $resourceName],
                [
                    'is_active' => true,
                    'sort_order' => $order,
                    // Facility or countable stock — this is what tells the
                    // approval check whether to look for a clash or a count.
                    'kind' => $kind === RequestType::KIND_EQUIPMENT
                        ? Resource::KIND_EQUIPMENT
                        : Resource::KIND_FACILITY,
                ] + $inventory,
            );

            $type = RequestType::updateOrCreate(
                ['name' => $typeName],
                [
                    'kind' => $kind,
                    'resource_id' => $resource->id,
                    'description' => $description,
                    'is_active' => true,
                    'sort_order' => 100 + $order,
                    'department_id' => $generalServices,
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
