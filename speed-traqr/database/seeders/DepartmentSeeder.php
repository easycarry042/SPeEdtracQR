<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'Front Desk/Reception', 'email' => 'frontdesk@sanpedro.gov', 'sla_hours' => 2],
            ['name' => 'Accounting', 'email' => 'accounting@sanpedro.gov', 'sla_hours' => 48],
            ['name' => 'Engineering', 'email' => 'engineering@sanpedro.gov', 'sla_hours' => 72],
            ['name' => 'Mayors Office', 'email' => 'mayor@sanpedro.gov', 'sla_hours' => 24],
            ['name' => 'Records/Archiving', 'email' => 'records@sanpedro.gov', 'sla_hours' => 12],
        ];

        foreach ($departments as $department) {
            Department::firstOrCreate(['name' => $department['name']], $department);
        }
    }
}