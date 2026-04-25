<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            if (! Schema::hasColumn('departments', 'name')) {
                $table->string('name')->unique();
            }

            if (! Schema::hasColumn('departments', 'email')) {
                $table->string('email')->nullable();
            }

            if (! Schema::hasColumn('departments', 'sla_hours')) {
                $table->integer('sla_hours')->default(48);
            }

            if (! Schema::hasColumn('departments', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('routing_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('routing_rules', 'document_type')) {
                $table->string('document_type');
            }

            if (! Schema::hasColumn('routing_rules', 'from_department_id')) {
                $table->foreignId('from_department_id')->nullable()->constrained('departments');
            }

            if (! Schema::hasColumn('routing_rules', 'to_department_id')) {
                $table->foreignId('to_department_id')->nullable()->constrained('departments');
            }

            if (! Schema::hasColumn('routing_rules', 'step_order')) {
                $table->integer('step_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('routing_rules', function (Blueprint $table) {
            if (Schema::hasColumn('routing_rules', 'to_department_id')) {
                $table->dropConstrainedForeignId('to_department_id');
            }

            if (Schema::hasColumn('routing_rules', 'from_department_id')) {
                $table->dropConstrainedForeignId('from_department_id');
            }

            if (Schema::hasColumn('routing_rules', 'document_type')) {
                $table->dropColumn('document_type');
            }

            if (Schema::hasColumn('routing_rules', 'step_order')) {
                $table->dropColumn('step_order');
            }
        });

        Schema::table('departments', function (Blueprint $table) {
            if (Schema::hasColumn('departments', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            if (Schema::hasColumn('departments', 'sla_hours')) {
                $table->dropColumn('sla_hours');
            }

            if (Schema::hasColumn('departments', 'email')) {
                $table->dropColumn('email');
            }

            if (Schema::hasColumn('departments', 'name')) {
                $table->dropUnique(['name']);
                $table->dropColumn('name');
            }
        });
    }
};
