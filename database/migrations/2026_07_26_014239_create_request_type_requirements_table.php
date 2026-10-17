<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The configurable requirement checklist for a request type — e.g. a Business
 * Permit needs a Barangay Business Clearance, Cedula, etc. Admin-managed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_type_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_type_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->boolean('is_mandatory')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_type_requirements');
    }
};
