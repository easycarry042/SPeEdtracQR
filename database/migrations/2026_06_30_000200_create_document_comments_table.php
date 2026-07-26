<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 — per-document staff collaboration feed.
 *
 * One unified timeline per document: staff posts + replies, plus system events
 * (status changes, assignment, returns) recorded as author_type='system'.
 * visibility splits staff-only 'internal' notes from citizen-facing 'public'
 * posts. New additive table — safe on Postgres and MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_type')->default('staff'); // staff | citizen | system
            $table->text('body');
            $table->string('visibility')->default('internal'); // internal | public
            $table->foreignId('parent_id')->nullable()->constrained('document_comments')->nullOnDelete();
            $table->timestamps();

            $table->index(['document_id', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_comments');
    }
};
