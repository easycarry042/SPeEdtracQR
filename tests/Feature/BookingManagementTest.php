<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Document;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seedRolesAndPermissions();

        return User::factory()->create()->assignRole('super_admin');
    }

    private function pendingBooking(Resource $resource, string $start, string $end): Booking
    {
        $doc = Document::create([
            'tracking_number' => 'SPD-BK-'.strtoupper(uniqid()),
            'document_type' => 'Covered Court Reservation',
            'status' => 'pending',
        ]);

        return $resource->bookings()->create([
            'document_id' => $doc->id,
            'starts_at' => Carbon::parse($start),
            'ends_at' => Carbon::parse($end),
            'status' => Booking::STATUS_PENDING,
        ]);
    }

    public function test_admin_can_approve_a_booking(): void
    {
        $admin = $this->admin();
        $resource = Resource::create(['name' => 'Covered Court']);
        $booking = $this->pendingBooking($resource, '2026-09-01 10:00', '2026-09-01 12:00');

        $this->actingAs($admin)
            ->patch(route('bookings.approve', $booking))
            ->assertRedirect();

        $this->assertSame(Booking::STATUS_APPROVED, $booking->fresh()->status);
    }

    public function test_approving_a_clashing_booking_is_refused(): void
    {
        $admin = $this->admin();
        $resource = Resource::create(['name' => 'Covered Court']);
        $first = $this->pendingBooking($resource, '2026-09-01 10:00', '2026-09-01 12:00');
        $second = $this->pendingBooking($resource, '2026-09-01 11:00', '2026-09-01 13:00');

        $this->actingAs($admin)->patch(route('bookings.approve', $first))->assertRedirect();

        // Second overlaps the now-approved first → refused, stays pending.
        $this->actingAs($admin)
            ->patch(route('bookings.approve', $second))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(Booking::STATUS_PENDING, $second->fresh()->status);
    }

    public function test_admin_can_cancel_a_booking_which_frees_the_slot(): void
    {
        $admin = $this->admin();
        $resource = Resource::create(['name' => 'Covered Court']);
        $booking = $this->pendingBooking($resource, '2026-09-01 10:00', '2026-09-01 12:00');

        $this->actingAs($admin)->patch(route('bookings.cancel', $booking))->assertRedirect();

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
        // A cancelled slot no longer conflicts.
        $this->assertCount(0, $resource->conflicts(Carbon::parse('2026-09-01 10:30'), Carbon::parse('2026-09-01 11:30')));
    }

    public function test_staff_can_open_the_calendar(): void
    {
        // Bookings are owned by staff via the 'manage bookings' permission.
        $this->seedRolesAndPermissions();
        $staff = User::factory()->create()->assignRole('staff');

        $this->actingAs($staff)->get(route('bookings.index'))->assertOk();
    }

    public function test_a_user_without_the_booking_permission_cannot_open_the_calendar(): void
    {
        $this->seedRolesAndPermissions();
        // Supervisors manage documents, not resource bookings — no 'manage bookings'.
        $withoutBooking = User::factory()->create()->assignRole('Supervisor');

        $this->actingAs($withoutBooking)->get(route('bookings.index'))->assertRedirect();
    }
}
