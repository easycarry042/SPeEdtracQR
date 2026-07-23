<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hop-flow columns: `started_at` anchors the per-hop aging clock (set when the
 * step becomes current); `signature_path` stores the approving supervisor's
 * signature as a frozen copy taken at approval time, so later changes to their
 * registered signature never rewrite history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_steps', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('acted_at');
            $table->string('signature_path')->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('request_steps', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'signature_path']);
        });
    }
};
