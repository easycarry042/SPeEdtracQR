<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A reservation of a resource for a time window, tied to a request (document).
 * A conflict is any overlapping window on the same resource among non-cancelled
 * bookings: existing.starts_at < new.ends_at AND existing.ends_at > new.starts_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status')->default('pending'); // pending | approved | cancelled
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['resource_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
