<?php

namespace Tests\Feature;

use App\Models\RequestType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * How the public request form reports problems, and how its two columns line up.
 */
class RequestFormLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function permitType(): void
    {
        $this->seedRolesAndPermissions();

        RequestType::create([
            'name' => 'Barangay Clearance',
            'kind' => RequestType::KIND_DOCUMENT,
            'is_active' => true,
        ]);
    }

    public function test_problems_are_reported_in_a_pop_up_over_the_form(): void
    {
        // Small print under a field on a form this long is off-screen as often
        // as not, so a refused submit looked like a dead button.
        $this->permitType();

        $response = $this->get(route('public.request.create'))->assertOk();

        $response->assertSee('id="formErrorAlert"', false);
        $response->assertSee('aria-live="assertive"', false);
        // Pinned to the viewport, not parked at the bottom of the form.
        $response->assertSee('fixed inset-x-0 top-4', false);
        $response->assertSee('Take me there');
        // The same pop-up carries whatever the server sent back.
        $response->assertSee('window.ErrorAlert.show(messages,', false);
    }

    public function test_a_server_refusal_is_lifted_into_the_pop_up(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->permitType();

        $response = $this->from(route('public.request.create'))
            ->followingRedirects()
            ->post(route('public.request.store'), [
                'document_type' => 'Barangay Clearance',
                'citizen_name' => 'Juan Dela Cruz',
                'citizen_email' => 'juan@example.com',
                'citizen_contact' => '0912345',
                'consent' => '1',
            ])->assertOk();

        // The static summary still renders for a browser without JavaScript,
        // and the script hands it to the pop-up when there is JavaScript.
        $response->assertSee('id="serverErrorSummary"', false);
        $response->assertSee('Add the missing 4 digits', false);
        $response->assertSee("document.getElementById('serverErrorSummary')", false);
    }

    /**
     * The honeypot took the first slot of the left column's space-y stack, so
     * every field on the left started one 30px gap lower than the column beside
     * it — "Request category" sat below "Name" instead of level with it.
     */
    public function test_the_columns_start_at_the_same_height(): void
    {
        $this->permitType();

        $content = $this->get(route('public.request.create'))->assertOk()->getContent();

        $honeypot = strpos($content, 'name="website"');
        $columns = strpos($content, 'gap-x-[59px]');

        $this->assertNotFalse($honeypot);
        $this->assertNotFalse($columns);
        $this->assertLessThan($columns, $honeypot, 'The honeypot must sit outside the two-column grid.');
    }

    public function test_booking_time_controls_use_the_shared_field_sizing(): void
    {
        $this->permitType();

        $content = $this->get(route('public.request.create'))->assertOk()->getContent();

        $this->assertStringContainsString('type="time" name="start_time"', $content);
        $this->assertStringContainsString('type="time" name="end_time"', $content);
        $this->assertStringContainsString('class="req-field min-w-[7rem] flex-1"', $content);
    }

    /**
     * Every date on this form opens the system's own calendar — the Look Up
     * desk's picker — rather than whichever one the browser ships. The value
     * still posts as a plain ISO date under the same field name, so nothing
     * server-side knows the control changed.
     */
    public function test_dates_open_the_system_calendar_not_the_browsers(): void
    {
        $this->permitType();

        $content = $this->get(route('public.request.create'))->assertOk()->getContent();

        $this->assertStringNotContainsString('type="date"', $content, 'A native date input brings the browser\'s own calendar back.');

        foreach (['booking_date', 'needed_date', 'return_date', 'needed_by'] as $field) {
            $this->assertStringContainsString('<input type="hidden" name="'.$field.'"', $content, "{$field} must still post its value.");
        }

        // The calendar itself, and the shared day-cell styling it borrows.
        $this->assertStringContainsString('cz-datepicker', $content);
        $this->assertStringContainsString('dp-day', $content);
        $this->assertStringContainsString('Alpine.data(\'dateField\'', $content);
    }

    /**
     * A native <input type="time"> in a 12-hour locale renders "hh:mm AM/PM"
     * and clips that tail rather than shrinking it, so a time field squeezed
     * narrow loses the AM/PM and reads as any hour of the day. Three columns
     * left each one 63px against the ~105px it needs; two columns, with the
     * date on its own row above, leave 138px.
     */
    public function test_the_reservation_row_leaves_the_time_fields_room_for_am_pm(): void
    {
        $this->permitType();

        $content = $this->get(route('public.request.create'))->assertOk()->getContent();
        $panel = substr($content, (int) strpos($content, 'id="bookingSection"'));
        $panel = substr($panel, 0, (int) strpos($panel, 'id="equipmentSection"'));

        $this->assertStringContainsString('sm:grid-cols-2', $panel);
        $this->assertStringNotContainsString('sm:grid-cols-3', $panel, 'Three columns clip the AM/PM off both time fields.');
        $this->assertStringContainsString('sm:col-span-2', $panel, 'The date takes its own row so the two times can share one.');
        // The flex floor behind the same guarantee, inside the component.
        $this->assertStringNotContainsString('min-w-0', $panel);
    }
}
