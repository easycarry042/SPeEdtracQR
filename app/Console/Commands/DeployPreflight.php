<?php

namespace App\Console\Commands;

use App\Support\SeedGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Refuses an unsafe deployment before it serves a request.
 *
 * Run this from the deploy script and let a non-zero exit stop the release:
 *
 *     php artisan deploy:preflight || exit 1
 *
 * Every check here exists because the misconfiguration it catches is both easy
 * to make and expensive to discover in production — `APP_DEBUG=true` renders
 * stack traces containing database credentials on any error, and a seeded
 * default admin password is a published credential on a government system.
 */
class DeployPreflight extends Command
{
    protected $signature = 'deploy:preflight {--allow-debug : Skip the APP_DEBUG check (never use on a public host)}';

    protected $description = 'Verify the environment is safe to serve production traffic';

    /** @var array<int, string> */
    private array $failures = [];

    /** @var array<int, string> */
    private array $warnings = [];

    public function handle(): int
    {
        $this->info('Deploy preflight — '.app()->environment());
        $this->newLine();

        $this->checkDebug();
        $this->checkAppKey();
        $this->checkAdminPassword();
        $this->checkSession();
        $this->checkHttps();
        $this->checkDatabase();
        $this->checkSecurityHeaders();

        $this->newLine();

        foreach ($this->warnings as $warning) {
            $this->warn('  ! '.$warning);
        }

        if ($this->failures !== []) {
            foreach ($this->failures as $failure) {
                $this->error('  ✗ '.$failure);
            }
            $this->newLine();
            $this->error('Preflight FAILED — '.count($this->failures).' blocking issue(s). Deployment should stop.');

            return self::FAILURE;
        }

        $this->info('Preflight passed. Safe to serve.');

        return self::SUCCESS;
    }

    private function addFailure(string $message): void
    {
        $this->failures[] = $message;
    }

    private function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    /** Debug mode leaks stack traces, env values and DB credentials to visitors. */
    private function checkDebug(): void
    {
        if (SeedGuard::isDevelopment()) {
            $this->line('  · APP_DEBUG not checked (local/testing)');

            return;
        }

        if (config('app.debug') && ! $this->option('allow-debug')) {
            $this->addFailure('APP_DEBUG is true outside local/testing. Any error will expose stack traces and database credentials. Set APP_DEBUG=false.');

            return;
        }

        $this->line('  ✓ APP_DEBUG is off');
    }

    private function checkAppKey(): void
    {
        if (trim((string) config('app.key')) === '') {
            $this->addFailure('APP_KEY is empty. Sessions, encrypted cookies and the QR authenticity seal all depend on it. Run `php artisan key:generate`.');

            return;
        }

        $this->line('  ✓ APP_KEY is set');
    }

    private function checkAdminPassword(): void
    {
        if (SeedGuard::isDevelopment()) {
            $this->line('  · ADMIN_PASSWORD not checked (local/testing)');

            return;
        }

        try {
            SeedGuard::adminPassword();
            $this->line('  ✓ ADMIN_PASSWORD is set and non-default');
        } catch (Throwable $e) {
            $this->addFailure($e->getMessage());
        }
    }

    private function checkSession(): void
    {
        if (SeedGuard::isDevelopment()) {
            return;
        }

        if (! config('session.encrypt')) {
            $this->addWarning('SESSION_ENCRYPT is false. Session payloads are stored unencrypted.');
        }

        if ((int) config('session.lifetime') > 60) {
            $this->addWarning('SESSION_LIFETIME is '.config('session.lifetime').' minutes. Shared counter terminals warrant something shorter.');
        }

        if (! config('session.secure') && str_starts_with((string) config('app.url'), 'https://')) {
            $this->addFailure('APP_URL is https but SESSION_SECURE_COOKIE is not enabled — the session cookie can be sent over plain HTTP.');
        }
    }

    private function checkHttps(): void
    {
        if (SeedGuard::isDevelopment()) {
            return;
        }

        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $this->addFailure('APP_URL is not https. Signed verification links, HSTS and secure cookies all assume TLS.');

            return;
        }

        $this->line('  ✓ APP_URL is https');
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            $this->line('  ✓ Database reachable');
        } catch (Throwable $e) {
            $this->addFailure('Cannot connect to the database: '.$e->getMessage());
        }
    }

    private function checkSecurityHeaders(): void
    {
        if (! config('security.csp.enabled')) {
            $this->addWarning('Content-Security-Policy is disabled in config/security.php.');
        }

        $policy = (string) config('security.permissions_policy');

        // The QR scanners call getUserMedia(); without this they fail silently.
        if (! str_contains($policy, 'camera=(self)')) {
            $this->addFailure('Permissions-Policy does not allow camera=(self) — every QR scan flow will break.');

            return;
        }

        $this->line('  ✓ Security headers configured (camera allowed for QR scanning)');
    }
}
