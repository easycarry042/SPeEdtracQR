<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory for the resources the office lends out.
 *
 * Until now a resource was only a name: nothing recorded that the municipality
 * owns 300 monoblock chairs, so no one could answer "how many are left for
 * Saturday?" without counting them. Two kinds behave differently and now say
 * which they are:
 *
 *   - facility  — one of a thing (the gymnasium). Exclusive: two approved
 *                 reservations may never overlap.
 *   - equipment — a countable pile (chairs, tents). Shared: overlapping
 *                 borrowings are fine until the stock runs out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->string('kind')->default('facility')->after('name');
            /** Units the office owns. Null for a facility, which is never counted. */
            $table->unsignedInteger('total_quantity')->nullable()->after('kind');
            /** Broken or otherwise unlendable units, still owned but not offerable. */
            $table->unsignedInteger('damaged_quantity')->default(0)->after('total_quantity');
            /** People a facility holds. Null for equipment. */
            $table->unsignedInteger('capacity')->nullable()->after('damaged_quantity');
            /** Where it is, or where it is stored — "Barangay Hall grounds". */
            $table->string('location')->nullable()->after('capacity');
        });

        // Which existing rows are equipment is already recorded, indirectly:
        // an equipment-kind request type points at them. Read it from there
        // rather than hard-coding names, so a municipality that renamed or
        // added resources is classified correctly too.
        $equipmentIds = DB::table('request_types')
            ->where('kind', 'equipment')
            ->whereNotNull('resource_id')
            ->distinct()
            ->pluck('resource_id');

        if ($equipmentIds->isNotEmpty()) {
            DB::table('resources')->whereIn('id', $equipmentIds)->update(['kind' => 'equipment']);
        }
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->dropColumn(['kind', 'total_quantity', 'damaged_quantity', 'capacity', 'location']);
        });
    }
};
