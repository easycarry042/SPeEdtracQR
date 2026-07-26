<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sticky, lifetime SLA-breach marker. Unlike sla_breach_notified_at (which the
 * per-stage flow resets), this is stamped once the first time a document breaches
 * any stage's SLA and is never cleared — so the staff "On-time / SLA rate" KPI
 * can ask "did this document ever breach?". Additive + nullable (PG/MySQL safe).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'sla_breached_at')) {
                $table->timestamp('sla_breached_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('sla_breached_at');
        });
    }
};
