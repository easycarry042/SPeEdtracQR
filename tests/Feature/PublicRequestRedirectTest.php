<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\RequestType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Post/Redirect/Get on the public request form.
 *
 * Filing used to render the receipt straight from the POST, which left the
 * browser sitting on a submitted form: a refresh — or Back then Forward —
 * re-posted it and filed a second identical request under a new tracking
 * number. Citizens routinely accept the browser's "Confirm Form Resubmission"
 * prompt, so this was not theoretical.
 */
class PublicRequestRedirectTest extends TestCase
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

    /** @return array<string, string> */
    private function filing(): array
    {
        return [
            'document_type' => 'Barangay Clearance',
            'citizen_name' => 'Juan Dela Cruz',
            'citizen_email' => 'juan@example.com',
            'consent' => '1',
        ];
    }

    public function test_filing_redirects_rather_than_rendering_the_receipt(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->permitType();

        $this->post(route('public.request.store'), $this->filing())
            ->assertRedirect(route('public.request.submitted'));

        $this->assertSame(1, Document::count());
    }

    public function test_the_receipt_shows_the_tracking_number_after_the_redirect(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->permitType();

        $this->post(route('public.request.store'), $this->filing());
        $document = Document::firstOrFail();

        $this->get(route('public.request.submitted'))
            ->assertOk()
            ->assertSee($document->tracking_number);
    }

    /** The whole point: reloading the receipt must not file anything. */
    public function test_reloading_the_receipt_does_not_file_a_second_request(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->permitType();

        $this->post(route('public.request.store'), $this->filing());
        $this->assertSame(1, Document::count());

        // Three reloads, as an impatient citizen would.
        foreach (range(1, 3) as $ignored) {
            $this->get(route('public.request.submitted'))->assertOk();
        }

        $this->assertSame(1, Document::count(), 'Reloading the receipt must never create another request.');
    }

    /**
     * On a shared counter terminal the next person must not find the previous
     * citizen's QR and tracking number still on screen.
     */
    public function test_the_receipt_is_not_reachable_after_navigating_away(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->permitType();

        $this->post(route('public.request.store'), $this->filing());
        $this->get(route('public.request.submitted'))->assertOk();

        // Any other page consumes the kept flash.
        $this->get(route('welcome'));

        $this->get(route('public.request.submitted'))
            ->assertRedirect(route('public.request.create'));
    }

    /** Opening the URL cold, with nothing filed, simply returns to the form. */
    public function test_visiting_the_receipt_directly_returns_to_the_form(): void
    {
        $this->permitType();

        $this->get(route('public.request.submitted'))
            ->assertRedirect(route('public.request.create'));
    }
}
