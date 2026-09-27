<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Department;
use App\Models\RequestType;
use Illuminate\Database\Seeder;

/**
 * Seeds the request-type catalog from the previously-hardcoded list, each as a
 * `document` kind with a starter requirement checklist. Requirements are typical
 * Philippine LGU examples — admins should confirm/adjust them against the
 * municipality's official list. Idempotent.
 */
class RequestTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Each request type names the office that handles it. Without this the
        // ticket is created with no department and reaches no specific queue —
        // the routing added in July does nothing, and every Supervisor ends up
        // triaging every request regardless of office.
        $this->callOnce(DepartmentSeeder::class);
        $departments = Department::pluck('id', 'code');

        $types = [
            ['Business Permit', 'OM', [
                'Barangay Business Clearance',
                'Community Tax Certificate (Cedula)',
                'DTI / SEC / CDA Registration',
                'Lease Contract or Land Title',
                'Occupancy Permit',
                'Fire Safety Inspection Certificate',
                'Sanitary Permit',
            ]],
            ["Mayor's Permit", 'OM', [
                'Barangay Clearance',
                'Community Tax Certificate (Cedula)',
                'Proof of Business Registration',
            ]],
            ['Building Permit', 'ENG', [
                'Transfer Certificate of Title or Tax Declaration',
                'Lot Plan / Survey',
                'Building Plans & Specifications',
                'Bill of Materials',
            ]],
            ['Barangay Clearance', 'OM', [
                'Community Tax Certificate (Cedula)',
                'Valid Government ID',
                'Proof of Residency',
            ]],
            ['Community Tax Certificate', 'TRSY', [
                'Valid Government ID',
            ]],
            ['Real Property Tax', 'TRSY', [
                'Latest Tax Declaration or Official Receipt',
                'Valid Government ID',
            ]],
            ['Birth Certificate Request', 'MHO', [
                'Valid Government ID',
                'Authorization Letter (if not the document owner)',
            ]],
            ['Other', 'OM', []],
        ];

        foreach ($types as $order => [$name, $departmentCode, $requirements]) {
            $type = RequestType::updateOrCreate(
                ['name' => $name],
                [
                    'kind' => RequestType::KIND_DOCUMENT,
                    'is_active' => true,
                    'sort_order' => $order,
                    'department_id' => $departments[$departmentCode] ?? null,
                ],
            );

            foreach ($requirements as $reqOrder => $label) {
                $type->requirements()->firstOrCreate(
                    ['label' => $label],
                    ['is_mandatory' => true, 'sort_order' => $reqOrder],
                );
            }
        }

        // Service / production requests: the office asks the LGU to make a
        // quantity of something by a date (no resource reserved). Lei making —
        // ribbon-and-flower medallions worn by officials at inaugurations — is
        // the canonical local example.
        $services = [
            ['Lei Making', 'GSO', 'Ribbon-and-flower leis prepared for ceremonies and building inaugurations.', 'Letter of Request addressed to the Mayor'],
            ['Tarpaulin / Streamer Printing', 'GSO', 'Printed tarpaulins or streamers for events and announcements.', 'Approved layout / design'],
        ];

        foreach ($services as $order => [$name, $departmentCode, $description, $requirement]) {
            $type = RequestType::updateOrCreate(
                ['name' => $name],
                [
                    'kind' => RequestType::KIND_SERVICE,
                    'description' => $description,
                    'is_active' => true,
                    'sort_order' => 200 + $order,
                    'department_id' => $departments[$departmentCode] ?? null,
                ],
            );

            $type->requirements()->firstOrCreate(
                ['label' => $requirement],
                ['is_mandatory' => true, 'sort_order' => 0],
            );
        }
    }
}
