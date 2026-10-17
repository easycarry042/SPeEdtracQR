<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dedup marker for the hold backstop sweep (documents:check-holds).
 *
 * A hold PAUSES the SLA clock, which means a parked document is invisible to
 * isOverdue(), to documents:check-sla and to the at-risk dashboard. This column
 * lets a daily sweep chase holds that have run past their own hold_until (or
 * past the open-ended staleness window) without re-emailing on every run.
 *
 * Additive + nullable only — safe on the shared Supabase Postgres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $t) {
            if (! Schema::hasColumn('documents', 'hold_reminder_sent_at')) {
                $t->timestamp('hold_reminder_sent_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $t) {
            $t->dropColumn('hold_reminder_sent_at');
        });
    }
};
