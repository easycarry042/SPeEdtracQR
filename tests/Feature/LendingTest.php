<?php

namespace Tests\Feature;

use App\Mail\BookingUpdated;
use App\Models\Booking;
use App\Models\Department;
use App\Models\Document;
use App\Models\Resource;
use App\Models\User;
use App\Notifications\DocumentEvent;
use App\Support\NavBadges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The lending desk on the Resources page: a borrowing is approved with the
 * number of units being lent, handed over, and only goes back into stock when
 * someone records the return. A late borrower stays "out" — overdue — instead
 * of the chairs quietly reappearing on the return date.
 */
class LendingTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->staff = User::factory()->create(['name' => 'Paolo Mendoza'])->assignRole('staff');
        $this->travelTo(Carbon::parse('2026-11-02 09:00'));
        Mail::fake();
    }

    private function chairs(int $owned = 100, int $damaged = 0): Resource
    {
        return Resource::create([
            'name' => 'Monoblock Chairs',
            'kind' => Resource::KIND_EQUIPMENT,
            'total_quantity' => $owned,
            'damaged_quantity' => $damaged,
            'is_active' => true,
        ]);
    }

    private function gym(): Resource
    {
        return Resource::create(['name' => 'Municipal Gymnasium', 'kind' => Resource::KIND_FACILITY, 'is_active' => true]);
    }

    /** A citizen's borrowing request for $asked units across the window. */
    private function request(Resource $resource, int $asked, string $start, string $end, string $status = Booking::STATUS_PENDING, array $document = []): Booking
    {
        $document = Document::create($document + [
            'tracking_number' => 'SPD-LEND-'.strtoupper(uniqid()),
            'document_type' => 'Chairs Borrowing',
            // Assigned, accepted and approved: approving the booking and handing
            // it over both wait for the request (App\Support\Lending).
            'status' => 'approved',
            'assigned_to' => $this->staff->id,
            'accepted_at' => now(),
            'quantity' => $asked ?: null,
            'citizen_name' => 'Juan Dela Cruz',
            'citizen_email' => 'juan@example.com',
            'citizen_contact' => '0917 000 0000',
        ]);

        return $resource->bookings()->create([
            'document_id' => $document->id,
            'starts_at' => Carbon::parse($start),
            'ends_at' => Carbon::parse($end),
            'status' => $status,
        ]);
    }

    /** @return array<string, mixed> */
    private function row(Resource $resource): array
    {
        return $this->actingAs($this->staff)->get(route('inventory.index'))->assertOk()
            ->viewData('equipment')->firstWhere('name', $resource->name);
    }

    /* ── The whole loan, as the office described it ────────────────────────── */

    public function test_lending_ten_of_a_hundred_chairs_from_approval_to_return(): void
    {
        $chairs = $this->chairs(owned: 100);
        $loan = $this->request($chairs, 10, '2026-11-03 08:00', '2026-11-04 17:00');

        // Staff enter how many they are lending and approve.
        $this->actingAs($this->staff)
            ->patch(route('inventory.loans.approve', $loan), ['quantity' => 10])
            ->assertSessionHas('status');

        $this->assertSame(10, $loan->fresh()->quantity);
        $this->assertSame(Booking::STATUS_APPROVED, $loan->fresh()->status);
        $row = $this->row($chairs);
        $this->assertSame(10, $row['reserved']);
        $this->assertSame(90, $row['available'], 'Once approved, the 10 are no longer available to anyone else.');

        // Pickup day: the chairs leave with the borrower.
        $this->travelTo(Carbon::parse('2026-11-03 08:15'));
        $this->actingAs($this->staff)->patch(route('inventory.loans.release', $loan))->assertSessionHas('status');

        $row = $this->row($chairs);
        $this->assertSame(10, $row['on_loan']);
        $this->assertSame(0, $row['reserved']);
        $this->assertSame(90, $row['available']);
        $this->assertSame(Booking::LENDING_ON_LOAN, $loan->fresh()->lendingState());
        $this->assertSame($this->staff->id, $loan->fresh()->released_by);

        // They come back; staff record it and the stock is whole again.
        $this->actingAs($this->staff)
            ->patch(route('inventory.loans.return', $loan), ['returned' => 10, 'damaged' => 0, 'lost' => 0])
            ->assertSessionHas('status');

        $loan->refresh();
        $this->assertTrue($loan->isClosed());
        $this->assertSame(Booking::LENDING_RETURNED, $loan->lendingState());
        $this->assertSame(100, $this->row($chairs)['available']);
        Mail::assertQueued(BookingUpdated::class, fn (BookingUpdated $mail) => $mail->outcome === BookingUpdated::OUTCOME_RETURNED);
    }

    public function test_staff_may_lend_fewer_than_were_asked_for(): void
    {
        $chairs = $this->chairs(owned: 100);
        $loan = $this->request($chairs, 40, '2026-11-03 08:00', '2026-11-04 17:00');

        $this->actingAs($this->staff)->patch(route('inventory.loans.approve', $loan), ['quantity' => 25]);

        $this->assertSame(25, $loan->fresh()->units());
        $this->assertSame(40, $loan->fresh()->unitsRequested(), 'The citizen\'s ask is kept as filed.');
    }

    /* ── Not enough stock ──────────────────────────────────────────────────── */

    public function test_lending_more_than_is_free_is_refused_and_stays_pending(): void
    {
        $chairs = $this->chairs(owned: 8);
        $loan = $this->request($chairs, 10, '2026-11-03 08:00', '2026-11-04 17:00');

        $this->actingAs($this->staff)
            ->patch(route('inventory.loans.approve', $loan), ['quantity' => 10])
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'only 8 are free'));

        $this->assertSame(Booking::STATUS_PENDING, $loan->fresh()->status);
    }

    public function test_the_page_warns_before_approving_more_than_is_free(): void
    {
        $chairs = $this->chairs(owned: 8);
        $this->request($chairs, 10, '2026-11-03 08:00', '2026-11-04 17:00');

        $response = $this->actingAs($this->staff)->get(route('inventory.index'))->assertOk();

        $pending = $response->viewData('desk')['approve']->first();
        $this->assertSame(8, $pending['free']);
        $response->assertSee('Not enough — only', false);
    }

    /** @return array<string, array{0: mixed}> */
    public static function notAWholeNumber(): array
    {
        return [
            'decimal' => ['10.5'],
            'text' => ['ten'],
            'zero' => [0],
            'negative' => [-3],
            'missing' => [null],
        ];
    }

    #[DataProvider('notAWholeNumber')]
    public function test_the_number_to_lend_must_be_a_whole_number_of_at_least_one(mixed $quantity): void
    {
        $chairs = $this->chairs(owned: 100);
        $loan = $this->request($chairs, 10, '2026-11-03 08:00', '2026-11-04 17:00');

        $this->actingAs($this->staff)
            ->patch(route('inventory.loans.approve', $loan), ['quantity' => $quantity])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(Booking::STATUS_PENDING, $loan->fresh()->status);
    }

    /* ── Overdue ───────────────────────────────────────────────────────────── */

    public function test_an_overdue_loan_stays_out_until_someone_records_the_return(): void
    {
        $chairs = $this->chairs(owned: 100);
        $late = $this->request($chairs, 60, '2026-10-20 08:00', '2026-10-25 17:00', Booking::STATUS_APPROVED);
        $late->update(['quantity' => 60, 'released_at' => Carbon::parse('2026-10-20 08:30')]);

        // A week past the return date, nobody has brought them back.
        $this->assertTrue($late->fresh()->isOverdue());
        $this->assertSame(8, $late->fresh()->daysOverdue());
        $this->assertSame(60, $this->row($chairs)['on_loan']);
        $this->assertSame(40, $this->row($chairs)['available']);

        // So a request for next month cannot be promised those 60 either.
        $next = $this->request($chairs, 50, '2026-12-01 08:00', '2026-12-02 17:00');
        $this->actingAs($this->staff)
            ->patch(route('inventory.loans.approve', $next), ['quantity' => 50])
            ->assertSessionHas('error');

        $desk = $this->actingAs($this->staff)->get(route('inventory.index'))->viewData('desk');
        $this->assertSame([$late->id], $desk['overdue']->pluck('booking.id')->all());
    }

    public function test_a_reminder_can_be_sent_and_says_when_there_is_no_email(): void
    {
        $chairs = $this->chairs(owned: 100);
        $late = $this->request($chairs, 5, '2026-10-20 08:00', '2026-10-25 17:00', Booking::STATUS_APPROVED);
        $late->update(['released_at' => Carbon::parse('2026-10-20 08:30')]);

        $this->actingAs($this->staff)->post(route('inventory.loans.remind', $late))->assertSessionHas('status');
        Mail::assertQueued(BookingUpdated::class, fn (BookingUpdated $mail) => $mail->outcome === BookingUpdated::OUTCOME_OVERDUE);
        $this->assertNotNull($late->fresh()->overdue_reminded_at);

        $noEmail = $this->request($chairs, 5, '2026-10-20 08:00', '2026-10-25 17:00', Booking::STATUS_APPROVED, ['citizen_email' => null]);
        $noEmail->update(['released_at' => Carbon::parse('2026-10-20 08:30')]);

        $this->actingAs($this->staff)->post(route('inventory.loans.remind', $noEmail))
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'call Juan Dela Cruz on 0917 000 0000'));
    }

    public function test_the_hourly_sweep_reminds_the_borrower_and_bells_the_handler_once_per_cadence(): void
    {
        Notification::fake();
        $office = Department::create(['name' => 'General Services Office', 'code' => 'GSO', 'is_active' => true]);
        $supervisor = User::factory()->create(['department_id' => $office->id, 'is_active' => true])->assignRole('Supervisor');
        $this->staff->update(['is_active' => true]);

        $chairs = $this->chairs(owned: 100);
        $late = $this->request($chairs, 5, '2026-10-20 08:00', '2026-10-25 17:00', Booking::STATUS_APPROVED, [
            'department_id' => $office->id,
            'assigned_to' => $this->staff->id,
        ]);
        $late->update(['released_at' => Carbon::parse('2026-10-20 08:30')]);
        // Due back later this week: not overdue, not chased.
        $onTime = $this->request($chairs, 5, '2026-11-01 08:00', '2026-11-06 17:00', Booking::STATUS_APPROVED);
        $onTime->update(['released_at' => Carbon::parse('2026-11-01 08:30')]);

        $this->artisan('inventory:check-overdue')->assertSuccessful();

        Mail::assertQueuedCount(1);
        Notification::assertSentTo($this->staff, DocumentEvent::class, fn (DocumentEvent $n) => $n->event === 'loan_overdue');
        Notification::assertSentTo($supervisor, DocumentEvent::class, fn (DocumentEvent $n) => $n->event === 'loan_overdue');

        // An hour later: already chased, so nothing new.
        $this->travel(1)->hours();
        $this->artisan('inventory:check-overdue')->assertSuccessful();
        Mail::assertQueuedCount(1);

        // Three days on and still not back: chased again.
        $this->travel(3)->days();
        $this->artisan('inventory:check-overdue')->assertSuccessful();
        Mail::assertQueuedCount(2);
    }

    /* ── Partial returns, damage and losses ────────────────────────────────── */

    public function test_a_partial_return_keeps_the_rest_on_loan_and_damage_and_losses_update_the_stock(): void
    {
        $chairs = $this->chairs(owned: 100);
        $loan = $this->request($chairs, 10, '2026-11-01 08:00', '2026-11-02 17:00', Booking::STATUS_APPROVED);
        $loan->update(['quantity' => 10, 'released_at' => Carbon::parse('2026-11-01 08:00')]);

        // 6 come back, one of them broken.
        $this->actingAs($this->staff)
            ->patch(route('inventory.loans.return', $loan), ['returned' => 6, 'damaged' => 1, 'lost' => 0, 'notes' => 'One leg cracked'])
            ->assertSessionHas('status');

        $loan->refresh();
        $this->assertFalse($loan->isClosed());
        $this->assertSame(4, $loan->unitsOutstanding());
        $this->assertSame(1, $chairs->fresh()->damaged_quantity);
        $this->assertSame(4, $this->row($chairs)['on_loan']);
        // 100 owned − 1 broken − 4 still out.
        $this->assertSame(95, $this->row($chairs)['available']);

        // The other 4: 3 come back, 1 is gone for good.
        $this->actingAs($this->staff)
            ->patch(route('inventory.loans.return', $loan), ['returned' => 3, 'lost' => 1]);

        $loan->refresh();
        $this->assertTrue($loan->isClosed());
        $this->assertSame(99, $chairs->fresh()->total_quantity, 'A missing chair comes off the count.');
        $this->assertSame(98, $this->row($chairs)['available']);
        $this->assertSame('One leg cracked', $loan->return_notes);
    }

    public function test_a_return_cannot_account_for_more_than_is_out_or_more_damaged_than_came_back(): void
    {
        $chairs = $this->chairs(owned: 100);
        $loan = $this->request($chairs, 10, '2026-11-01 08:00', '2026-11-02 17:00', Booking::STATUS_APPROVED);
        $loan->update(['quantity' => 10, 'released_at' => Carbon::parse('2026-11-01 08:00')]);

        $this->actingAs($this->staff)->patch(route('inventory.loans.return', $loan), ['returned' => 11])->assertSessionHas('error');
        $this->actingAs($this->staff)->patch(route('inventory.loans.return', $loan), ['returned' => 2, 'damaged' => 3])->assertSessionHas('error');
        $this->actingAs($this->staff)->patch(route('inventory.loans.return', $loan), ['returned' => 0, 'lost' => 0])->assertSessionHas('error');
        $this->actingAs($this->staff)->patch(route('inventory.loans.return', $loan), ['returned' => 2.5])->assertSessionHasErrors('returned');

        $this->assertSame(10, $loan->fresh()->unitsOutstanding());
    }

    /* ── Hand-over and cancelling ──────────────────────────────────────────── */

    public function test_a_hand_over_is_refused_when_late_returns_leave_the_stockroom_short(): void
    {
        $chairs = $this->chairs(owned: 100);
        $late = $this->request($chairs, 95, '2026-10-20 08:00', '2026-10-25 17:00', Booking::STATUS_APPROVED);
        $late->update(['quantity' => 95, 'released_at' => Carbon::parse('2026-10-20 08:00')]);
        // Approved before the other borrower was late (inserted directly).
        $today = $this->request($chairs, 10, '2026-11-02 08:00', '2026-11-03 17:00', Booking::STATUS_APPROVED);
        $today->update(['quantity' => 10]);

        $this->actingAs($this->staff)->patch(route('inventory.loans.release', $today))
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'Only 5'));

        $this->assertFalse($today->fresh()->isReleased());
    }

    public function test_items_already_handed_over_cannot_be_cancelled_but_a_pickup_can(): void
    {
        $chairs = $this->chairs(owned: 100);
        $out = $this->request($chairs, 10, '2026-11-01 08:00', '2026-11-03 17:00', Booking::STATUS_APPROVED);
        $out->update(['quantity' => 10, 'released_at' => Carbon::parse('2026-11-01 08:00')]);
        $pickup = $this->request($chairs, 20, '2026-11-05 08:00', '2026-11-06 17:00', Booking::STATUS_APPROVED);
        $pickup->update(['quantity' => 20]);

        $this->actingAs($this->staff)->patch(route('inventory.loans.cancel', $out))->assertSessionHas('error');
        $this->assertSame(Booking::STATUS_APPROVED, $out->fresh()->status);

        $this->actingAs($this->staff)->patch(route('inventory.loans.cancel', $pickup))->assertSessionHas('status');
        $this->assertSame(Booking::STATUS_CANCELLED, $pickup->fresh()->status);
        $this->assertSame(0, $this->row($chairs)['reserved']);
    }

    /* ── Facilities ────────────────────────────────────────────────────────── */

    public function test_a_facility_marked_done_is_free_again_for_the_rest_of_its_slot(): void
    {
        $gym = $this->gym();
        $now = $this->request($gym, 0, '2026-11-02 08:00', '2026-11-02 17:00', Booking::STATUS_APPROVED);
        $later = $this->request($gym, 0, '2026-11-02 13:00', '2026-11-02 15:00');

        $this->assertNotNull($gym->bookingAt(now()));

        $this->actingAs($this->staff)->patch(route('inventory.loans.return', $now))->assertSessionHas('status');

        $this->assertNull($gym->fresh()->bookingAt(now()), 'Marked done, the gym shows as free.');

        $this->actingAs($this->staff)->patch(route('inventory.loans.approve', $later))->assertSessionHas('status');
        $this->assertSame(Booking::STATUS_APPROVED, $later->fresh()->status);
    }

    /* ── Counts, permissions, the calendar and the badge ───────────────────── */

    public function test_the_owned_count_cannot_drop_below_what_is_out_on_loan(): void
    {
        $chairs = $this->chairs(owned: 100, damaged: 2);
        $out = $this->request($chairs, 30, '2026-11-01 08:00', '2026-11-03 17:00', Booking::STATUS_APPROVED);
        $out->update(['quantity' => 30, 'released_at' => Carbon::parse('2026-11-01 08:00')]);

        $this->actingAs($this->staff)
            ->patch(route('inventory.update', $chairs), ['total_quantity' => 20, 'damaged_quantity' => 2])
            ->assertSessionHasErrors('total_quantity');

        $this->assertSame(100, $chairs->fresh()->total_quantity);
    }

    public function test_a_supervisor_can_run_the_lending_desk_and_a_visitor_cannot(): void
    {
        $chairs = $this->chairs(owned: 100);
        $loan = $this->request($chairs, 10, '2026-11-03 08:00', '2026-11-04 17:00');
        $supervisor = User::factory()->create()->assignRole('Supervisor');

        $this->patch(route('inventory.loans.approve', $loan), ['quantity' => 10])->assertRedirect(route('login'));

        $this->actingAs($supervisor)->patch(route('inventory.loans.approve', $loan), ['quantity' => 10])->assertSessionHas('status');
        $this->assertSame(Booking::STATUS_APPROVED, $loan->fresh()->status);
    }

    public function test_the_booking_calendar_approves_with_a_count_too(): void
    {
        $chairs = $this->chairs(owned: 100);
        $loan = $this->request($chairs, 10, '2026-11-03 08:00', '2026-11-04 17:00');

        $this->actingAs($this->staff)->patch(route('bookings.approve', $loan), ['quantity' => 7])->assertSessionHas('status');

        $this->assertSame(7, $loan->fresh()->quantity);

        // And the calendar asks for that count up front, filled with the ask.
        $other = $this->request($chairs, 12, '2026-11-02 13:00', '2026-11-02 17:00');
        $this->actingAs($this->staff)->get(route('bookings.index'))
            ->assertOk()
            ->assertSee('id="bk-qty-'.$other->id.'"', false)
            ->assertSee('value="12"', false);
    }

    public function test_the_resources_badge_counts_requests_to_approve_and_overdue_loans(): void
    {
        $chairs = $this->chairs(owned: 100);
        $this->request($chairs, 10, '2026-11-03 08:00', '2026-11-04 17:00');
        $late = $this->request($chairs, 5, '2026-10-20 08:00', '2026-10-25 17:00', Booking::STATUS_APPROVED);
        $late->update(['released_at' => Carbon::parse('2026-10-20 08:30')]);
        // Neither of these is waiting on anyone.
        $this->request($chairs, 5, '2026-11-05 08:00', '2026-11-06 17:00', Booking::STATUS_APPROVED);
        $this->request($chairs, 5, '2026-11-05 08:00', '2026-11-06 17:00', Booking::STATUS_CANCELLED);

        $this->assertSame(2, NavBadges::for($this->staff)['resources']);
        $this->assertSame(0, NavBadges::for(User::factory()->create()->assignRole('super_admin'))['resources']);
    }

    public function test_the_desk_lists_each_borrowing_in_its_queue(): void
    {
        $chairs = $this->chairs(owned: 100);
        $pending = $this->request($chairs, 10, '2026-11-03 08:00', '2026-11-04 17:00');
        $pickup = $this->request($chairs, 10, '2026-11-02 08:00', '2026-11-03 17:00', Booking::STATUS_APPROVED);
        $onLoan = $this->request($chairs, 10, '2026-11-01 08:00', '2026-11-04 17:00', Booking::STATUS_APPROVED);
        $onLoan->update(['released_at' => Carbon::parse('2026-11-01 08:00')]);
        $returned = $this->request($chairs, 10, '2026-10-28 08:00', '2026-10-29 17:00', Booking::STATUS_APPROVED);
        $returned->update(['released_at' => Carbon::parse('2026-10-28 08:00'), 'returned_at' => Carbon::parse('2026-10-29 16:00'), 'returned_quantity' => 10]);

        $desk = $this->actingAs($this->staff)->get(route('inventory.index'))
            ->assertSee('Lending desk')
            ->assertSee('Juan Dela Cruz')
            ->viewData('desk');

        $this->assertSame([$pending->id], $desk['approve']->pluck('booking.id')->all());
        $this->assertSame([$pickup->id], $desk['pickup']->pluck('booking.id')->all());
        $this->assertTrue($desk['pickup']->first()['pickup_today']);
        $this->assertSame([$onLoan->id], $desk['on_loan']->pluck('booking.id')->all());
        $this->assertSame([$returned->id], $desk['returned']->pluck('booking.id')->all());
        $this->assertTrue($desk['overdue']->isEmpty());
    }

    public function test_bookings_from_before_the_lending_desk_are_read_the_way_the_old_rule_did(): void
    {
        $migration = require database_path('migrations/2026_10_20_140317_add_lending_to_bookings_table.php');
        $chairs = $this->chairs(owned: 100);
        $past = $this->request($chairs, 20, '2026-10-01 08:00', '2026-10-05 17:00', Booking::STATUS_APPROVED);
        $current = $this->request($chairs, 15, '2026-11-01 08:00', '2026-11-04 17:00', Booking::STATUS_APPROVED);
        $future = $this->request($chairs, 5, '2026-11-10 08:00', '2026-11-11 17:00', Booking::STATUS_APPROVED);

        $migration->down();
        $migration->up();

        // Ended before the desk existed: treated as returned on its date, not overdue.
        $this->assertSame(Booking::LENDING_RETURNED, $past->fresh()->lendingState());
        $this->assertSame(20, $past->fresh()->returned_quantity);
        // Under way: treated as handed over on its pickup date.
        $this->assertSame(Booking::LENDING_ON_LOAN, $current->fresh()->lendingState());
        $this->assertSame(15, $current->fresh()->quantity);
        // Still to come: just promised.
        $this->assertSame(Booking::LENDING_RESERVED, $future->fresh()->lendingState());
    }

    /* ── Changing the return date ──────────────────────────────────────────── */

    public function test_an_overdue_borrower_can_be_given_a_new_return_date(): void
    {
        $chairs = $this->chairs(owned: 100);
        $late = $this->request($chairs, 10, '2026-10-20 08:00', '2026-10-25 17:00', Booking::STATUS_APPROVED);
        $late->update(['quantity' => 10, 'released_at' => Carbon::parse('2026-10-20 08:30'), 'overdue_reminded_at' => now()]);

        $this->actingAs($this->staff)
            ->patch(route('inventory.loans.extend', $late), ['return_by' => '2026-11-05'])
            ->assertSessionHas('status');

        $late->refresh();
        $this->assertSame('2026-11-05 17:00', $late->ends_at->format('Y-m-d H:i'), 'Same time of day, new date.');
        $this->assertSame('2026-10-20', $late->starts_at->toDateString(), 'The pickup does not move.');
        $this->assertFalse($late->isOverdue());
        $this->assertNull($late->overdue_reminded_at, 'The reminder clock starts over if it is late again.');
        $this->assertSame(10, $this->row($chairs)['on_loan'], 'Still out, just not late.');
        Mail::assertQueued(BookingUpdated::class, fn (BookingUpdated $mail) => $mail->outcome === BookingUpdated::OUTCOME_EXTENDED);
    }

    public function test_keeping_items_longer_is_refused_when_they_are_promised_to_someone_else(): void
    {
        $chairs = $this->chairs(owned: 100);
        $loan = $this->request($chairs, 60, '2026-11-01 08:00', '2026-11-03 17:00', Booking::STATUS_APPROVED);
        $loan->update(['quantity' => 60, 'released_at' => Carbon::parse('2026-11-01 08:00')]);
        $next = $this->request($chairs, 50, '2026-11-05 08:00', '2026-11-06 17:00', Booking::STATUS_APPROVED);
        $next->update(['quantity' => 50]);

        // 60 kept into Nov 5–6 plus the 50 promised then is 110 of 100.
        $this->actingAs($this->staff)
            ->patch(route('inventory.loans.extend', $loan), ['return_by' => '2026-11-06'])
            ->assertSessionHas('error', fn (string $message) => str_contains($message, "Can't keep it until Nov 6"));
        $this->assertSame('2026-11-03', $loan->fresh()->ends_at->toDateString());

        // Up to the day before the next pickup is fine.
        $this->actingAs($this->staff)->patch(route('inventory.loans.extend', $loan), ['return_by' => '2026-11-04'])->assertSessionHas('status');

        // And once 40 are back, the 20 still out can stay through Nov 6.
        $this->actingAs($this->staff)->patch(route('inventory.loans.return', $loan), ['returned' => 40]);
        $this->actingAs($this->staff)->patch(route('inventory.loans.extend', $loan), ['return_by' => '2026-11-06'])->assertSessionHas('status');
    }

    public function test_a_return_date_cannot_be_in_the_past_or_before_the_pickup(): void
    {
        $chairs = $this->chairs(owned: 100);
        $pickup = $this->request($chairs, 10, '2026-11-05 08:00', '2026-11-06 17:00', Booking::STATUS_APPROVED);

        $this->actingAs($this->staff)->patch(route('inventory.loans.extend', $pickup), ['return_by' => '2026-11-01'])->assertSessionHasErrors('return_by');
        $this->actingAs($this->staff)->patch(route('inventory.loans.extend', $pickup), ['return_by' => '2026-11-04'])->assertSessionHas('error');
        $this->actingAs($this->staff)->patch(route('inventory.loans.extend', $pickup), ['return_by' => 'soon'])->assertSessionHasErrors('return_by');

        // A promised pickup can have its return date moved too.
        $this->actingAs($this->staff)->patch(route('inventory.loans.extend', $pickup), ['return_by' => '2026-11-08'])->assertSessionHas('status');
        $this->assertSame('2026-11-08', $pickup->fresh()->ends_at->toDateString());
    }
}
