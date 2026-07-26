<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data backfill for the new status model (App\Enums\DocumentStatus).
 *
 * Legacy rows used the routing-era value `in_transit`; the new canonical key
 * for that stage is `in_progress`. This is an idempotent data UPDATE (safe to
 * re-run, fully reversible) — not a schema change.
 *
 * Also seeds status_changed_at for existing rows so the re-anchored SLA clock
 * has a sensible starting point instead of NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('documents')->where('status', 'in_transit')->update(['status' => 'in_progress']);

        // Give pre-existing rows a status anchor: prefer the last update, fall back to creation.
        DB::statement('UPDATE documents SET status_changed_at = COALESCE(updated_at, created_at) WHERE status_changed_at IS NULL');
    }

    public function down(): void
    {
        DB::table('documents')->where('status', 'in_progress')->update(['status' => 'in_transit']);
    }
};
