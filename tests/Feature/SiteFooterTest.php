<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The shared fat footer (<x-site-footer />) closes every page that scrolls:
 * contact details, doormat navigation, and the legal line.
 *
 * It is included by four different shells — the landing page, the citizen
 * layout, the guest/auth layout, the staff app layout — plus two standalone
 * public pages. A page family that loses the include is invisible in review,
 * so each one is asserted here by name.
 */
class SiteFooterTest extends TestCase
{
    use RefreshDatabase;

    private function supervisor(): User
    {
        $this->seedRolesAndPermissions();
        $dept = Department::factory()->create();

        return User::factory()->create(['department_id' => $dept->id])->assignRole('Supervisor');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function publicPages(): array
    {
        return [
            'landing page' => ['welcome'],
            'citizen portal' => ['citizen.dashboard'],
            'citizen lookup' => ['citizen.track'],
            'public request form' => ['public.request.create'],
            'staff sign in' => ['login'],
        ];
    }

    #[DataProvider('publicPages')]
    public function test_every_public_page_ends_with_the_footer(string $route): void
    {
        $content = $this->get(route($route))->assertOk()->getContent();

        $this->assertStringContainsString('mailto:speedtraqr@gmail.com', $content);
        $this->assertStringContainsString('For Citizens', $content);
        $this->assertStringContainsString('All rights reserved', $content);
    }

    public function test_the_staff_workspace_carries_the_footer_too(): void
    {
        $this->actingAs($this->supervisor())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('mailto:speedtraqr@gmail.com', false)
            ->assertSee('Get in Touch');
    }

    public function test_a_fixed_height_page_is_left_alone(): void
    {
        // Those pages fill the viewport and scroll their own panels, so a footer
        // below the fold could never be reached — it would only steal height.
        $document = Document::create([
            'tracking_number' => 'SPD-20260101-FTR123',
            'document_type' => 'Business Permit',
            'status' => 'pending',
        ]);

        $this->actingAs($this->supervisor())
            ->get(route('track.show', ['trackingNumber' => $document->tracking_number]))
            ->assertOk()
            ->assertDontSee('mailto:speedtraqr@gmail.com', false);
    }

    public function test_the_doormat_links_point_at_real_pages(): void
    {
        // A dead link in a footer is the kind of thing nobody clicks until a
        // panel does. Every href here is generated from a named route.
        $response = $this->get(route('welcome'))->assertOk();

        foreach (['public.request.create', 'citizen.track', 'citizen.dashboard', 'login'] as $name) {
            $response->assertSee('href="'.route($name).'"', false);
        }
    }

    public function test_the_footer_states_the_statutes_the_system_runs_on(): void
    {
        // The SLA clock and what the public tracking page may show are not
        // design choices; both trace to these laws.
        $this->get(route('welcome'))
            ->assertOk()
            ->assertSee('RA 11032')
            ->assertSee('RA 10173');
    }
}
