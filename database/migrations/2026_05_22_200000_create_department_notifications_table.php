<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('type', 50)->default('citizen_upload');
            $table->string('message');
            $table->unsignedSmallInteger('file_count')->default(1);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_notifications');
    }
};
