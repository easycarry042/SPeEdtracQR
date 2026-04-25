<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number')->unique();
            $table->string('document_type'); // e.g., Business Permit, Clearance
            $table->string('citizen_name')->nullable();
            $table->string('status')->default('pending'); // pending, in_transit, completed, archived
            $table->foreignId('current_department_id')->nullable()->constrained('departments');
            $table->foreignId('created_by')->constrained('users');
            $table->text('remarks')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'current_department_id']);
            $table->index('tracking_number');
        });
    }

    public function down()
    {
        Schema::dropIfExists('documents');
    }
};