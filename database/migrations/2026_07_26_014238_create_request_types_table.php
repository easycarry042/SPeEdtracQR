<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-configurable catalog of public request types. Replaces the hardcoded
 * DocumentFormOptions list. `kind` distinguishes document/permit requests (which
 * carry supporting requirements) from booking requests (which reserve a resource
 * for a date). Phase 1 seeds only `document` kinds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('kind')->default('document'); // 'document' | 'booking'
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_types');
    }
};
