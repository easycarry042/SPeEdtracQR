<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('department_notifications');
        Schema::dropIfExists('document_route_steps');
        Schema::dropIfExists('routing_rules');
        Schema::dropIfExists('departments');
    }

    public function down(): void
    {
        //
    }
};
