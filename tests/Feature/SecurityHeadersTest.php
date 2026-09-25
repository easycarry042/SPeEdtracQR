<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the security headers, and in particular the two rules that break real
 * features when they regress: the camera permission and the HTTPS-only HSTS.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_baseline_headers_are_sent_on_public_pages(): void
    {
        $response = $this->get(route('welcome'));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertNotNull($response->headers->get('Permissions-Policy'));
        $this->assertNotNull($response->headers->get('Content-Security-Policy'));
    }

    /**
     * The QR scanners on the Look Up hub, the custody widget and the internal
     * request panel all call getUserMedia(). If this ever becomes `camera=()`
     * every scan flow dies silently — no error, just a camera that never
     * starts. Worth a dedicated test.
     */
    public function test_permissions_policy_still_allows_the_camera(): void
    {
        $policy = (string) $this->get(route('welcome'))->headers->get('Permissions-Policy');

        $this->assertStringContainsString('camera=(self)', $policy);
        $this->assertStringNotContainsString('camera=()', $policy);
    }

    public function test_csp_blocks_framing_plugins_and_stray_form_targets(): void
    {
        $csp = (string) $this->get(route('welcome'))->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
    }

    /**
     * The accessibility widget is loaded from jsDelivr. If the CDN is dropped
     * from script-src the widget silently stops loading, so the allowance is
     * asserted rather than left to chance.
     */
    public function test_csp_allows_the_accessibility_widget_cdn(): void
    {
        $csp = (string) $this->get(route('welcome'))->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('https://cdn.jsdelivr.net', $csp);
    }

    /**
     * Live tracking runs over a Reverb WebSocket. connect-src has to name it or
     * Echo is blocked and the citizen page silently stops updating.
     */
    public function test_csp_permits_the_reverb_websocket_origin(): void
    {
        config(['security.csp.enabled' => true]);

        $csp = (string) $this->get(route('welcome'))->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('connect-src', $csp);
    }

    /**
     * HSTS over plain HTTP would pin localhost to HTTPS in a developer's
     * browser, which is unpleasant to undo.
     */
    public function test_hsts_is_not_sent_over_plain_http(): void
    {
        $this->assertNull(
            $this->get(route('welcome'))->headers->get('Strict-Transport-Security')
        );
    }

    public function test_hsts_is_sent_over_https(): void
    {
        $response = $this->get('https://localhost/');

        $this->assertStringContainsString(
            'max-age=',
            (string) $response->headers->get('Strict-Transport-Security')
        );
    }

    /** Signed-in pages get the same treatment as public ones. */
    public function test_headers_are_sent_on_authenticated_pages(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create()->assignRole('super_admin');

        $this->actingAs($user)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
