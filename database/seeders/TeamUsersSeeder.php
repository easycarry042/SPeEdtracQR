<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TeamUsersSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // ── Departments (looked up by name) ───────────────────────────────────
        $depts = Department::all()->keyBy('name');

        // ── Users ─────────────────────────────────────────────────────────────
        // Format: [ name, email, password, role, department name ]
        $users = [
            // Admin
            ['Super Admin',     'admin@speedtraqr.com',              env('ADMIN_PASSWORD', 'password123'), 'super_admin',     null],

            // Front Desk
            ['Maria Santos',    'maria.santos@speedtraqr.com',       'staff1234',   'staff',           'Front Desk/Reception'],

            // Accounting
            ['Jose Reyes',      'jose.reyes@speedtraqr.com',         'staff1234',   'staff',           'Accounting'],

            // Engineering
            ['Ana Cruz',        'ana.cruz@speedtraqr.com',           'staff1234',   'staff',           'Engineering'],

            // Mayor's Office
            ['Carlos Dela Cruz', 'carlos.delacruz@speedtraqr.com',    'staff1234',   'staff',           'Mayors Office'],

            // Records
            ['Liza Reyes',      'liza.reyes@speedtraqr.com',         'staff1234',   'staff',           'Records/Archiving'],
        ];

        foreach ($users as [$name, $email, $password, $roleName, $deptName]) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $dept = $deptName ? $depts->get($deptName) : null;

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => bcrypt($password),
                    'department_id' => $dept?->id,
                    'is_active' => true,
                ]
            );

            $user->syncRoles([$role]);

            $this->command->info('✓  '.str_pad($roleName, 16)." {$email}  /  {$password}");
        }

        $this->command->newLine();
        $this->command->info('All team users seeded. Share the credentials above with your co-workers.');
    }
}
