<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff badge code. Endorsement decisions re-confirm identity by scanning the
 * signer's own badge QR instead of retyping a password — a records office signs
 * dozens of hops a day, and a password field on every one trains people to type
 * their password into whatever form asks for it.
 *
 * The code is a random secret, never the user's id or email: scanning a badge is
 * only proof of identity if the badge cannot be derived from public information.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('identity_code', 40)->nullable()->unique()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('identity_code');
        });
    }
};
