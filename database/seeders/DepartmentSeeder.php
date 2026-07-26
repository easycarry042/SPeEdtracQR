<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

/**
 * Typical municipal offices involved in internal request routing. Idempotent:
 * safe to re-run; matches on code and refreshes the name.
 */
class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['code' => 'OM', 'name' => 'Office of the Mayor'],
            ['code' => 'BO', 'name' => 'Municipal Budget Office'],
            ['code' => 'ACC', 'name' => 'Municipal Accounting Office'],
            ['code' => 'BAC', 'name' => 'Bids and Awards Committee'],
            ['code' => 'GSO', 'name' => 'General Services Office'],
            ['code' => 'TRSY', 'name' => "Municipal Treasurer's Office"],
            ['code' => 'HRMO', 'name' => 'Human Resource Management Office'],
            ['code' => 'TRSM', 'name' => 'Tourism Office'],
            ['code' => 'ENG', 'name' => 'Municipal Engineering Office'],
            ['code' => 'MHO', 'name' => 'Municipal Health Office'],
        ];

        foreach ($departments as $department) {
            Department::updateOrCreate(
                ['code' => $department['code']],
                ['name' => $department['name'], 'is_active' => true],
            );
        }
    }
}
