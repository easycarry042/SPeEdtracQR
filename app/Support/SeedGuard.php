<?php

namespace App\Support;

use RuntimeException;

/**
 * Keeps development conveniences out of a real deployment.
 *
 * Before this existed, seeding a production database created
 * `admin@speedtraqr.com` with the password `password123` — a value written in
 * this repository — plus five staff accounts on `staff1234` and a
 * `test@example.com` user. Anyone who had read the code owned the system.
 *
 * Two rules, enforced here rather than trusted to whoever runs the deploy:
 *
 *  1. Outside local/testing, `ADMIN_PASSWORD` must be set to a real value, and
 *     the seeder aborts loudly if it is not. Failing the deploy is far cheaper
 *     than a municipality running on a published password.
 *  2. Demo accounts are seeded ONLY in local/testing. They are fixtures, not
 *     data, and they have no business existing on a live system.
 */
class SeedGuard
{
    /** Environments where demo fixtures and weak defaults are acceptable. */
    private const SAFE_ENVIRONMENTS = ['local', 'testing'];

    public static function isDevelopment(): bool
    {
        return app()->environment(self::SAFE_ENVIRONMENTS);
    }

    /**
     * The super admin's password, or an abort if a real one was not supplied.
     *
     * In local/testing this falls back to a known value so the suite and a
     * fresh clone keep working with no setup. Anywhere else, an unset or
     * obviously-placeholder password stops the seed.
     */
    public static function adminPassword(): string
    {
        $password = (string) config('app.admin_password', '');

        if (self::isDevelopment()) {
            return $password !== '' ? $password : 'password123';
        }

        if (trim($password) === '') {
            throw new RuntimeException(
                'ADMIN_PASSWORD is not set. Refusing to seed a super admin with a '
                .'default password outside local/testing. Set ADMIN_PASSWORD in .env '
                .'to a strong, unique value and run the seeder again.'
            );
        }

        // The values that have actually shipped in this repo's history, plus the
        // usual placeholders. Cheap to check, and catches a copy-pasted .env.
        $banned = ['password123', 'staff1234', 'password', 'secret', 'changeme', 'admin'];

        if (in_array(strtolower(trim($password)), $banned, true)) {
            throw new RuntimeException(
                'ADMIN_PASSWORD is set to a known default ("'.$password.'"). '
                .'Choose a password that has never appeared in this repository.'
            );
        }

        if (mb_strlen($password) < 12) {
            throw new RuntimeException(
                'ADMIN_PASSWORD must be at least 12 characters outside local/testing.'
            );
        }

        return $password;
    }

    /**
     * Whether to seed the demo staff accounts and the test user.
     *
     * Callers should skip quietly rather than abort — a production deploy
     * legitimately runs `db:seed` for roles, departments and route templates,
     * and should simply get no fixtures along with them.
     */
    public static function allowsDemoAccounts(): bool
    {
        return self::isDevelopment();
    }
}
