<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The track page offers two routes in side by side — scan the receipt's QR, or
 * type the number printed on it — each in its own glass card.
 */
class CitizenTrackPageDesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_ways_in_are_offered_side_by_side(): void
    {
        $response = $this->get(route('citizen.track'))->assertOk();

        $response->assertSee('Track your requests now!');
        $response->assertSee('Scan QR Code to track');
        $response->assertSee('Enter Tracking Number');
        $response->assertSee('Start Camera');
        $response->assertSee('Search');

        // Both columns carry the portal's glass card.
        $this->assertSame(2, substr_count($response->getContent(), 'portal-card'));
    }

    public function test_the_lookup_field_shows_the_tracking_number_shape(): void
    {
        $this->get(route('citizen.track'))
            ->assertOk()
            ->assertSee('placeholder="SPD-XXXXXXXX-XXXXXX"', false);
    }

    public function test_the_scanner_keeps_a_status_line_for_screen_readers(): void
    {
        // The status text is visually replaced by the framing guide, but the
        // camera's state still has to reach assistive tech.
        $this->get(route('citizen.track'))
            ->assertOk()
            ->assertSee('id="scanStatus"', false)
            ->assertSee('aria-live="polite"', false);
    }

    public function test_camera_trouble_interrupts_with_a_dialog(): void
    {
        // The old inline panel sat under the card where it was easy to miss.
        $response = $this->get(route('citizen.track'))->assertOk();

        $response->assertSee("new CustomEvent('open-modal', { detail: 'camera-error' })", false);
        $response->assertSee('id="cameraErrorTitle"', false);
        $response->assertSee('id="cameraErrorBody"', false);
        $response->assertSee('Try Again');

        // Its copy is set per failure, so the dialog can't claim "denied" when
        // the device simply has no camera.
        $response->assertSee('No camera found');
        $response->assertSee('Could not start the camera');
        $response->assertSee('Camera access denied');
    }

    public function test_the_scanner_uses_the_bundled_shared_helper_not_a_cdn(): void
    {
        // The CDN copy of html5-qrcode overwrote the bundled one, so an office
        // LAN without internet access got a Start Camera button that did
        // nothing. SpeedQr also refuses an insecure origin loudly, which is the
        // usual reason the browser never shows a permission prompt.
        $response = $this->get(route('citizen.track'))->assertOk();

        $response->assertDontSee('unpkg.com', false);
        $response->assertDontSee('html5-qrcode.min.js', false);
        $response->assertSee('window.SpeedQr.start(', false);
        $response->assertSee('window.SpeedQr.describe(', false);

        // Foreign QR codes are rejected rather than sent to a bogus lookup.
        $response->assertSee('window.SpeedQr.extractTracking(', false);
    }

    public function test_a_failed_lookup_still_reports_inside_the_lookup_card(): void
    {
        $this->get(route('citizen.track', ['tracking' => 'not-a-number']))
            ->assertOk()
            ->assertSee('We couldn\'t track that number.', false)
            ->assertSee('aria-describedby="tracking-error"', false);
    }
}
