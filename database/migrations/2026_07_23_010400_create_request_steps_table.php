<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The materialized endorsement chain of one internal request: the ordered
 * hops it must make between offices (resolved from a route template at
 * creation, editable before submission). Each hop records who acted, when,
 * and the outcome; signatures attach in the approval phase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('action', 100);
            $table->string('status', 20)->default('pending');
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acted_at')->nullable();
            $table->string('remarks', 500)->nullable();
            $table->timestamps();

            $table->index(['document_id', 'step_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_steps');
    }
};
