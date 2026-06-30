<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 1 of department removal: drop the foreign keys on the SURVIVING tables so
 * their department columns can be dropped next (step 2). The fully-retired tables
 * (document_scans, document_route_steps, routing_rules, department_notifications)
 * keep their FKs for now — they are dropped wholesale in step 3, which removes
 * those FKs with them. Guarded so it is safe on partial state / re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dropForeignIfExists('documents', 'current_department_id');
        $this->dropForeignIfExists('users', 'department_id');
        $this->dropForeignIfExists('document_attachments', 'department_id');
    }

    public function down(): void
    {
        // FKs to departments are not restored: departments no longer exists. The
        // down() of step 2 recreates these as plain nullable columns.
    }

    private function dropForeignIfExists(string $table, string $column): void
    {
        if (Schema::hasColumn($table, $column)) {
            Schema::table($table, fn (Blueprint $t) => $t->dropForeign([$column]));
        }
    }
};
