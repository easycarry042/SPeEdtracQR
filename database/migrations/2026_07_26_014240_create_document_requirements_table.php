<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A requirement attached to a specific submitted request. `label` / `is_mandatory`
 * are snapshotted at submission so later catalog edits don't rewrite history. The
 * citizen may optionally upload a file; staff record verification against the
 * physical original.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_type_requirement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->boolean('is_mandatory')->default(true);
            $table->string('uploaded_file_path')->nullable(); // private disk
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_requirements');
    }
};
