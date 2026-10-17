<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turns the one-way collaboration feed into a two-way conversation.
 *
 * The table already carried what a threaded message needs (parent_id,
 * visibility, author_type), so this only adds what two-way traffic requires:
 *
 *  - Per-audience read stamps. Read state cannot be one flag: the same row is
 *    "new" to staff and to the citizen independently, so each side gets its own
 *    timestamp and neither can clear the other's badge.
 *  - One optional attachment per message, kept on the private disk and streamed
 *    through a gated route (never a public URL).
 *  - author_name, so a citizen's message keeps the name it was posted under even
 *    if the request's contact details are corrected later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_comments', function (Blueprint $table) {
            $table->string('author_name')->nullable()->after('author_type');
            $table->timestamp('staff_read_at')->nullable()->after('visibility');
            $table->timestamp('citizen_read_at')->nullable()->after('staff_read_at');
            $table->string('attachment_path')->nullable()->after('citizen_read_at');
            $table->string('attachment_name')->nullable()->after('attachment_path');

            // Drives the unread badge: "citizen messages on this ticket that staff
            // have not read yet" must not table-scan.
            $table->index(['document_id', 'author_type', 'staff_read_at'], 'doc_comments_unread_idx');
        });
    }

    public function down(): void
    {
        Schema::table('document_comments', function (Blueprint $table) {
            $table->dropIndex('doc_comments_unread_idx');
            $table->dropColumn([
                'author_name',
                'staff_read_at',
                'citizen_read_at',
                'attachment_path',
                'attachment_name',
            ]);
        });
    }
};
