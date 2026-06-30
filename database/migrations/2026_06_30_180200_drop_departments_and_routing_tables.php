<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 3 of department removal: drop the fully-retired routing/department-era
 * tables, then the departments table itself. The dependent tables (which hold
 * FKs to departments) are dropped first so departments can then be removed.
 *
 * document_scans is dropped here too: IN/OUT scan recording is retired and the
 * /scan page is now a QR-lookup tool only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('department_notifications');
        Schema::dropIfExists('document_scans');
        Schema::dropIfExists('document_route_steps');
        Schema::dropIfExists('routing_rules');
        Schema::dropIfExists('departments');
    }

    public function down(): void
    {
        // Minimal structural restore only (no data, no FKs to departments where
        // the parent ordering would be circular). Enough for migrate:rollback.
        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code')->nullable();
            $t->string('email')->nullable();
            $t->integer('sla_hours')->nullable();
            $t->timestamps();
        });

        Schema::create('routing_rules', function (Blueprint $t) {
            $t->id();
            $t->string('document_type');
            $t->unsignedBigInteger('from_department_id')->nullable();
            $t->unsignedBigInteger('to_department_id')->nullable();
            $t->integer('step_order')->default(1);
            $t->timestamps();
        });

        Schema::create('document_route_steps', function (Blueprint $t) {
            $t->id();
            $t->foreignId('document_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('department_id')->nullable();
            $t->integer('step_order')->default(1);
            $t->timestamps();
        });

        Schema::create('document_scans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('document_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('department_id')->nullable();
            $t->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action')->nullable();
            $t->uuid('offline_uuid')->nullable();
            $t->timestamp('scanned_at')->nullable();
            $t->timestamps();
        });

        Schema::create('department_notifications', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('department_id')->nullable();
            $t->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type')->nullable();
            $t->string('message')->nullable();
            $t->integer('file_count')->default(0);
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
    }
};
