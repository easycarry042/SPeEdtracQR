<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records who closed a request and when. Denial previously stored only the
 * reason in `remarks`, so the citizen page had no way to say who decided —
 * the actor survived only as free text in the activity log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'decided_by')) {
                $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('documents', 'decided_at')) {
                $table->timestamp('decided_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'decided_by')) {
                $table->dropConstrainedForeignId('decided_by');
            }

            if (Schema::hasColumn('documents', 'decided_at')) {
                $table->dropColumn('decided_at');
            }
        });
    }
};
