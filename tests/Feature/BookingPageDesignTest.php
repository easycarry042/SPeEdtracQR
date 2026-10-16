<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Document;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The booking screen as the STAFF BOOKING frame draws it: a month calendar in
 * the wide left panel, and two side panels — what is booked on the selected day
 * and what is coming up — each on the same near-clear glass at a 35px radius.
 */
class BookingPageDesignTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seedRolesAndPermissions();

        return User::factory()->create()->assignRole('staff');
    }

    private function booking(string $start, string $end, string $requester = 'Leovie Minoza'): Booking
    {
        $resource = Resource::firstOrCreate(['name' => 'City Plaza']);

        $document = Document::create([
            'tracking_number' => 'SPD-BKD-'.strtoupper(uniqid()),
            'document_type' => 'City Plaza Reservation',
            'citizen_name' => $requester,
            'status' => 'pending',
        ]);

        return $resource->bookings()->create([
            'document_id' => $document->id,
            'starts_at' => Carbon::parse($start),
            'ends_at' => Carbon::parse($end),
            'status' => Booking::STATUS_PENDING,
        ]);
    }

    /**
     * The month label is a fixed-width button (so the arrows never shift as the
     * month name changes length) that opens a month/year picker.
     */
    public function test_the_month_label_opens_a_month_and_year_picker(): void
    {
        $this->actingAs($this->staff())->get(route('bookings.index'))
            ->assertOk()
            ->assertSee('class="bk-month bk-month-btn"', false)
            ->assertSee('@click="openPicker()"', false)
            ->assertSee('role="dialog" aria-label="Choose month and year"', false)
            ->assertSee('x-model.number="pickerYear"', false)
            ->assertSee('@click="jumpTo(pickerYear, mi)"', false);
    }

    /**
     * The app runs on Philippine time, so "today" turns over at local midnight.
     * On UTC it turned over at 08:00 and the calendar marked yesterday as today.
     */
    public function test_today_follows_philippine_time_not_utc(): void
    {
        // 02:00 on the 16th in Manila is still 18:00 on the 15th in UTC.
        Carbon::setTestNow(Carbon::parse('2026-10-15 18:00:00', 'UTC'));

        try {
            $this->assertSame('Asia/Manila', config('app.timezone'));

            $this->actingAs($this->staff())->get(route('bookings.index'))
                ->assertOk()
                ->assertSee("today: '2026-10-16'", false);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_the_screen_is_a_calendar_beside_the_two_side_panels(): void
    {
        $response = $this->actingAs($this->staff())->get(route('bookings.index'))->assertOk();

        // Calendar beside a 340px column of two cards, filling the window.
        $response->assertSee('class="app-fixed-height', false);
        $response->assertSee('lg:grid-cols-[minmax(0,1fr)_340px]', false);
        $response->assertSee('class="bk-card min-h-[520px] lg:min-h-0" aria-label="Reservation calendar"', false);
        $response->assertSee('Booked today');
        $response->assertSee('Upcoming');

        // Previous / Today / Next as one joined control.
        $response->assertSee('class="bk-nav" role="group" aria-label="Change month"', false);

        // Today is marked on the grid, for sight and for screen readers.
        $response->assertSee("'bk-day-today': cell.iso === today", false);
        $response->assertSee(":aria-current=\"cell.iso === today ? 'date' : null\"", false);

        // Sunday is the one coloured column head.
        $response->assertSee('bk-dow-sun', false);
        foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dow) {
            $response->assertSee('>'.$dow.'</div>', false);
        }
    }

    public function test_both_side_panels_say_what_is_missing_when_nothing_is_booked(): void
    {
        $response = $this->actingAs($this->staff())->get(route('bookings.index'))->assertOk();

        $response->assertSee('There are no reservations today');
        $response->assertSee('There are no upcoming reservations');
    }

    public function test_the_day_panel_lists_the_selected_days_reservations_with_their_controls(): void
    {
        $booking = $this->booking(now()->setTime(9, 0)->toDateTimeString(), now()->setTime(11, 0)->toDateTimeString());

        $response = $this->actingAs($this->staff())->get(route('bookings.index'))->assertOk();

        $response->assertSee('City Plaza');
        $response->assertSee('Requested by Leovie Minoza');
        // The actions the screen exists for are still on it.
        $response->assertSee(route('bookings.approve', $booking), false);
        $response->assertSee(route('bookings.reschedule', $booking), false);
        $response->assertSee(route('bookings.cancel', $booking), false);
        // Today's date is pre-selected, so the panel opens on today's bookings.
        $response->assertSee("selected: '".now()->format('Y-m-d')."'", false);
    }

    public function test_upcoming_lists_reservations_after_today_and_not_todays(): void
    {
        $this->booking(now()->setTime(9, 0)->toDateTimeString(), now()->setTime(11, 0)->toDateTimeString(), 'Today Requester');
        $this->booking(now()->addDays(4)->setTime(8, 0)->toDateTimeString(), now()->addDays(4)->setTime(17, 0)->toDateTimeString(), 'Future Requester');

        $response = $this->actingAs($this->staff())->get(route('bookings.index'))->assertOk();

        $response->assertSee('Requested by Future Requester');
        $response->assertDontSee('There are no upcoming reservations');

        // Today's booking belongs in the day panel above, not in Upcoming.
        $content = $response->getContent();
        $upcoming = substr($content, strpos($content, 'Upcoming</h2>'));
        $this->assertStringNotContainsString('Today Requester', $upcoming);
    }

    public function test_a_day_with_an_overlap_is_marked_on_the_calendar(): void
    {
        // Two bookings on one resource at the same time: the day carries the
        // clash marker so it can be found without opening every day.
        $this->booking(now()->addDay()->setTime(9, 0)->toDateTimeString(), now()->addDay()->setTime(12, 0)->toDateTimeString());
        $this->booking(now()->addDay()->setTime(11, 0)->toDateTimeString(), now()->addDay()->setTime(13, 0)->toDateTimeString());

        $response = $this->actingAs($this->staff())->get(route('bookings.index'))->assertOk();

        // @js renders the map as JSON.parse('…') with \u0022 for its quotes.
        $response->assertSee('\u0022conflict\u0022:true', false);
        $response->assertSee("cell.meta.conflict ? 'is-clash' : ''", false);
        $response->assertSee('Overlaps another booking');
    }
}
