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
        $this->assertStringContainsString('Get in Touch', $content);
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

    /** The rendered footer element alone. */
    private function footer(string $content): string
    {
        $start = (int) strpos($content, '<footer');

        return substr($content, $start, (int) strpos($content, '</footer>') - $start);
    }

    public function test_the_footer_carries_no_doormat_navigation(): void
    {
        // The "For Citizens" and "The System" columns only repeated links every
        // page already offers. Its only links are the privacy notice and the
        // office mailbox.
        $citizen = $this->footer($this->get(route('welcome'))->assertOk()->getContent());
        $staff = $this->footer($this->actingAs($this->supervisor())->get(route('dashboard'))->assertOk()->getContent());

        foreach ([$citizen, $staff] as $footer) {
            $this->assertStringNotContainsString('For Citizens', $footer);
            $this->assertStringNotContainsString('The System', $footer);
            $this->assertSame(2, substr_count($footer, '<a '));
            $this->assertStringContainsString('href="'.route('privacy').'"', $footer);
            $this->assertStringContainsString('href="mailto:speedtraqr@gmail.com"', $footer);
        }
    }

    public function test_the_footer_names_the_city_as_the_owner(): void
    {
        $footer = $this->footer($this->get(route('citizen.track'))->assertOk()->getContent());

        $this->assertStringContainsString('images/staff/seal-2.png', $footer);
        $this->assertStringContainsString('San Pedro City Hall, Laguna', $footer);
    }

    public function test_the_footer_states_the_statutes_the_system_runs_on(): void
    {
        // The SLA clock and what the public tracking page may show are not
        // design choices; both trace to these laws.
        $this->get(route('welcome'))
            ->assertOk()
            ->assertSee('RA&nbsp;11032', false)
            ->assertSee('RA&nbsp;10173', false);
    }
}
