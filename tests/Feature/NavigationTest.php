<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Navigation IA: role-based landing pages, the Look up hub, and the
 * Assignments desk with its folded-in Unclaimed tab.
 */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    private function login(User $user)
    {
        return $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
    }

    private function makeDocument(array $attributes = []): Document
    {
        return Document::create(array_merge([
            'tracking_number' => 'SPD-NAV-'.strtoupper(uniqid()),
            'document_type' => 'Business Permit',
            'status' => 'pending',
        ], $attributes));
    }

    public function test_staff_login_lands_on_requests(): void
    {
        $this->seedRolesAndPermissions();

        $this->login($this->userWithRole('staff'))
            ->assertRedirect(route('staff.dashboard', absolute: false));
    }

    public function test_scan_capable_staff_still_land_on_requests(): void
    {
        // The former intake-only landing (Look up hub) was removed together with
        // the receiving_staff role; scan-capable staff now land on Requests.
        $this->seedRolesAndPermissions();

        $this->login($this->userWithRole('staff'))
            ->assertRedirect(route('staff.dashboard', absolute: false));
    }

    public function test_supervisor_login_lands_on_dashboard(): void
    {
        $this->seedRolesAndPermissions();

        $this->login($this->userWithRole('Supervisor'))
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_scan_redirects_to_look_up_hub(): void
    {
        $this->seedRolesAndPermissions();

        $this->actingAs($this->userWithRole('staff'))
            ->get(route('scan.index'))
            ->assertRedirect(route('track.index', ['find' => 1]));
    }

    public function test_find_mode_renders_the_requests_hub_instead_of_redirecting(): void
    {
        $this->seedRolesAndPermissions();

        $user = $this->userWithRole('staff');

        // Even with an assigned in-progress document (which /track would
        // otherwise auto-open), ?find=1 stays on the finder.
        $this->makeDocument([
            'assigned_to' => $user->id,
            'status' => 'in_progress',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('track.index', ['find' => 1]))
            ->assertOk()
            ->assertSee('Find a request');
    }

    public function test_unknown_tracking_number_soft_fails_without_a_404(): void
    {
        // A guest scanning a bogus/forged code is soft-redirected, never 404'd.
        $this->get(route('track.show', 'SPD-20260101-ZZZZZZ'))
            ->assertRedirect(route('track.index', ['find' => 1]));

        // The public no longer has a standalone Look up hub — guests are sent to
        // the homepage, where the inline search lives.
        $this->get(route('track.index', ['find' => 1]))
            ->assertRedirect(route('welcome'));
    }

    public function test_look_up_hub_leads_with_the_qr_upload_button(): void
    {
        $this->seedRolesAndPermissions();

        $this->actingAs($this->userWithRole('staff'))
            ->get(route('track.index', ['find' => 1]))
            ->assertOk()
            ->assertSee('Upload QR image')
            ->assertSee('drag one onto the button')
            ->assertSeeInOrder(['Type the tracking number', 'Upload QR image', 'Open camera scanner']);
    }

    public function test_unclaimed_queue_is_a_tab_on_the_assignments_desk(): void
    {
        $this->seedRolesAndPermissions();

        $supervisor = $this->userWithRole('Supervisor');

        $claimed = $this->makeDocument(['assigned_to' => $supervisor->id, 'status' => 'in_progress']);
        $unclaimed = $this->makeDocument(['assigned_to' => null]);

        // Old standalone URL redirects into the tab.
        $this->actingAs($supervisor)
            ->get(route('admin.assignments.unclaimed'))
            ->assertRedirect(route('admin.assignments.index', ['filter' => 'unclaimed']));

        // The tab lists only unassigned documents.
        $this->actingAs($supervisor)
            ->get(route('admin.assignments.index', ['filter' => 'unclaimed']))
            ->assertOk()
            ->assertSee($unclaimed->tracking_number)
            ->assertDontSee($claimed->tracking_number);
    }
}
