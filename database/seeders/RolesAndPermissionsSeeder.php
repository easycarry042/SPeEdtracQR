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
            'create internal requests', // draft/file dept-to-dept requests (staff + supervisor)
            'act on internal requests', // approve/deny/return hops of internal requests
            'scan documents',
            'advance documents', // move an assigned document through its status stages
            'assign documents',  // assign the staff member responsible for a document
            'accept documents',  // staff self-accept from the unclaimed queue
            'manage bookings',   // staff manage resource reservations (approve/reschedule/cancel)
            'view reports',
            'manage users',
            'manage system',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Prune permissions that no longer gate anything. 'view all documents'
        // and 'delete documents' were never checked in code — visibility is
        // decided by App\Support\DepartmentScope/AssignmentScope, and documents
        // are soft-deleted through the edit flow. Leaving them assignable made
        // the role matrix look like it granted powers that did not exist.
        Permission::whereNotIn('name', $permissions)->delete();

        // Staff process and manage assigned requests; they no longer CREATE
        // requests — only guests (the public) submit, via the public request form.
        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        // Staff may draft internal requests for their office; only a Supervisor
        // (department head) can endorse/forward them ('act on internal requests').
        $staffRole->syncPermissions(['scan documents', 'advance documents', 'accept documents', 'manage bookings', 'create internal requests']);

        // receiving_staff has been folded into staff. Migrate any remaining users,
        // then drop the role so only Guest (public), Staff, Supervisor, and Super
        // Admin remain.
        $legacyReceivingRole = Role::where('name', 'receiving_staff')->first();
        if ($legacyReceivingRole) {
            foreach ($legacyReceivingRole->users as $user) {
                $user->syncRoles(['staff']);
            }
            $legacyReceivingRole->delete();
        }

        $legacyDeptAdminRole = Role::where('name', 'department_admin')->first();
        $supervisorRole = Role::firstOrCreate(['name' => 'Supervisor']);
        $supervisorRole->syncPermissions([
            'assign documents', 'view reports',
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

        // Prune any legacy/stray roles outside the canonical set (e.g. old
        // "admin", "clerk", "department_head" roles). Guests are the
        // unauthenticated public (no role); the only login roles are Staff,
        // Supervisor, and Super Admin. Migrate any lingering users down to staff
        // (least privilege) before removing the role.
        $canonicalRoles = ['staff', 'Supervisor', 'super_admin'];
        foreach (Role::whereNotIn('name', $canonicalRoles)->get() as $legacyRole) {
            foreach ($legacyRole->users as $user) {
                $user->syncRoles(['staff']);
            }
            $legacyRole->delete();
        }

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
