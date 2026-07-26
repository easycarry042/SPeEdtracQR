<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enable Row-Level Security on every table in the public schema.
 *
 * Supabase exposes the `public` schema through its REST API (reachable with the
 * public anon key). With RLS disabled, those tables — including users.password
 * and password_reset_tokens.token — are readable/writable by anyone holding that
 * key. Enabling RLS with NO policies denies the anon/authenticated API roles
 * entirely; the Laravel app is unaffected because it connects as the table
 * owner (postgres), which bypasses RLS.
 *
 * Postgres-only: guarded so it is a no-op on the SQLite test DB / MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DO $$
            DECLARE r RECORD;
            BEGIN
                FOR r IN
                    SELECT tablename FROM pg_tables WHERE schemaname = 'public'
                LOOP
                    EXECUTE format('ALTER TABLE public.%I ENABLE ROW LEVEL SECURITY;', r.tablename);
                END LOOP;
            END $$;
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DO $$
            DECLARE r RECORD;
            BEGIN
                FOR r IN
                    SELECT tablename FROM pg_tables WHERE schemaname = 'public'
                LOOP
                    EXECUTE format('ALTER TABLE public.%I DISABLE ROW LEVEL SECURITY;', r.tablename);
                END LOOP;
            END $$;
        SQL);
    }
};
