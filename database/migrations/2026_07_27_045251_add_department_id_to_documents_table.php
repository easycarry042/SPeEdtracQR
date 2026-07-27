<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The department currently handling a ticket. Stamped at submission from the
     * request type's department; a Supervisor of that department then assigns a
     * staff member. Drives the THED ID / Department tracking columns and the
     * department-scoped supervisor queue.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->foreignId('department_id')
                ->nullable()
                ->after('assigned_by')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
