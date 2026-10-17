<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The shared fat footer (<x-site-footer />) closes every PUBLIC page:
 * contact details, doormat navigation, and the legal line.
 *
 * It is included by three shells — the landing page, the citizen layout, the
 * guest/auth layout — plus two standalone public pages. A page family that
 * loses the include is invisible in review, so each one is asserted here by
 * name.
 *
 * The signed-in workspace deliberately has none; see the test at the foot.
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

    /**
     * Every signed-in dashboard, one per role.
     *
     * @return array<string, array{string, string}>
     */
    public static function workspaceDashboards(): array
    {
        return [
            'staff' => ['staff', 'staff.dashboard'],
            'supervisor' => ['Supervisor', 'dashboard'],
            'super admin' => ['super_admin', 'admin.dashboard'],
        ];
    }

    /**
     * The fat footer is wayfinding for visitors. Staff, supervisors and super
     * admins already have all of it in the sidebar, so inside the workspace it
     * is only 120px of padding and a dark plate between them and the bottom of
     * their queue.
     */
    #[DataProvider('workspaceDashboards')]
    public function test_the_signed_in_workspace_has_no_footer(string $role, string $route): void
    {
        $this->seedRolesAndPermissions();
        $dept = Department::factory()->create();
        $user = User::factory()->create(['department_id' => $dept->id])->assignRole($role);

        $this->actingAs($user)
            ->get(route($route))
            ->assertOk()
            ->assertDontSee('mailto:speedtraqr@gmail.com', false)
            ->assertDontSee('Get in Touch')
            ->assertDontSee('All rights reserved');
    }

    public function test_a_guest_on_an_app_layout_page_still_gets_the_footer(): void
    {
        // The public /track lookup runs on the same shell as the workspace but
        // through its guest branch — a visitor there is still a visitor.
        $document = Document::create([
            'tracking_number' => 'SPD-20260101-FTR123',
            'document_type' => 'Business Permit',
            'status' => 'pending',
        ]);

        $this->get(route('track.show', ['trackingNumber' => $document->tracking_number]))
            ->assertOk()
            ->assertSee('mailto:speedtraqr@gmail.com', false);
    }
}
