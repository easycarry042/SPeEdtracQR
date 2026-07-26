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
    }
}
