<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — citizen self-service tickets.
 *
 * A citizen "ticket" IS a document; this just records where it came from and
 * how to reach the citizen by email. Additive + nullable only (safe on the
 * shared Supabase Postgres and local MySQL). No `after()` (Postgres ignores it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'source')) {
                $table->string('source')->default('walk_in'); // walk_in | online
            }
            if (! Schema::hasColumn('documents', 'citizen_email')) {
                $table->string('citizen_email')->nullable();
            }
            if (! Schema::hasColumn('documents', 'notify_citizen')) {
                $table->boolean('notify_citizen')->default(true); // per-ticket email opt-out
            }
        });

        // Online tickets are created by the citizen (no staff user), so relax the
        // existing NOT NULL on created_by. This only loosens a constraint — safe
        // and non-destructive. The FK to users is preserved.
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['source', 'citizen_email', 'notify_citizen']);
        });
    }
};
