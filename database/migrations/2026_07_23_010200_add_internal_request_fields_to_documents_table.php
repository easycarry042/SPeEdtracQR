<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Internal dept-to-dept requests reuse the documents engine (QR, custody,
 * SLA, activity log). `origin` separates the two worlds: 'external' citizen
 * tickets keep their existing flow; 'internal' requests carry a requesting
 * department and an optional peso amount that drives the procurement branch
 * (≥ ₱2M → public bidding under RA 12009).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('origin', 20)->default('external')->after('source')->index();
            $table->foreignId('requesting_department_id')->nullable()->after('origin')
                ->constrained('departments')->nullOnDelete();
            $table->decimal('amount', 14, 2)->nullable()->after('requesting_department_id');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requesting_department_id');
            $table->dropColumn(['origin', 'amount']);
        });
    }
};
