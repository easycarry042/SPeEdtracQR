<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-requirement review workflow: staff mark each supporting document as
     * approved / needs revision / rejected with a comment. Needs-revision items
     * are re-uploadable by the citizen; rejected items show the reason.
     */
    public function up(): void
    {
        Schema::table('document_requirements', function (Blueprint $table): void {
            $table->string('review_status')->default('pending')->after('is_mandatory');
            $table->text('review_comment')->nullable()->after('review_status');
            $table->timestamp('reviewed_at')->nullable()->after('review_comment');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_requirements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_status', 'review_comment', 'reviewed_at']);
        });
    }
};
