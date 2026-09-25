<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\SeedGuard;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TeamUsersSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // This seeder exists purely to populate a development machine with a
        // believable municipal team on a shared weak password. None of it
        // belongs on a live system.
        if (! SeedGuard::allowsDemoAccounts()) {
            $this->command?->warn('Skipping the demo municipal team: not a local/testing environment.');

            return;
        }

        // ── Users ─────────────────────────────────────────────────────────────
        // Format: [ name, email, password, role ]
        $users = [
            // Admin
            ['Super Admin',     'admin@speedtraqr.com',              SeedGuard::adminPassword(), 'super_admin'],

            // Front Desk
            ['Maria Santos',    'maria.santos@speedtraqr.com',       'staff1234',   'staff'],

            // Accounting
            ['Jose Reyes',      'jose.reyes@speedtraqr.com',         'staff1234',   'staff'],

            // Engineering
            ['Ana Cruz',        'ana.cruz@speedtraqr.com',           'staff1234',   'staff'],

            // Mayor's Office
            ['Carlos Dela Cruz', 'carlos.delacruz@speedtraqr.com',    'staff1234',   'staff'],

            // Records
            ['Liza Reyes',      'liza.reyes@speedtraqr.com',         'staff1234',   'staff'],
        ];

        foreach ($users as [$name, $email, $password, $roleName]) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => bcrypt($password),
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
