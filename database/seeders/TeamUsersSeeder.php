<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use App\Support\SeedGuard;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * A believable municipal team for a development machine.
 *
 * Every department gets a Supervisor. That is not cosmetic: only a Supervisor
 * holds `act on internal requests`, so a department with nobody in it is a dead
 * end — an internal request routed there is endorsed, arrives, and can never be
 * approved, denied or returned by anyone. The seeded Procurement template runs
 * OM → BO → BAC → BAC → GSO, so leaving Budget or the BAC empty silently breaks
 * the flagship flow.
 *
 * Demo accounts only: SeedGuard stops this running outside local/testing.
 */
class TeamUsersSeeder extends Seeder
{
    /** Shared password for every demo account except the super admin. */
    private const DEMO_PASSWORD = 'staff1234';

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (! SeedGuard::allowsDemoAccounts()) {
            $this->command?->warn('Skipping the demo municipal team: not a local/testing environment.');

            return;
        }

        // Departments must exist before anyone can be attached to one.
        $this->callOnce(DepartmentSeeder::class);

        /**
         * [ name, email, role, department code (null = org-wide) ]
         *
         * One Supervisor per department, plus a couple of staff so the
         * assign-to-staff step has somewhere to land.
         */
        $users = [
            ['Super Admin',      'admin@speedtraqr.com',              'super_admin', null],

            // Department heads — one per office, so no route can dead-end.
            ['Ramon Bautista',   'om.head@speedtraqr.com',            'Supervisor',  'OM'],
            ['Elena Marquez',    'budget.head@speedtraqr.com',        'Supervisor',  'BO'],
            ['Teresa Lim',       'accounting.head@speedtraqr.com',    'Supervisor',  'ACC'],
            ['Noel Aguilar',     'bac.head@speedtraqr.com',           'Supervisor',  'BAC'],
            ['Grace Villanueva', 'treasury.head@speedtraqr.com',      'Supervisor',  'TRSY'],
            ['Arnel Domingo',    'engineering.head@speedtraqr.com',   'Supervisor',  'ENG'],
            ['Divina Ocampo',    'health.head@speedtraqr.com',        'Supervisor',  'MHO'],
            ['Maria Santos',     'maria.santos@speedtraqr.com',       'Supervisor',  'TRSM'],
            ['Jose Reyes',       'jose.reyes@speedtraqr.com',         'Supervisor',  'GSO'],
            ['Carlos Dela Cruz', 'carlos.delacruz@speedtraqr.com',    'Supervisor',  'HRMO'],

            // Staff — the people a Supervisor assigns citizen requests to.
            ['Ana Cruz',         'ana.cruz@speedtraqr.com',           'staff',       'TRSM'],
            ['Liza Reyes',       'liza.reyes@speedtraqr.com',         'staff',       'OM'],
            ['Paolo Mendoza',    'paolo.mendoza@speedtraqr.com',      'staff',       'GSO'],
            ['Rosa Ilagan',      'rosa.ilagan@speedtraqr.com',        'staff',       'ENG'],
        ];

        $departments = Department::pluck('id', 'code');

        foreach ($users as [$name, $email, $roleName, $code]) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            $password = $roleName === 'super_admin'
                ? SeedGuard::adminPassword()
                : self::DEMO_PASSWORD;

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => bcrypt($password),
                    'is_active' => true,
                    'department_id' => $code ? ($departments[$code] ?? null) : null,
                ]
            );

            $user->syncRoles([$role]);

            $this->command?->info('✓  '.str_pad($roleName, 12).str_pad($code ?? 'org-wide', 10)." {$email}  /  {$password}");
        }

        // A department with no Supervisor cannot act on an internal request, so
        // say so loudly rather than letting it surface as a stuck request weeks
        // later.
        $orphans = Department::whereDoesntHave('users', fn ($q) => $q->role('Supervisor'))->pluck('code');

        $this->command?->newLine();

        if ($orphans->isNotEmpty()) {
            $this->command?->warn('Departments with NO Supervisor (internal requests routed here will stall): '.$orphans->join(', '));
        } else {
            $this->command?->info('Every department has a Supervisor — no route can dead-end.');
        }
    }
}
