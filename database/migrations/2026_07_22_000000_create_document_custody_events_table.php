<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Physical custody trail: each row records that a staff member took physical
 * possession of the paper folder (usually by scanning its QR). The digital
 * status says where the *record* is; this says where the *paper* is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_custody_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['document_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_custody_events');
    }
};
