<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('department_id'));
        Schema::table('documents', fn (Blueprint $t) => $t->dropColumn('current_department_id'));
        Schema::table('document_scans', fn (Blueprint $t) => $t->dropColumn('department_id'));
        Schema::table('document_route_steps', fn (Blueprint $t) => $t->dropColumn('department_id'));
        Schema::table('routing_rules', fn (Blueprint $t) => $t->dropColumn(['from_department_id', 'to_department_id']));
        Schema::table('document_attachments', fn (Blueprint $t) => $t->dropColumn('department_id'));
        Schema::table('department_notifications', fn (Blueprint $t) => $t->dropColumn('department_id'));
    }

    public function down(): void
    {
        //
    }
};
