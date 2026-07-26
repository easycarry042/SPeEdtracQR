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

class BookingNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seedRolesAndPermissions();

        return User::factory()->create()->assignRole('super_admin');
    }

    private function pendingBooking(Resource $resource, array $documentAttributes = []): Booking
    {
        $doc = Document::create(array_merge([
            'tracking_number' => 'SPD-BK-'.strtoupper(uniqid()),
            'document_type' => 'Covered Court Reservation',
            'citizen_name' => 'Juan Dela Cruz',
            'citizen_email' => 'juan@example.com',
            'status' => 'pending',
        ], $documentAttributes));

        return $resource->bookings()->create([
            'document_id' => $doc->id,
            'starts_at' => Carbon::parse('2026-09-01 10:00'),
            'ends_at' => Carbon::parse('2026-09-01 12:00'),
            'status' => Booking::STATUS_PENDING,
        ]);
    }

    public function test_approving_a_booking_emails_the_citizen(): void
    {
        Mail::fake();
        $resource = Resource::create(['name' => 'Covered Court']);
        $booking = $this->pendingBooking($resource);

        $this->actingAs($this->admin())->patch(route('bookings.approve', $booking))->assertRedirect();

        Mail::assertQueued(BookingUpdated::class, function (BookingUpdated $mail) {
            return $mail->outcome === BookingUpdated::OUTCOME_APPROVED
                && $mail->hasTo('juan@example.com');
        });
    }

    public function test_rescheduling_a_booking_emails_the_citizen(): void
    {
        Mail::fake();
        $resource = Resource::create(['name' => 'Covered Court']);
        $booking = $this->pendingBooking($resource);

        $this->actingAs($this->admin())
            ->patch(route('bookings.reschedule', $booking), [
                'starts_at' => '2026-09-02 09:00',
                'ends_at' => '2026-09-02 11:00',
            ])
            ->assertRedirect();

        Mail::assertQueued(BookingUpdated::class, fn (BookingUpdated $mail) => $mail->outcome === BookingUpdated::OUTCOME_RESCHEDULED);
    }

    public function test_cancelling_a_booking_emails_the_citizen(): void
    {
        Mail::fake();
        $resource = Resource::create(['name' => 'Covered Court']);
        $booking = $this->pendingBooking($resource);

        $this->actingAs($this->admin())->patch(route('bookings.cancel', $booking))->assertRedirect();

        Mail::assertQueued(BookingUpdated::class, fn (BookingUpdated $mail) => $mail->outcome === BookingUpdated::OUTCOME_CANCELLED);
    }

    public function test_no_email_when_the_ticket_opted_out(): void
    {
        Mail::fake();
        $resource = Resource::create(['name' => 'Covered Court']);
        $booking = $this->pendingBooking($resource, ['notify_citizen' => false]);

        $this->actingAs($this->admin())->patch(route('bookings.approve', $booking))->assertRedirect();

        Mail::assertNothingQueued();
    }

    public function test_no_email_when_no_citizen_email_is_on_file(): void
    {
        Mail::fake();
        $resource = Resource::create(['name' => 'Covered Court']);
        $booking = $this->pendingBooking($resource, ['citizen_email' => null]);

        $this->actingAs($this->admin())->patch(route('bookings.approve', $booking))->assertRedirect();

        Mail::assertNothingQueued();
    }

    public function test_no_email_when_the_bookings_toggle_is_off(): void
    {
        Mail::fake();
        config(['tracking.notify_citizen.bookings' => false]);
        $resource = Resource::create(['name' => 'Covered Court']);
        $booking = $this->pendingBooking($resource);

        $this->actingAs($this->admin())->patch(route('bookings.approve', $booking))->assertRedirect();

        Mail::assertNothingQueued();
    }
}
