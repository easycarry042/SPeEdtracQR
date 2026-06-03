<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Clear permission cache so freshly seeded roles are visible
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $adminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $staffRole = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        // ── Admin ──────────────────────────────────────────────────────────────
        // RolesAndPermissionsSeeder already creates this user; firstOrCreate
        // ensures we don't duplicate it and syncRoles guarantees the role is set.
        $admin = User::firstOrCreate(
            ['email' => 'admin@speedtraqr.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt(env('ADMIN_PASSWORD', 'password123')),
            ]
        );
        $admin->syncRoles([$adminRole]);

        // ── Staff ──────────────────────────────────────────────────────────────
        // Keyed by department name so each staff member is placed correctly.
        $departments = Department::all()->keyBy('name');

        $staffUsers = [
            [
                'name' => 'Maria Santos',
                'email' => 'maria.santos@speedtraqr.com',
                'department' => 'Front Desk/Reception',
            ],
            [
                'name' => 'Jose Reyes',
                'email' => 'jose.reyes@speedtraqr.com',
                'department' => 'Accounting',
            ],
            [
                'name' => 'Ana Cruz',
                'email' => 'ana.cruz@speedtraqr.com',
                'department' => 'Engineering',
            ],
            [
                'name' => 'Carlos Dela Cruz',
                'email' => 'carlos.delacruz@speedtraqr.com',
                'department' => 'Mayors Office',
            ],
            [
                'name' => 'Liza Reyes',
                'email' => 'liza.reyes@speedtraqr.com',
                'department' => 'Records/Archiving',
            ],
        ];

        foreach ($staffUsers as $data) {
            $dept = $departments->get($data['department']);

            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => bcrypt('staff1234'),
                    'department_id' => $dept?->id,
                ]
            );

            $user->syncRoles([$staffRole]);
        }

        $adminPassword = env('ADMIN_PASSWORD', 'password123');
        $this->command->info("✓ Super Admin  → admin@speedtraqr.com  / {$adminPassword}");
        $this->command->info('✓ Staff        → *@speedtraqr.com       / staff1234');
    }
}
