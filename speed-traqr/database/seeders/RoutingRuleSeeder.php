<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\RoutingRule;
use Illuminate\Database\Seeder;

class RoutingRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            ['document_type' => 'Business Permit', 'from' => 'Front Desk/Reception', 'to' => 'Accounting', 'order' => 1],
            ['document_type' => 'Business Permit', 'from' => 'Accounting', 'to' => 'Engineering', 'order' => 2],
            ['document_type' => 'Business Permit', 'from' => 'Engineering', 'to' => 'Mayors Office', 'order' => 3],
            ['document_type' => 'Business Permit', 'from' => 'Mayors Office', 'to' => 'Records/Archiving', 'order' => 4],
            ['document_type' => 'Barangay Clearance', 'from' => 'Front Desk/Reception', 'to' => 'Mayors Office', 'order' => 1],
            ['document_type' => 'Barangay Clearance', 'from' => 'Mayors Office', 'to' => 'Records/Archiving', 'order' => 2],
        ];

        foreach ($rules as $rule) {
            $fromDepartment = Department::where('name', $rule['from'])->first();
            $toDepartment = Department::where('name', $rule['to'])->first();

            if (! $fromDepartment || ! $toDepartment) {
                continue;
            }

            RoutingRule::updateOrCreate(
                [
                    'document_type' => $rule['document_type'],
                    'step_order' => $rule['order'],
                ],
                [
                    'from_department_id' => $fromDepartment->id,
                    'to_department_id' => $toDepartment->id,
                ]
            );
        }
    }
}