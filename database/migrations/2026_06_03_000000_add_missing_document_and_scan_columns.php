<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * These columns were referenced throughout the app (model $fillable, controllers,
 * views) but were never actually created by a migration, so the data was silently
 * dropped on write. This adds them so the runtime Schema::hasColumn guards can be
 * removed and the fields (citizen contact, purpose, description, QR path, scan
 * remarks, offline dedup) actually persist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'citizen_contact')) {
                $table->string('citizen_contact')->nullable()->after('citizen_name');
            }
            if (! Schema::hasColumn('documents', 'purpose')) {
                $table->string('purpose')->nullable()->after('citizen_contact');
            }
            if (! Schema::hasColumn('documents', 'description')) {
                $table->text('description')->nullable()->after('purpose');
            }
            if (! Schema::hasColumn('documents', 'qr_code_path')) {
                $table->string('qr_code_path')->nullable();
            }
        });

        Schema::table('document_scans', function (Blueprint $table) {
            if (! Schema::hasColumn('document_scans', 'remarks')) {
                $table->text('remarks')->nullable();
            }
            if (! Schema::hasColumn('document_scans', 'offline_uuid')) {
                $table->string('offline_uuid')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['citizen_contact', 'purpose', 'description', 'qr_code_path']);
        });

        Schema::table('document_scans', function (Blueprint $table) {
            if (Schema::hasColumn('document_scans', 'offline_uuid')) {
                $table->dropIndex(['offline_uuid']);
            }
            $table->dropColumn(['remarks', 'offline_uuid']);
        });
    }
};
