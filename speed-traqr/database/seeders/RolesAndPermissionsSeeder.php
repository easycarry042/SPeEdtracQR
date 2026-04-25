<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'create documents',
            'scan documents',
            'view reports',
            'manage users',
            'view all documents',
            'delete documents',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $clerkRole = Role::firstOrCreate(['name' => 'clerk']);
        $clerkRole->syncPermissions(['create documents', 'scan documents']);

        $departmentHeadRole = Role::firstOrCreate(['name' => 'department_head']);
        $departmentHeadRole->syncPermissions(['scan documents', 'view reports']);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->syncPermissions(Permission::pluck('name')->all());

        $admin = User::firstOrCreate(
            ['email' => 'admin@speedtraqr.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt('password123'),
            ]
        );

        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }
    }
}