<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Clear Spatie's permission cache before seeding
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = ['admin', 'staff'];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name'       => $role,
                'guard_name' => 'web',
            ]);
        }

        $this->command->info('Roles seeded: ' . implode(', ', $roles));
    }
}
