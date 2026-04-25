<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('routing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('document_type');
            $table->foreignId('from_department_id')->constrained('departments');
            $table->foreignId('to_department_id')->constrained('departments');
            $table->integer('step_order');
            $table->timestamps();

            $table->unique(['document_type', 'step_order']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('routing_rules');
    }
};