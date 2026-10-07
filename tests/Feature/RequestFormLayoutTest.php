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
        $columns = strpos($content, 'gap-x-[74px]');

        $this->assertNotFalse($honeypot);
        $this->assertNotFalse($columns);
        $this->assertLessThan($columns, $honeypot, 'The honeypot must sit outside the two-column grid.');
    }
}
