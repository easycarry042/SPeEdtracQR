<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who first opened a requirement's upload, and when. Staff must open a
     * file before they can mark it checked; a citizen re-upload clears it.
     */
    public function up(): void
    {
        Schema::table('document_requirements', function (Blueprint $table): void {
            $table->timestamp('opened_at')->nullable()->after('uploaded_file_path');
            $table->foreignId('opened_by')->nullable()->after('opened_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_requirements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('opened_by');
            $table->dropColumn('opened_at');
        });
    }
};
