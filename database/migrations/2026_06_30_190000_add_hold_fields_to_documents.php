<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "On Hold / Waiting" capability: a document can be parked (blocked through no
 * fault of the assignee) with a reason, a who's-blocking tag, an optional
 * reschedule date, and enough state to RESTORE the prior stage on un-hold.
 *
 * Additive + nullable only — safe on the shared Supabase Postgres. `status`
 * stays a varchar, so the new 'on_hold' value needs no schema change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $t) {
            if (! Schema::hasColumn('documents', 'status_before_hold')) {
                $t->string('status_before_hold')->nullable();
            }
            if (! Schema::hasColumn('documents', 'hold_reason')) {
                $t->text('hold_reason')->nullable();
            }
            if (! Schema::hasColumn('documents', 'hold_until')) {
                $t->date('hold_until')->nullable();
            }
            if (! Schema::hasColumn('documents', 'blocked_by')) {
                // 'citizen' | 'internal' | 'external' — validated in the app, not a DB enum.
                $t->string('blocked_by')->nullable();
            }
            if (! Schema::hasColumn('documents', 'held_at')) {
                $t->timestamp('held_at')->nullable();
            }
            if (! Schema::hasColumn('documents', 'held_by')) {
                $t->foreignId('held_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $t) {
            $t->dropConstrainedForeignId('held_by');
            $t->dropColumn(['status_before_hold', 'hold_reason', 'hold_until', 'blocked_by', 'held_at']);
        });
    }
};
