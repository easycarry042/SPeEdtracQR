<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Markers used by the scheduled SLA sweep (documents:check-sla) to avoid
 * re-sending the same warning/breach email. Reset whenever a document checks
 * in at a new department, so the SLA clock and notifications restart per stay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->timestamp('sla_warning_notified_at')->nullable();
            $table->timestamp('sla_breach_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['sla_warning_notified_at', 'sla_breach_notified_at']);
        });
    }
};
