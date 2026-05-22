<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_route_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments');
            $table->unsignedSmallInteger('step_order');
            $table->timestamps();

            $table->unique(['document_id', 'step_order']);
            $table->unique(['document_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_route_steps');
    }
};
