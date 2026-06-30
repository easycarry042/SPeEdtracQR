<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TeamUsersSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // ── Users ─────────────────────────────────────────────────────────────
        // Format: [ name, email, password, role ]
        $users = [
            // Admin
            ['Super Admin',     'admin@speedtraqr.com',              env('ADMIN_PASSWORD', 'password123'), 'super_admin'],

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
