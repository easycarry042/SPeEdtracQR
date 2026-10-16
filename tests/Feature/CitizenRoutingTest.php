<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Support\CitizenReceipt;
use App\Support\CitizenView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Citizen-facing links must reach citizen pages even when a staff member is
 * signed in on the same browser (a shared counter PC), and the receipt must
 * survive a round trip through its printable claim slip.
 */
class CitizenRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function citizenDocument(array $attributes = []): Document
    {
        return Document::create(array_merge([
            'tracking_number' => 'SPD-20261016-ABC234',
            'document_type' => 'Covered Court Reservation',
            'citizen_name' => 'Juan Dela Cruz',
            'status' => 'pending',
        ], $attributes));
    }

    public function test_citizen_view_shows_the_citizen_page_to_a_signed_in_admin(): void
    {
        $this->seedRolesAndPermissions();
        $admin = User::factory()->create()->assignRole('super_admin');
        $document = $this->citizenDocument();

        $this->actingAs($admin)
            ->get($document->citizenTrackingUrl())
            ->assertOk()
            ->assertViewIs('track.show-citizen');
    }

    public function test_citizen_view_shows_the_citizen_page_to_signed_in_staff(): void
    {
        $this->seedRolesAndPermissions();
        $supervisor = User::factory()->create()->assignRole('Supervisor');
        $document = $this->citizenDocument();

        $this->actingAs($supervisor)
            ->get($document->citizenTrackingUrl())
            ->assertOk()
            ->assertViewIs('track.show-citizen')
            ->assertViewHas('custody', null);
    }

    public function test_staff_links_without_the_flag_keep_the_staff_views(): void
    {
        $this->seedRolesAndPermissions();
        $admin = User::factory()->create()->assignRole('super_admin');
        $supervisor = User::factory()->create()->assignRole('Supervisor');
        $document = $this->citizenDocument();

        $this->actingAs($admin)
            ->get(route('track.show', $document->tracking_number))
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($supervisor)
            ->get(route('track.show', $document->tracking_number))
            ->assertOk()
            ->assertViewIs('track.show');
    }

    public function test_citizen_view_never_reveals_an_internal_request(): void
    {
        $this->seedRolesAndPermissions();
        $supervisor = User::factory()->create()->assignRole('Supervisor');
        $document = $this->citizenDocument([
            'tracking_number' => 'INT-20261016-ABC234',
            'origin' => Document::ORIGIN_INTERNAL,
        ]);

        $this->actingAs($supervisor)
            ->get($document->citizenTrackingUrl())
            ->assertNotFound();
    }

    public function test_guests_still_get_the_citizen_page_with_or_without_the_flag(): void
    {
        $document = $this->citizenDocument();

        $this->get(route('track.show', $document->tracking_number))->assertViewIs('track.show-citizen');
        $this->get($document->citizenTrackingUrl())->assertViewIs('track.show-citizen');
    }

    /** What store() leaves in the session once a request is filed. */
    private function withReceiptFor(Document $document): self
    {
        return $this->withSession([CitizenReceipt::SESSION_KEY => [
            'tracking_number' => $document->tracking_number,
            'expires_at' => now()->addMinutes(CitizenReceipt::LIFETIME_MINUTES)->getTimestamp(),
        ]]);
    }

    public function test_receipt_links_point_at_citizen_pages(): void
    {
        $document = $this->citizenDocument();

        $this->withReceiptFor($document)
            ->get(route('public.request.submitted'))
            ->assertOk()
            ->assertSee(e($document->citizenTrackingUrl()), false)
            ->assertSee(route('citizen.track'), false)
            ->assertSee(e(CitizenView::homeUrl()), false)
            ->assertSee(e(route('track.slip', ['trackingNumber' => $document->tracking_number, 'from' => 'receipt'])), false);
    }

    public function test_the_receipt_survives_the_slip_and_the_tracking_page(): void
    {
        $document = $this->citizenDocument();
        $this->withReceiptFor($document);

        // Every detour a citizen takes from the receipt, including the tracking
        // page's background status polls, which used to wipe a one-view flash.
        $this->get(route('track.slip', ['trackingNumber' => $document->tracking_number, 'from' => 'receipt']))->assertOk();
        $this->get($document->citizenTrackingUrl())->assertOk();
        $this->getJson(route('track.status', $document->tracking_number))->assertOk();
        $this->get(route('citizen.track'))->assertOk();

        $this->get(route('public.request.submitted'))
            ->assertOk()
            ->assertSee($document->tracking_number);
    }

    public function test_starting_over_forgets_the_receipt(): void
    {
        $document = $this->citizenDocument();

        foreach ([CitizenView::homeUrl(), route('citizen.dashboard'), route('public.request.create')] as $startOver) {
            $this->withReceiptFor($document)->get($startOver)->assertOk();

            $this->get(route('public.request.submitted'))
                ->assertRedirect(route('public.request.create'));
        }
    }

    public function test_the_receipt_expires(): void
    {
        $document = $this->citizenDocument();
        $this->withReceiptFor($document);

        $this->travel(CitizenReceipt::LIFETIME_MINUTES + 1)->minutes();

        $this->get(route('public.request.submitted'))
            ->assertRedirect(route('public.request.create'));
    }

    public function test_slip_back_returns_to_the_page_that_opened_it(): void
    {
        $document = $this->citizenDocument();
        $slip = fn (array $query = []) => route('track.slip', ['trackingNumber' => $document->tracking_number] + $query);

        $this->get($slip(['from' => 'receipt']))
            ->assertViewHas('backUrl', route('public.request.submitted'));

        $this->get($slip(['from' => 'tracking']))
            ->assertViewHas('backUrl', $document->citizenTrackingUrl());

        // Opened cold (bookmark, new tab): a guest goes to their tracking page...
        $this->get($slip())->assertViewHas('backUrl', $document->citizenTrackingUrl());

        // ...and staff, who open it from their desk, to the staff view.
        $this->seedRolesAndPermissions();
        $this->actingAs(User::factory()->create()->assignRole('Supervisor'))
            ->get($slip())
            ->assertViewHas('backUrl', route('track.show', $document->tracking_number));
    }

    public function test_slip_never_follows_the_sessions_previous_page(): void
    {
        $document = $this->citizenDocument();

        $this->get(route('citizen.dashboard'));

        $this->get(route('track.slip', ['trackingNumber' => $document->tracking_number, 'from' => 'tracking']))
            ->assertDontSee('href="'.route('citizen.dashboard').'" class="inline-flex', false)
            ->assertSee(route('citizen.track'));
    }

    public function test_the_landing_page_is_reachable_by_citizens_while_staff_are_signed_in(): void
    {
        $this->seedRolesAndPermissions();
        $staff = User::factory()->create()->assignRole('Supervisor');

        $this->actingAs($staff)->get(route('welcome'))->assertRedirect(route('home'));
        $this->actingAs($staff)->get(CitizenView::homeUrl())->assertOk()->assertViewIs('welcome');
    }

    public function test_an_unknown_number_on_the_citizen_page_reports_not_found_publicly(): void
    {
        $this->seedRolesAndPermissions();
        $staff = User::factory()->create()->assignRole('Supervisor');
        $missing = 'SPD-20261016-ZZZ999';
        $citizenUrl = route('track.show', ['trackingNumber' => $missing] + CitizenView::QUERY);

        // Guests used to be bounced to the landing page, losing the message.
        $this->get(route('track.show', $missing))
            ->assertRedirect(route('citizen.track', ['tracking' => $missing]));

        $this->actingAs($staff)->get($citizenUrl)
            ->assertRedirect(route('citizen.track', ['tracking' => $missing]));

        // Staff without the flag keep their own Look up hub.
        $this->actingAs($staff)->get(route('track.show', $missing))
            ->assertRedirect(route('track.index', ['find' => 1]));

        $this->get(route('citizen.track', ['tracking' => $missing]))
            ->assertOk()
            ->assertSee("No document found for {$missing}");
    }

    public function test_error_pages_offer_the_public_home_and_the_staff_dashboard(): void
    {
        $this->seedRolesAndPermissions();
        $staff = User::factory()->create()->assignRole('Supervisor');
        $internal = $this->citizenDocument([
            'tracking_number' => 'INT-20261016-ABC234',
            'origin' => Document::ORIGIN_INTERNAL,
        ]);

        // A citizen on a staff-signed-in PC hitting a 404 can still get home.
        $this->actingAs($staff)
            ->get($internal->citizenTrackingUrl())
            ->assertNotFound()
            ->assertSee('href="'.e(CitizenView::homeUrl()).'"', false)
            ->assertSee('href="'.route('home').'"', false);
    }

    public function test_the_landing_page_lookup_links_to_the_citizen_page(): void
    {
        $document = $this->citizenDocument();

        $this->getJson(route('track.lookup', ['tracking_number' => $document->tracking_number]))
            ->assertOk()
            ->assertJsonPath('data.url', $document->citizenTrackingUrl());
    }
}
