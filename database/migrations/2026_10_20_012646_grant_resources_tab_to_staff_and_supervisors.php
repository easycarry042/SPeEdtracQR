<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The Resources tab (`inventory.index`) is behind 'manage inventory', which
     * was added to RolesAndPermissionsSeeder but never reached databases that
     * were seeded before it — so staff and supervisors never saw the tab.
     * Granting it here makes `php artisan migrate` enough; no reseed needed.
     */
    public function up(): void
    {
        Permission::findOrCreate('manage inventory', 'web');

        Role::whereIn('name', ['staff', 'Supervisor', 'super_admin'])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo('manage inventory'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::whereIn('name', ['staff', 'Supervisor'])
            ->get()
            ->each(fn (Role $role) => $role->revokePermissionTo('manage inventory'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
