<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Internal requests can travel without paper. A soft-copy request has no
     * folder to carry or scan, so the filing office names the person who
     * receives it at each office instead.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Every request filed so far came with paper.
            $table->boolean('has_hard_copy')->default(true)->after('origin');
        });

        Schema::table('request_steps', function (Blueprint $table) {
            $table->foreignId('recipient_id')->nullable()->after('department_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('request_steps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recipient_id');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('has_hard_copy');
        });
    }
};
