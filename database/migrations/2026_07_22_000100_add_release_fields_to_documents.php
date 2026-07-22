<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * QR-gated release: a Completed document is claimed at the counter by the
 * citizen presenting their QR stub; staff scan it and mark the release.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->timestamp('claimed_at')->nullable()->after('accepted_at');
            $table->foreignId('released_by')->nullable()->after('claimed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('released_by');
            $table->dropColumn('claimed_at');
        });
    }
};
