<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('departments', function (Blueprint $table) {
            if (! Schema::hasColumn('departments', 'sla_hours')) {
                $table->integer('sla_hours')->default(48)->after('email');
            }
        });
    }

    public function down()
    {
        Schema::table('departments', function (Blueprint $table) {
            if (Schema::hasColumn('departments', 'sla_hours')) {
                $table->dropColumn('sla_hours');
            }
        });
    }
};