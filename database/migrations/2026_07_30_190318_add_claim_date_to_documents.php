<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The claiming day staff set when advancing a request — the date the citizen
 * comes to collect. A date, not a timestamp: offices promise a day, not a time,
 * and the citizen sees it on the public tracking page.
 *
 * Distinct from `claimed_at`, which records when it was actually collected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->date('claim_date')->nullable()->after('claimed_at');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('claim_date');
        });
    }
};
