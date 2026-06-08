<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('document_scans')) {
            return;
        }

        Schema::create('document_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade');
            $table->foreignId('scanned_by')->constrained('users');
            $table->foreignId('department_id')->constrained();
            $table->enum('action', ['in', 'out']);
            $table->timestamp('scanned_at')->useCurrent();
            $table->string('location_ip')->nullable();
            $table->text('remarks')->nullable();
            $table->string('offline_uuid')->nullable();
            $table->boolean('synced')->default(true); // for offline sync
            $table->timestamps();

            $table->index('offline_uuid');

            $table->index(['document_id', 'scanned_at']);
            $table->index('department_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('document_scans');
    }
};