<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profile pictures.
 *
 * The file itself lives on the private disk and is served only through
 * AvatarController, never from public storage: staff are anonymous to the
 * public on citizen surfaces, and a guessable /storage/avatars/user-5.jpg
 * would hand a face to anyone who tried the URL. Only the path is stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('last_active_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
    }
};
