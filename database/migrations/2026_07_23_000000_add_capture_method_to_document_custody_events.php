<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custody events now record HOW they were captured: 'scan' (the folder's QR
 * was decoded live and matched server-side) or 'manual' (audited override for
 * a torn/unreadable QR, with a required reason).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_custody_events', function (Blueprint $table) {
            $table->string('capture_method')->default('scan')->after('user_id');
            $table->string('override_reason', 500)->nullable()->after('capture_method');
        });
    }

    public function down(): void
    {
        Schema::table('document_custody_events', function (Blueprint $table) {
            $table->dropColumn(['capture_method', 'override_reason']);
        });
    }
};
