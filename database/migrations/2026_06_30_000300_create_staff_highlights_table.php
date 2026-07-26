<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff profile — manual "highlight" posts (accomplishments / milestones / notes)
 * authored by a staff member, optionally referencing one of their own documents.
 * Staff-visible only (citizens are not users). Auto-logged feed entries are NOT
 * stored here — they are derived from the Spatie activity_log by causer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_highlights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // author
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete(); // referenced doc (chip)
            $table->string('highlight_type')->default('note'); // completed | milestone | note
            $table->text('body');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_highlights');
    }
};
