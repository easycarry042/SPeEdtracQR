<?php

namespace Tests\Feature;

use App\Support\SeedGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Guards the guard. Seeding a production database used to create a super admin
 * with `password123` — a value published in this repository — so these rules
 * are worth pinning.
 */
class SeedGuardTest extends TestCase
{
    use RefreshDatabase;

    /** Local and testing keep the convenient fallback so a fresh clone works. */
    public function test_development_environments_fall_back_to_the_dev_password(): void
    {
        config(['app.admin_password' => '']);

        $this->assertTrue(SeedGuard::isDevelopment());
        $this->assertSame('password123', SeedGuard::adminPassword());
        $this->assertTrue(SeedGuard::allowsDemoAccounts());
    }

    public function test_production_refuses_a_blank_admin_password(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config(['app.admin_password' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ADMIN_PASSWORD is not set');

        SeedGuard::adminPassword();
    }

    /**
     * The specific values that have shipped in this repo's history must never
     * be accepted — a copy-pasted .env is the likely failure mode.
     */
    public function test_production_refuses_passwords_published_in_this_repository(): void
    {
        app()->detectEnvironment(fn () => 'production');

        foreach (['password123', 'staff1234', 'changeme'] as $banned) {
            config(['app.admin_password' => $banned]);

            try {
                SeedGuard::adminPassword();
                $this->fail("SeedGuard accepted the known default [{$banned}].");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('known default', $e->getMessage());
            }
        }
    }

    public function test_production_refuses_a_short_password(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config(['app.admin_password' => 'Sh0rt!']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('at least 12 characters');

        SeedGuard::adminPassword();
    }

    public function test_production_accepts_a_strong_password(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config(['app.admin_password' => 'k9$Tarlac-Bayan-2026']);

        $this->assertSame('k9$Tarlac-Bayan-2026', SeedGuard::adminPassword());
    }

    /** Demo fixtures are for laptops, never for a live municipality. */
    public function test_production_does_not_allow_demo_accounts(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->assertFalse(SeedGuard::isDevelopment());
        $this->assertFalse(SeedGuard::allowsDemoAccounts());
    }
}
