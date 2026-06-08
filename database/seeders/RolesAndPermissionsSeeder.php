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
            'manage system',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        $staffRole->syncPermissions(['create documents', 'scan documents']);

        $receivingRole = Role::firstOrCreate(['name' => 'receiving_staff']);
        $receivingRole->syncPermissions(['scan documents']);

        $deptAdminRole = Role::firstOrCreate(['name' => 'department_admin']);
        $deptAdminRole->syncPermissions([
            'create documents', 'scan documents', 'view reports',
            'manage users', 'view all documents',
        ]);

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdminRole->syncPermissions(Permission::pluck('name')->all());

        $admin = User::firstOrCreate(
            ['email' => 'admin@speedtraqr.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt(env('ADMIN_PASSWORD', 'password123')),
                'is_active' => true,
            ]
        );

        $admin->syncRoles(['super_admin']);
    }
}
