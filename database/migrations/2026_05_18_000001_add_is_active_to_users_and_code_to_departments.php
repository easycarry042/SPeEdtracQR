<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('email_verified_at');
            }
        });

        Schema::table('departments', function (Blueprint $table) {
            if (! Schema::hasColumn('departments', 'code')) {
                $table->string('code', 20)->nullable()->unique()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
