<?php

namespace Tests\Feature;

use App\Mail\BookingUpdated;
use App\Models\Booking;
use App\Models\Document;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A borrowing's claiming date (set in the Review modal, or when advancing its
 * stage) and its booking on the calendar are the same day. They used to be two
 * columns nobody kept together: moving the claiming date to Oct 25 left the
 * booking on Oct 20, so the Booking calendar and the Resources page still
 * showed the old day — and still counted the chairs against it.
 */
class ClaimDateMovesBookingTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Resource $chairs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->staff = User::factory()->create()->assignRole('staff');
        $this->chairs = Resource::create([
            'name' => 'Monoblock Chairs',
            'kind' => Resource::KIND_EQUIPMENT,
            'total_quantity' => 100,
            'is_active' => true,
        ]);
        $this->travelTo(Carbon::parse('2026-10-18 09:00'));
        Mail::fake();
    }

    /** Chairs asked for Oct 20, back Oct 24 — as the public form files them. */
    private function borrowing(int $asked = 10, string $status = Booking::STATUS_PENDING): Booking
    {
        $document = Document::create([
            'tracking_number' => 'SPD-CLAIM-'.strtoupper(uniqid()),
            'document_type' => 'Chairs Borrowing',
            'status' => 'in_progress',
            'quantity' => $asked,
            'needed_by' => '2026-10-20',
            'citizen_name' => 'Juan Dela Cruz',
            'citizen_email' => 'juan@example.com',
            'assigned_to' => $this->staff->id,
            'assigned_at' => now(),
            'accepted_at' => now(),
        ]);

        return $this->chairs->bookings()->create([
            'document_id' => $document->id,
            'quantity' => $status === Booking::STATUS_APPROVED ? $asked : null,
            'starts_at' => Carbon::parse('2026-10-20 08:00'),
            'ends_at' => Carbon::parse('2026-10-24 17:00'),
            'status' => $status,
        ]);
    }

    public function test_moving_the_claiming_date_moves_the_booking_on_the_calendar(): void
    {
        $booking = $this->borrowing();

        $this->actingAs($this->staff)
            ->patchJson(route('documents.claim-date', $booking->document), ['claim_date' => '2026-10-25'])
            ->assertOk()
            ->assertJsonPath('message', 'Claiming date set to Oct 25, 2026. The booking moved to Oct 25 – Oct 29.');

        $booking->refresh();
        // Same pickup time, and the same five days with the chairs.
        $this->assertSame('2026-10-25 08:00', $booking->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-29 17:00', $booking->ends_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-25', $booking->document->claim_date->toDateString());

        $calendar = $this->actingAs($this->staff)->get(route('bookings.index'))->assertOk();
        $this->assertTrue($calendar->viewData('byDate')->has('2026-10-25'));
        $this->assertFalse($calendar->viewData('byDate')->has('2026-10-20'));
    }

    public function test_an_approved_borrowing_moves_only_if_the_stock_covers_the_new_dates(): void
    {
        $booking = $this->borrowing(asked: 30, status: Booking::STATUS_APPROVED);
        // Someone else already has 80 promised over Oct 26–27.
        $other = $this->borrowing(asked: 80, status: Booking::STATUS_APPROVED);
        $other->update(['starts_at' => '2026-10-26 08:00', 'ends_at' => '2026-10-27 17:00']);

        $this->actingAs($this->staff)
            ->patchJson(route('documents.claim-date', $booking->document), ['claim_date' => '2026-10-25'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('claim_date');

        // Nothing changed: not the booking, not the claiming date.
        $this->assertSame('2026-10-20', $booking->fresh()->starts_at->toDateString());
        $this->assertNull($booking->document->fresh()->claim_date);

        // A date the stock can cover moves it, and the borrower is told.
        $this->actingAs($this->staff)
            ->patchJson(route('documents.claim-date', $booking->document), ['claim_date' => '2026-10-28'])
            ->assertOk();

        $this->assertSame('2026-10-28', $booking->fresh()->starts_at->toDateString());
        Mail::assertQueued(BookingUpdated::class, fn (BookingUpdated $mail) => $mail->outcome === BookingUpdated::OUTCOME_RESCHEDULED);
    }

    public function test_advancing_the_stage_with_a_new_claiming_date_moves_the_booking_too(): void
    {
        $booking = $this->borrowing();
        $booking->document->update(['status' => 'pending']);

        $this->actingAs($this->staff)
            ->patchJson(route('documents.status.advance', $booking->document), [
                'expected_status' => 'pending',
                'claim_date' => '2026-10-23',
            ])
            ->assertOk();

        $this->assertSame('2026-10-23', $booking->fresh()->starts_at->toDateString());
        $this->assertSame('2026-10-27', $booking->fresh()->ends_at->toDateString());
    }

    public function test_rescheduling_on_the_calendar_moves_the_claiming_date(): void
    {
        $booking = $this->borrowing();

        $this->actingAs($this->staff)
            ->patch(route('bookings.reschedule', $booking), [
                'starts_at' => '2026-10-26T09:00',
                'ends_at' => '2026-10-28T17:00',
            ])
            ->assertSessionHas('status');

        $this->assertSame('2026-10-26', $booking->document->fresh()->claim_date->toDateString());
    }

    public function test_items_already_handed_over_stay_put_when_the_claiming_date_changes(): void
    {
        $booking = $this->borrowing(status: Booking::STATUS_APPROVED);
        $booking->update(['released_at' => Carbon::parse('2026-10-18 08:30')]);

        $this->actingAs($this->staff)
            ->patchJson(route('documents.claim-date', $booking->document), ['claim_date' => '2026-10-25'])
            ->assertOk();

        $this->assertSame('2026-10-20', $booking->fresh()->starts_at->toDateString());

        // And the calendar refuses to move a pickup that has already happened.
        $this->actingAs($this->staff)
            ->patch(route('bookings.reschedule', $booking), ['starts_at' => '2026-10-26T09:00', 'ends_at' => '2026-10-28T17:00'])
            ->assertSessionHas('error');
    }

    public function test_a_request_with_no_booking_just_sets_its_claiming_date(): void
    {
        $document = Document::create([
            'tracking_number' => 'SPD-CLAIM-PLAIN',
            'document_type' => 'Business Permit',
            'status' => 'in_progress',
            'assigned_to' => $this->staff->id,
        ]);

        $this->actingAs($this->staff)
            ->patchJson(route('documents.claim-date', $document), ['claim_date' => '2026-10-25'])
            ->assertOk()
            ->assertJsonPath('message', 'Claiming date set to Oct 25, 2026.');
    }
}
