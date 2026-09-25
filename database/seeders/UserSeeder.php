<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\SeedGuard;
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
                'password' => bcrypt(SeedGuard::adminPassword()),
            ]
        );
        $admin->syncRoles([$adminRole]);

        // The named staff below are demo fixtures on a shared weak password.
        // They exist to make a fresh clone usable, and must never reach a live
        // system — stop here, having still created roles and the super admin.
        if (! SeedGuard::allowsDemoAccounts()) {
            $this->command?->warn('Skipping demo staff accounts: not a local/testing environment.');

            return;
        }

        // ── Staff ──────────────────────────────────────────────────────────────
        $staffUsers = [
            [
                'name' => 'Maria Santos',
                'email' => 'maria.santos@speedtraqr.com',
            ],
            [
                'name' => 'Jose Reyes',
                'email' => 'jose.reyes@speedtraqr.com',
            ],
            [
                'name' => 'Ana Cruz',
                'email' => 'ana.cruz@speedtraqr.com',
            ],
            [
                'name' => 'Carlos Dela Cruz',
                'email' => 'carlos.delacruz@speedtraqr.com',
            ],
            [
                'name' => 'Liza Reyes',
                'email' => 'liza.reyes@speedtraqr.com',
            ],
        ];

        foreach ($staffUsers as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => bcrypt('staff1234'),
                ]
            );

            $user->syncRoles([$staffRole]);
        }

        // Only ever printed in local/testing — the early return above means a
        // real deployment never reaches this, so no credential is ever echoed
        // into a production deploy log.
        $this->command->info('✓ Super Admin  → admin@speedtraqr.com  / '.SeedGuard::adminPassword());
        $this->command->info('✓ Staff        → *@speedtraqr.com       / staff1234');
    }
}
