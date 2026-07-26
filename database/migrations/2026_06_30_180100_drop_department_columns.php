<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 2 of department removal: drop the department columns from the SURVIVING
 * tables. `documents.current_department_id` is part of the composite index
 * documents_status_current_department_id_index, which MUST be dropped first
 * (sqlite/pgsql refuse to drop an indexed column) — then we re-add an index on
 * status alone. The retired tables are dropped wholesale in step 3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $t) {
            if (Schema::hasColumn('documents', 'current_department_id')) {
                $t->dropIndex('documents_status_current_department_id_index');
                $t->dropColumn('current_department_id');
                $t->index('status');
            }
        });

        if (Schema::hasColumn('users', 'department_id')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('department_id'));
        }

        if (Schema::hasColumn('document_attachments', 'department_id')) {
            Schema::table('document_attachments', fn (Blueprint $t) => $t->dropColumn('department_id'));
        }
    }

    public function down(): void
    {
        // Recreated as plain nullable columns (no departments FK — that table is gone).
        Schema::table('documents', function (Blueprint $t) {
            $t->dropIndex(['status']);
            $t->unsignedBigInteger('current_department_id')->nullable();
            $t->index(['status', 'current_department_id']);
        });
        Schema::table('users', fn (Blueprint $t) => $t->unsignedBigInteger('department_id')->nullable());
        Schema::table('document_attachments', fn (Blueprint $t) => $t->unsignedBigInteger('department_id')->nullable());
    }
};
