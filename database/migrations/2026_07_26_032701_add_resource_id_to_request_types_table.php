<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a booking-kind request type to the resource it reserves (e.g. the
 * "Covered Court Reservation" type → the Covered Court resource). Null for
 * document-kind types.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_types', function (Blueprint $table) {
            $table->foreignId('resource_id')->nullable()->after('kind')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('request_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resource_id');
        });
    }
};
