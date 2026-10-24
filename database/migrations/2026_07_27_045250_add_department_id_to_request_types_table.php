<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each request type is handled by one department. A ticket of this type is
     * auto-routed to that department's queue on submission, where the
     * department's Supervisor assigns a staff member.
     */
    public function up(): void
    {
        Schema::table('request_types', function (Blueprint $table): void {
            $table->foreignId('department_id')
                ->nullable()
                ->after('kind')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('request_types', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
