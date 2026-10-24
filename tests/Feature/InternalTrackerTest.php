<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\RequestStep;
use App\Models\User;
use App\Support\TrackingQr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public internal-request tracker (the client's request): anyone with an
 * INT- request's QR or number can see which office has it and who there is
 * handling it, without signing in — and nothing about what it asks for.
 */
class InternalTrackerTest extends TestCase
{
    use RefreshDatabase;

    private Department $gso;

    private Department $mayor;

    private User $handler;

    private Document $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gso = Department::create(['name' => 'General Services Office', 'code' => 'GSO', 'is_active' => true]);
        $this->mayor = Department::create(['name' => 'Office of the Mayor', 'code' => 'OM', 'is_active' => true]);
        $this->handler = User::factory()->create(['name' => 'Paolo Mendoza', 'department_id' => $this->gso->id]);

        $this->request = Document::create([
            'tracking_number' => 'INT-20261019-TRK234',
            'document_type' => 'Internal Request',
            'purpose' => 'SECRET purchase of ten laptops',
            'status' => 'pending',
            'origin' => Document::ORIGIN_INTERNAL,
            'has_hard_copy' => false,
            'requesting_department_id' => $this->mayor->id,
        ]);
        $this->request->requestSteps()->create([
            'step_order' => 1,
            'department_id' => $this->gso->id,
            'recipient_id' => $this->handler->id,
            'action' => 'Review and action',
            'status' => RequestStep::STATUS_CURRENT,
            'started_at' => now()->subHour(),
        ]);
    }

    private function track(string $number)
    {
        return $this->get(route('internal.track', ['number' => $number]));
    }

    public function test_the_sign_in_page_offers_it(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Track a request')
            ->assertSee('action="'.route('internal.track').'"', false)
            ->assertSee('Scan QR');
    }

    public function test_anyone_can_see_which_office_has_it_and_who_it_was_sent_to(): void
    {
        $this->assertGuest();

        $this->track('int-20261019-trk234')
            ->assertOk()
            ->assertSee('INT-20261019-TRK234')
            ->assertSee('General Services Office')
            ->assertSee('Paolo Mendoza')
            ->assertSee('Sent to them — not received yet.')
            ->assertSee('Awaiting receipt')
            ->assertSee('Office of the Mayor');
    }

    public function test_once_received_it_names_who_received_it(): void
    {
        $this->request->custodyEvents()->create(['user_id' => $this->handler->id, 'capture_method' => 'image']);

        $this->track('INT-20261019-TRK234')
            ->assertOk()
            ->assertSee('Paolo Mendoza')
            ->assertSee('Received ')
            ->assertSee('Under review');
    }

    public function test_it_never_shows_what_the_request_asks_for(): void
    {
        $this->track('INT-20261019-TRK234')
            ->assertOk()
            ->assertDontSee('SECRET purchase of ten laptops');
    }

    public function test_a_citizen_number_is_sent_to_the_citizen_tracker(): void
    {
        Document::create(['tracking_number' => 'SPD-20261019-CIT234', 'document_type' => 'Business Permit', 'status' => 'pending']);

        $this->track('SPD-20261019-CIT234')
            ->assertOk()
            ->assertSee('is a citizen request')
            ->assertSee(route('citizen.track', ['tracking' => 'SPD-20261019-CIT234']), false);
    }

    public function test_a_phone_camera_opening_the_qr_lands_on_the_request(): void
    {
        $this->get(TrackingQr::content('INT-20261019-TRK234'))
            ->assertOk()
            ->assertSee('General Services Office')
            ->assertSee('Paolo Mendoza');

        $this->get(route('internal.track', ['qr' => 'SPDQR1:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA']))
            ->assertOk()
            ->assertSee('That QR was not issued by SPeED TraQR.');
    }

    public function test_names_can_be_switched_off_for_offices_only(): void
    {
        config(['tracking.public_internal_tracker.show_names' => false]);

        $this->track('INT-20261019-TRK234')
            ->assertOk()
            ->assertSee('General Services Office')
            ->assertDontSee('Paolo Mendoza')
            ->assertDontSee('Handled by')
            ->assertSee('Sent to someone there');
    }

    public function test_unknown_or_malformed_numbers_say_so(): void
    {
        $this->track('INT-20261019-NOPE23')->assertOk()->assertSee('No internal request INT-20261019-NOPE23 was found');
        $this->track('hello')->assertOk()->assertSee('That is not an internal request number');
    }
}
