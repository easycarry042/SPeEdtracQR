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
            'create internal requests', // supervisor-only dept-to-dept requests
            'act on internal requests', // approve/deny/return hops of internal requests
            'scan documents',
            'advance documents', // move an assigned document through its status stages
            'assign documents',  // assign the staff member responsible for a document
            'accept documents',  // staff self-accept from the unclaimed queue
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
        $staffRole->syncPermissions(['create documents', 'scan documents', 'advance documents', 'accept documents']);

        $receivingRole = Role::firstOrCreate(['name' => 'receiving_staff']);
        $receivingRole->syncPermissions(['scan documents', 'accept documents']);

        $legacyDeptAdminRole = Role::where('name', 'department_admin')->first();
        $supervisorRole = Role::firstOrCreate(['name' => 'Supervisor']);
        $supervisorRole->syncPermissions([
            'assign documents', 'view reports', 'view all documents',
            'create internal requests', 'act on internal requests',
        ]);
        if ($legacyDeptAdminRole) {
            foreach ($legacyDeptAdminRole->users as $user) {
                $user->syncRoles(['Supervisor']);
            }
            $legacyDeptAdminRole->delete();
        }

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
