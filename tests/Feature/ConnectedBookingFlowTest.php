<?php

namespace Tests\Feature;

use App\Mail\BookingUpdated;
use App\Models\Booking;
use App\Models\Document;
use App\Models\Resource;
use App\Models\User;
use App\Support\NavBadges;
use App\Support\RequestReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A citizen's borrowing or reservation and the request it belongs to are one
 * flow, not two tracks:
 *
 *   assigned + accepted → the booking can be approved
 *   booking approved    → the request can reach Approved
 *   items handed over   → the request is Completed and released
 *   request denied      → the booking is cancelled
 *
 * and the Review modal and the citizen's tracking page both show it.
 */
class ConnectedBookingFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $supervisor;

    private Resource $chairs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->staff = User::factory()->create(['name' => 'Paolo Mendoza'])->assignRole('staff');
        $this->supervisor = User::factory()->create()->assignRole('Supervisor');
        $this->chairs = Resource::create(['name' => 'Monoblock Chairs', 'kind' => Resource::KIND_EQUIPMENT, 'total_quantity' => 100, 'is_active' => true]);
        $this->travelTo(Carbon::parse('2026-11-02 09:00'));
        Mail::fake();
    }

    /** A chairs request in whatever state the test needs. */
    private function borrowing(array $document = [], array $booking = []): Booking
    {
        $doc = Document::create($document + [
            'tracking_number' => 'SPD-FLOW-'.strtoupper(uniqid()),
            'document_type' => 'Chairs Borrowing',
            'status' => 'pending',
            'quantity' => 10,
            'citizen_name' => 'Juan Dela Cruz',
            'citizen_email' => 'juan@example.com',
        ]);

        return $this->chairs->bookings()->create($booking + [
            'document_id' => $doc->id,
            'starts_at' => Carbon::parse('2026-11-03 08:00'),
            'ends_at' => Carbon::parse('2026-11-04 17:00'),
            'status' => Booking::STATUS_PENDING,
        ]);
    }

    /* ── 1. The booking waits for the request's own order ─────────────────── */

    public function test_a_booking_cannot_be_approved_until_the_request_is_assigned_and_accepted(): void
    {
        $booking = $this->borrowing();

        $this->actingAs($this->staff)->patch(route('inventory.loans.approve', $booking), ['quantity' => 10])
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'supervisor has to assign'));
        $this->actingAs($this->staff)->patch(route('bookings.approve', $booking), ['quantity' => 10])
            ->assertSessionHas('error');

        $booking->document->update(['assigned_to' => $this->staff->id]);
        $this->actingAs($this->staff)->patch(route('inventory.loans.approve', $booking), ['quantity' => 10])
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'Paolo Mendoza has to accept'));

        // Not offered on the desk, and not counted on the badge, until then.
        $desk = $this->actingAs($this->staff)->get(route('inventory.index'))->viewData('desk');
        $this->assertNotNull($desk['approve']->first()['not_ready']);
        $this->assertSame(0, NavBadges::for($this->staff)['resources']);

        $booking->document->update(['accepted_at' => now(), 'status' => 'in_progress']);
        $this->assertSame(1, NavBadges::for($this->staff)['resources']);
        $this->actingAs($this->staff)->patch(route('inventory.loans.approve', $booking), ['quantity' => 10])
            ->assertSessionHas('status');
        $this->assertTrue($booking->fresh()->isApproved());
    }

    /* ── 2. The request's Approved stage waits for the booking ────────────── */

    public function test_the_request_cannot_reach_approved_while_its_booking_is_unconfirmed(): void
    {
        $booking = $this->borrowing(['status' => 'in_review', 'assigned_to' => $this->staff->id, 'accepted_at' => now()]);
        $advance = fn () => $this->actingAs($this->staff)->patchJson(route('documents.status.advance', $booking->document), [
            'expected_status' => 'in_review',
            'claim_date' => '2026-11-03',
        ]);

        $advance()->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame('in_review', $booking->document->fresh()->status);

        $this->actingAs($this->staff)->patch(route('inventory.loans.approve', $booking), ['quantity' => 10]);
        $advance()->assertOk();
        $this->assertSame('approved', $booking->document->fresh()->status);
    }

    public function test_a_cancelled_booking_also_blocks_approved(): void
    {
        $booking = $this->borrowing(['status' => 'in_review', 'assigned_to' => $this->staff->id, 'accepted_at' => now()], ['status' => Booking::STATUS_CANCELLED]);

        $this->actingAs($this->staff)->patchJson(route('documents.status.advance', $booking->document), [
            'expected_status' => 'in_review',
            'claim_date' => '2026-11-03',
        ])->assertUnprocessable();
    }

    /* ── 3. Handing over completes the request ────────────────────────────── */

    public function test_items_are_handed_over_only_for_an_approved_request_and_that_completes_it(): void
    {
        $booking = $this->borrowing(
            ['status' => 'in_review', 'assigned_to' => $this->staff->id, 'accepted_at' => now()],
            ['status' => Booking::STATUS_APPROVED, 'quantity' => 10],
        );

        $this->actingAs($this->staff)->patch(route('inventory.loans.release', $booking))
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'Move it to Approved first'));
        $this->assertFalse($booking->fresh()->isReleased());

        $booking->document->update(['status' => 'approved']);
        $this->actingAs($this->staff)->patch(route('inventory.loans.release', $booking))->assertSessionHas('status');

        $document = $booking->document->fresh();
        $this->assertTrue($booking->fresh()->isReleased());
        $this->assertSame('completed', $document->status, 'What the citizen came for has been given.');
        $this->assertNotNull($document->claimed_at);
        $this->assertSame($this->staff->id, $document->released_by);
    }

    public function test_releasing_a_borrowing_at_the_counter_hands_the_items_over(): void
    {
        $booking = $this->borrowing(
            ['status' => 'completed', 'assigned_to' => $this->staff->id, 'accepted_at' => now()],
            ['status' => Booking::STATUS_APPROVED, 'quantity' => 10],
        );

        $this->actingAs($this->staff)->patchJson(route('documents.release', $booking->document))->assertOk();

        $this->assertTrue($booking->fresh()->isReleased());
        $this->assertSame(10, $this->chairs->fresh()->unitsOnLoan());
    }

    public function test_a_counter_release_is_refused_when_the_stockroom_is_short(): void
    {
        $late = $this->borrowing(['status' => 'completed'], [
            'status' => Booking::STATUS_APPROVED, 'quantity' => 95,
            'starts_at' => '2026-10-20 08:00', 'ends_at' => '2026-10-25 17:00', 'released_at' => '2026-10-20 08:00',
        ]);
        $booking = $this->borrowing(['status' => 'completed'], ['status' => Booking::STATUS_APPROVED, 'quantity' => 10]);

        $this->actingAs($this->staff)->patchJson(route('documents.release', $booking->document))
            ->assertUnprocessable()
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'Only 5'));

        $this->assertNull($booking->document->fresh()->claimed_at);
        $this->assertTrue($late->fresh()->isOverdue());
    }

    /* ── 4. A denied request frees what it held ───────────────────────────── */

    public function test_denying_the_request_cancels_its_booking_without_a_second_email(): void
    {
        $booking = $this->borrowing(['status' => 'in_progress', 'assigned_to' => $this->staff->id, 'accepted_at' => now()], [
            'status' => Booking::STATUS_APPROVED, 'quantity' => 10,
        ]);

        $this->actingAs($this->supervisor)->post(route('documents.deny', $booking->document), ['reason' => 'Event cancelled.']);

        $this->assertTrue($booking->fresh()->isCancelled());
        $this->assertSame(0, $this->chairs->fresh()->unitsReserved());
        Mail::assertNotQueued(BookingUpdated::class);
    }

    /* ── 5. The Review modal shows and approves it ─────────────────────────── */

    public function test_the_review_modal_carries_the_booking_and_approves_it_in_place(): void
    {
        $booking = $this->borrowing(['status' => 'in_progress', 'assigned_to' => $this->staff->id, 'accepted_at' => now()]);
        $this->actingAs($this->staff);

        $payload = RequestReview::forModal($booking->document)['booking'];
        $this->assertSame('Monoblock Chairs', $payload['item']);
        $this->assertSame('pending', $payload['state']);
        $this->assertSame(10, $payload['requested']);
        $this->assertSame(100, $payload['free']);
        $this->assertNull($payload['not_ready']);
        $this->assertTrue($payload['can_manage']);

        $this->patchJson($payload['approve_url'], ['quantity' => 8])
            ->assertOk()
            ->assertJsonPath('booking.state', 'reserved')
            ->assertJsonPath('booking.units', 8);

        $this->patchJson($payload['approve_url'], ['quantity' => 8])->assertUnprocessable();
    }

    /* ── 6. The citizen sees it on their tracking page ─────────────────────── */

    public function test_the_citizen_tracking_page_shows_where_the_borrowing_stands(): void
    {
        $booking = $this->borrowing(['status' => 'in_progress', 'assigned_to' => $this->staff->id, 'accepted_at' => now()]);
        $page = fn () => $this->get(route('track.show', ['trackingNumber' => $booking->document->tracking_number, 'view' => 'citizen']));

        $page()->assertOk()->assertSee('Your borrowing')->assertSee('Waiting for confirmation')->assertSee('10 Monoblock Chairs');

        $booking->update(['status' => Booking::STATUS_APPROVED, 'quantity' => 8]);
        $page()->assertSee('Confirmed')->assertSee('You asked for 10; the office confirmed 8.');

        $booking->update(['released_at' => '2026-11-03 08:00']);
        $this->travelTo(Carbon::parse('2026-11-06 09:00'));
        $page()->assertSee('Overdue')->assertSee('Please bring them back as soon as you can')
            ->assertDontSee('Paolo Mendoza');
    }
}
