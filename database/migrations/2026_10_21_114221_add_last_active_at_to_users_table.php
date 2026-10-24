<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Real presence for the "Active now" marker.
 *
 * It used to be read from the activity log — the last time the user *caused a
 * logged change*. Reading a page, opening a request, scanning a queue: none of
 * those write an audit row, so somebody sitting on the site all morning still
 * showed "Last active 1 hour ago", their login. The marker was answering a
 * different question from the one it asks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_active_at')->nullable()->after('is_active');
        });

        // Seed from what the old rule knew, so nobody reads as never-seen until
        // their next visit. The activity log remains the fallback in code too.
        DB::table('users')->orderBy('id')->chunkById(200, function ($users) {
            foreach ($users as $user) {
                $seen = DB::table('activity_log')
                    ->where('causer_type', 'App\Models\User')
                    ->where('causer_id', $user->id)
                    ->max('created_at');

                if ($seen) {
                    DB::table('users')->where('id', $user->id)->update(['last_active_at' => $seen]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_active_at');
        });
    }
};
