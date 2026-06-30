<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracking-model change: documents are now advanced by an assigned staff member
 * moving them manually through status stages (see App\Enums\DocumentStatus),
 * not by department-to-department QR scans.
 *
 * Additive + nullable only — safe on the shared Supabase Postgres. No drops,
 * no type changes. `after()` is intentionally omitted (Postgres has no column
 * positioning; Laravel's pgsql grammar ignores it anyway).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // ASSIGNMENT — single primary staff member responsible for advancing the file.
            if (! Schema::hasColumn('documents', 'assigned_to')) {
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('documents', 'assigned_by')) {
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('documents', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable();
            }

            // STATUS TIMING — anchors the SLA clock to "time in current stage".
            if (! Schema::hasColumn('documents', 'status_changed_at')) {
                $table->timestamp('status_changed_at')->nullable();
            }
        });

        // Index added separately so the hasColumn guards above stay simple.
        Schema::table('documents', function (Blueprint $table) {
            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['assigned_to']);
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropConstrainedForeignId('assigned_by');
            $table->dropColumn(['assigned_at', 'status_changed_at']);
        });
    }
};
