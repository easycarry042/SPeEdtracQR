<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-defined route templates prefill the endorsement chain when a
 * supervisor creates an internal request. Steps carry an optional `condition`
 * so one template can branch (e.g. Budget certification only when an amount
 * is attached; BAC bidding vs small-value procurement around the ₱2M line).
 * Two steps may share a step_order when their conditions are mutually
 * exclusive — resolution picks whichever applies to the request's amount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('route_template_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('action', 100);
            $table->string('condition', 30)->nullable();
            $table->timestamps();

            $table->index(['route_template_id', 'step_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_template_steps');
        Schema::dropIfExists('route_templates');
    }
};
