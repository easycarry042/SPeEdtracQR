<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lending: what happens to an approved borrowing after the approval.
 *
 * Until now a borrowing held stock only for its dates, so the chairs "came
 * back" on the return date whether or not anybody brought them. Now the
 * counter records the hand-over and the return, and a loan nobody has marked
 * returned stays out — overdue — instead of quietly rejoining the stock.
 *
 * - quantity: the units staff granted (the citizen's ask stays on documents.quantity).
 * - released_*: when and by whom the items were handed over.
 * - returned_*: when the loan was closed (everything back or written off; a
 *   facility marked done), plus how the units came back.
 * - overdue_reminded_at: the last overdue reminder, so the sweep does not nag hourly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->nullable()->after('resource_id');
            $table->timestamp('released_at')->nullable()->after('status');
            $table->foreignId('released_by')->nullable()->after('released_at')->constrained('users')->nullOnDelete();
            $table->timestamp('returned_at')->nullable()->after('released_by');
            $table->foreignId('returned_by')->nullable()->after('returned_at')->constrained('users')->nullOnDelete();
            $table->unsignedInteger('returned_quantity')->default(0)->after('returned_by');
            $table->unsignedInteger('damaged_quantity')->default(0)->after('returned_quantity');
            $table->unsignedInteger('lost_quantity')->default(0)->after('damaged_quantity');
            $table->text('return_notes')->nullable()->after('lost_quantity');
            $table->timestamp('overdue_reminded_at')->nullable()->after('return_notes');
        });

        // Existing borrowings were counted by their dates alone, so read them
        // the way the old rule did: one that has ended came back on its return
        // date, one under way was handed over on its pickup date. Without this
        // every past loan would turn up as overdue on the first page load.
        $now = now();

        DB::table('bookings')
            ->join('documents', 'documents.id', '=', 'bookings.document_id')
            ->whereNotNull('documents.quantity')
            ->select('bookings.id', 'documents.quantity')
            ->get()
            ->each(fn ($row) => DB::table('bookings')->where('id', $row->id)->update(['quantity' => $row->quantity]));

        DB::table('bookings')
            ->where('status', 'approved')
            ->where('ends_at', '<=', $now)
            ->get(['id', 'starts_at', 'ends_at', 'quantity'])
            ->each(fn ($row) => DB::table('bookings')->where('id', $row->id)->update([
                'released_at' => $row->starts_at,
                'returned_at' => $row->ends_at,
                'returned_quantity' => (int) $row->quantity,
            ]));

        DB::table('bookings')
            ->where('status', 'approved')
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now)
            ->get(['id', 'starts_at'])
            ->each(fn ($row) => DB::table('bookings')->where('id', $row->id)->update(['released_at' => $row->starts_at]));
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('released_by');
            $table->dropConstrainedForeignId('returned_by');
            $table->dropColumn([
                'quantity', 'released_at', 'returned_at', 'returned_quantity',
                'damaged_quantity', 'lost_quantity', 'return_notes', 'overdue_reminded_at',
            ]);
        });
    }
};
