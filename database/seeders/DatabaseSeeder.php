<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\SeedGuard;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Structural data — safe, and required, in every environment.
        $this->call([
            RolesAndPermissionsSeeder::class,
            DepartmentSeeder::class,
            RouteTemplateSeeder::class,
        ]);

        // A throwaway account with a factory password. Fine on a laptop, an
        // unnecessary live credential anywhere else.
        if (SeedGuard::allowsDemoAccounts()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }
    }
}
