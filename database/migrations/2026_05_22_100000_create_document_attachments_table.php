<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('file_path');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('document_scans', function (Blueprint $table) {
            if (! Schema::hasColumn('document_scans', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('synced');
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_scans', function (Blueprint $table) {
            if (Schema::hasColumn('document_scans', 'attachment_path')) {
                $table->dropColumn('attachment_path');
            }
        });

        Schema::dropIfExists('document_attachments');
    }
};
