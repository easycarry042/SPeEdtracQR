<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'tracking_number')) {
                $table->string('tracking_number')->nullable();
            }

            if (! Schema::hasColumn('documents', 'document_type')) {
                $table->string('document_type')->nullable();
            }

            if (! Schema::hasColumn('documents', 'citizen_name')) {
                $table->string('citizen_name')->nullable();
            }

            if (! Schema::hasColumn('documents', 'status')) {
                $table->string('status')->default('pending');
            }

            if (! Schema::hasColumn('documents', 'current_department_id')) {
                $table->unsignedBigInteger('current_department_id')->nullable();
            }

            if (! Schema::hasColumn('documents', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable();
            }

            if (! Schema::hasColumn('documents', 'remarks')) {
                $table->text('remarks')->nullable();
            }

            if (! Schema::hasColumn('documents', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }

            if (! Schema::hasColumn('documents', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('document_scans', function (Blueprint $table) {
            if (! Schema::hasColumn('document_scans', 'document_id')) {
                $table->unsignedBigInteger('document_id')->nullable();
            }

            if (! Schema::hasColumn('document_scans', 'scanned_by')) {
                $table->unsignedBigInteger('scanned_by')->nullable();
            }

            if (! Schema::hasColumn('document_scans', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable();
            }

            if (! Schema::hasColumn('document_scans', 'action')) {
                $table->string('action')->nullable();
            }

            if (! Schema::hasColumn('document_scans', 'scanned_at')) {
                $table->timestamp('scanned_at')->nullable();
            }

            if (! Schema::hasColumn('document_scans', 'location_ip')) {
                $table->string('location_ip')->nullable();
            }

            if (! Schema::hasColumn('document_scans', 'synced')) {
                $table->boolean('synced')->default(true);
            }
        });
    }

    public function down(): void
    {
        // Intentionally left empty for local schema repair migration.
    }
};
