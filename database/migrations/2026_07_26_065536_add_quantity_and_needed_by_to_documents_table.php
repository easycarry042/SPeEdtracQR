<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quantity + due date for requests that ask for a number of something.
 *   - equipment borrowing (e.g. 50 chairs) — quantity of the reserved resource;
 *   - service/production (e.g. 10 leis) — quantity to make, by `needed_by`.
 * Both null for plain document requests and facility reservations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->nullable()->after('purpose');
            $table->date('needed_by')->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'needed_by']);
        });
    }
};
