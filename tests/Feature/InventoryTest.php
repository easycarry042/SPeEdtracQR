<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Document;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Inventory: what the office owns and what is free right now.
 *
 * The two kinds of resource are counted differently on purpose. A facility is
 * one of a thing — taken or free. Equipment is a pile several borrowers share
 * until it runs out, which is the case nothing in the system could answer
 * before: how many chairs are left for Saturday.
 */
class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seedRolesAndPermissions();

        return User::factory()->create()->assignRole('staff');
    }

    private function chairs(int $owned = 300, int $damaged = 0): Resource
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
        return Resource::create([
            'name' => 'Municipal Gymnasium',
            'kind' => Resource::KIND_FACILITY,
            'capacity' => 1500,
            'is_active' => true,
        ]);
    }

    /** A borrowing of $quantity units across the window. */
    private function borrowing(Resource $resource, int $quantity, string $start, string $end, string $status = Booking::STATUS_APPROVED): Booking
    {
        $document = Document::create([
            'tracking_number' => 'SPD-INV-'.strtoupper(uniqid()),
            'document_type' => 'Chairs Borrowing',
            // Assigned and accepted: a reservation is only approved after that.
            'status' => 'in_progress',
            'assigned_to' => User::factory()->create()->id,
            'accepted_at' => now(),
            'quantity' => $quantity,
        ]);

        return $resource->bookings()->create([
            'document_id' => $document->id,
            'starts_at' => Carbon::parse($start),
            'ends_at' => Carbon::parse($end),
            'status' => $status,
        ]);
    }

    /* ── Counting ─────────────────────────────────────────────────────────── */

    public function test_broken_units_are_owned_but_not_lendable(): void
    {
        $chairs = $this->chairs(owned: 300, damaged: 18);

        $this->assertSame(282, $chairs->serviceableUnits());
    }

    public function test_only_approved_borrowings_hold_stock(): void
    {
        $chairs = $this->chairs(owned: 300);
        $window = ['2026-11-02 08:00', '2026-11-03 17:00'];

        $this->borrowing($chairs, 100, ...$window);
        // Still only a request — it must not reserve anything against itself.
        $this->borrowing($chairs, 150, ...$window, status: Booking::STATUS_PENDING);

        $this->assertSame(100, $chairs->unitsCommitted(Carbon::parse($window[0]), Carbon::parse($window[1])));
        $this->assertSame(200, $chairs->unitsAvailable(Carbon::parse($window[0]), Carbon::parse($window[1])));
    }

    public function test_borrowings_outside_the_window_do_not_count_against_it(): void
    {
        $chairs = $this->chairs(owned: 300);
        $this->borrowing($chairs, 250, '2026-11-02 08:00', '2026-11-03 17:00');

        // The following week — the same chairs are back by then.
        $free = $chairs->unitsAvailable(Carbon::parse('2026-11-09 08:00'), Carbon::parse('2026-11-10 17:00'));

        $this->assertSame(300, $free);
    }

    public function test_a_facility_reports_what_holds_it_now_and_what_is_next(): void
    {
        $gym = $this->gym();
        $now = Carbon::parse('2026-11-02 10:00');

        $this->borrowing($gym, 0, '2026-11-02 08:00', '2026-11-02 12:00');
        $this->borrowing($gym, 0, '2026-11-05 08:00', '2026-11-05 12:00');

        $this->assertNotNull($gym->bookingAt($now), 'The gym is in use at 10am.');
        $this->assertSame('2026-11-05 08:00', $gym->nextBookingAfter($now)?->starts_at->format('Y-m-d H:i'));
    }

    /* ── The page ─────────────────────────────────────────────────────────── */

    public function test_staff_can_open_the_inventory_and_see_what_is_free(): void
    {
        $chairs = $this->chairs(owned: 300, damaged: 18);
        $handedOver = $this->borrowing($chairs, 100, '2026-11-02 08:00', '2026-11-03 17:00');
        $handedOver->update(['released_at' => Carbon::parse('2026-11-02 08:30')]);
        // Approved for later in the week, not collected yet: promised, not out.
        $this->borrowing($chairs, 30, '2026-11-05 08:00', '2026-11-06 17:00');

        $this->travelTo(Carbon::parse('2026-11-02 10:00'));

        $response = $this->actingAs($this->staff())->get(route('inventory.index'))->assertOk();

        $row = $response->viewData('equipment')->firstWhere('name', 'Monoblock Chairs');

        $this->assertSame(300, $row['owned']);
        $this->assertSame(18, $row['damaged']);
        $this->assertSame(100, $row['on_loan']);
        $this->assertSame(30, $row['reserved']);
        // 300 owned, less 18 broken, less 100 out on loan: 182 in the stockroom.
        $this->assertSame(182, $row['on_hand']);
        // ...of which 30 are promised to Thursday's pickup.
        $this->assertSame(152, $row['available']);
    }

    public function test_a_signed_out_visitor_cannot_see_the_inventory(): void
    {
        $this->get(route('inventory.index'))->assertRedirect(route('login'));
    }

    public function test_staff_can_correct_the_counts(): void
    {
        $chairs = $this->chairs(owned: 300, damaged: 0);

        $this->actingAs($this->staff())
            ->patch(route('inventory.update', $chairs), [
                'total_quantity' => 350,
                'damaged_quantity' => 12,
                'location' => 'GSO stockroom',
            ])
            ->assertRedirect();

        $chairs->refresh();

        $this->assertSame(350, $chairs->total_quantity);
        $this->assertSame(12, $chairs->damaged_quantity);
        $this->assertSame('GSO stockroom', $chairs->location);
    }

    public function test_more_broken_than_owned_is_refused(): void
    {
        $chairs = $this->chairs(owned: 300, damaged: 0);

        $this->actingAs($this->staff())
            ->patch(route('inventory.update', $chairs), [
                'total_quantity' => 50,
                'damaged_quantity' => 80,
            ])
            ->assertSessionHasErrors('damaged_quantity');

        $this->assertSame(0, $chairs->fresh()->damaged_quantity);
    }

    /* ── Approval is blocked when the stock will not cover it ─────────────── */

    public function test_approving_more_units_than_are_free_is_refused(): void
    {
        $chairs = $this->chairs(owned: 300);
        $window = ['2026-11-02 08:00', '2026-11-03 17:00'];

        $this->borrowing($chairs, 250, ...$window);
        $tooMany = $this->borrowing($chairs, 100, ...$window, status: Booking::STATUS_PENDING);

        $this->actingAs($this->staff())
            ->patch(route('bookings.approve', $tooMany))
            ->assertSessionHas('error');

        $this->assertSame(Booking::STATUS_PENDING, $tooMany->fresh()->status, 'The booking must stay pending.');
    }

    public function test_two_borrowings_may_share_the_same_days_while_stock_lasts(): void
    {
        $chairs = $this->chairs(owned: 300);
        $window = ['2026-11-02 08:00', '2026-11-03 17:00'];

        $this->borrowing($chairs, 100, ...$window);
        $second = $this->borrowing($chairs, 150, ...$window, status: Booking::STATUS_PENDING);

        $this->actingAs($this->staff())
            ->patch(route('bookings.approve', $second))
            ->assertSessionHasNoErrors();

        // 100 + 150 fits inside 300, so an overlap is not a clash here.
        $this->assertSame(Booking::STATUS_APPROVED, $second->fresh()->status);
    }

    public function test_equipment_with_no_count_set_cannot_be_approved(): void
    {
        $chairs = $this->chairs(owned: 0);
        $booking = $this->borrowing($chairs, 40, '2026-11-02 08:00', '2026-11-03 17:00', status: Booking::STATUS_PENDING);

        $this->actingAs($this->staff())
            ->patch(route('bookings.approve', $booking))
            ->assertSessionHas('error');

        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);
    }

    public function test_a_facility_is_still_refused_on_any_overlap(): void
    {
        $gym = $this->gym();

        $this->borrowing($gym, 0, '2026-11-02 08:00', '2026-11-02 12:00');
        $clashing = $this->borrowing($gym, 0, '2026-11-02 10:00', '2026-11-02 14:00', status: Booking::STATUS_PENDING);

        $this->actingAs($this->staff())
            ->patch(route('bookings.approve', $clashing))
            ->assertSessionHas('error');

        $this->assertSame(Booking::STATUS_PENDING, $clashing->fresh()->status);
    }

    /* ── The Resources tab ─────────────────────────────────────────────────── */

    /** @return list<array{0: string}> */
    public static function rolesWithTheResourcesTab(): array
    {
        return [['staff'], ['Supervisor']];
    }

    #[DataProvider('rolesWithTheResourcesTab')]
    public function test_staff_and_supervisors_have_the_resources_tab(string $role): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create()->assignRole($role);

        $this->actingAs($user)->followingRedirects()->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('inventory.index').'"', false)
            ->assertSee('Resources');

        $this->actingAs($user)->get(route('inventory.index'))->assertOk()->assertSee('Resources');
    }

    public function test_a_supervisor_is_not_sent_to_the_staff_booking_calendar(): void
    {
        $this->seedRolesAndPermissions();
        $supervisor = User::factory()->create()->assignRole('Supervisor');

        $this->actingAs($supervisor)->get(route('inventory.index'))
            ->assertOk()
            ->assertDontSee('href="'.route('bookings.index').'"', false)
            ->assertSee('Reservations are managed by staff');
    }

    public function test_the_migration_gives_the_tab_to_databases_seeded_before_it(): void
    {
        $this->seedRolesAndPermissions();
        Role::findByName('staff')->revokePermissionTo('manage inventory');
        Role::findByName('Supervisor')->revokePermissionTo('manage inventory');

        (require database_path('migrations/2026_10_20_012646_grant_resources_tab_to_staff_and_supervisors.php'))->up();

        $this->assertTrue(User::factory()->create()->assignRole('staff')->can('manage inventory'));
        $this->assertTrue(User::factory()->create()->assignRole('Supervisor')->can('manage inventory'));
    }
}
