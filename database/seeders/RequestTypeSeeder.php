<?php

declare(strict_types=1);

namespace Database\Seeders;

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
        $types = [
            ['Business Permit', [
                'Barangay Business Clearance',
                'Community Tax Certificate (Cedula)',
                'DTI / SEC / CDA Registration',
                'Lease Contract or Land Title',
                'Occupancy Permit',
                'Fire Safety Inspection Certificate',
                'Sanitary Permit',
            ]],
            ["Mayor's Permit", [
                'Barangay Clearance',
                'Community Tax Certificate (Cedula)',
                'Proof of Business Registration',
            ]],
            ['Building Permit', [
                'Transfer Certificate of Title or Tax Declaration',
                'Lot Plan / Survey',
                'Building Plans & Specifications',
                'Bill of Materials',
            ]],
            ['Barangay Clearance', [
                'Community Tax Certificate (Cedula)',
                'Valid Government ID',
                'Proof of Residency',
            ]],
            ['Community Tax Certificate', [
                'Valid Government ID',
            ]],
            ['Real Property Tax', [
                'Latest Tax Declaration or Official Receipt',
                'Valid Government ID',
            ]],
            ['Birth Certificate Request', [
                'Valid Government ID',
                'Authorization Letter (if not the document owner)',
            ]],
            ['Other', []],
        ];

        foreach ($types as $order => [$name, $requirements]) {
            $type = RequestType::updateOrCreate(
                ['name' => $name],
                ['kind' => RequestType::KIND_DOCUMENT, 'is_active' => true, 'sort_order' => $order],
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
            ['Lei Making', 'Ribbon-and-flower leis prepared for ceremonies and building inaugurations.', 'Letter of Request addressed to the Mayor'],
            ['Tarpaulin / Streamer Printing', 'Printed tarpaulins or streamers for events and announcements.', 'Approved layout / design'],
        ];

        foreach ($services as $order => [$name, $description, $requirement]) {
            $type = RequestType::updateOrCreate(
                ['name' => $name],
                [
                    'kind' => RequestType::KIND_SERVICE,
                    'description' => $description,
                    'is_active' => true,
                    'sort_order' => 200 + $order,
                ],
            );

            $type->requirements()->firstOrCreate(
                ['label' => $requirement],
                ['is_mandatory' => true, 'sort_order' => 0],
            );
        }
    }
}
