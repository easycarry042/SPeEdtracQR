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

    /**
     * ?find=1 used to render the Look Up desk — a browsable table of the work
     * in scope. The desk was folded into each dashboard's Review modal and
     * deleted, so /track is the QR and tracking-number hub in every mode.
     */
    public function test_find_mode_renders_the_qr_hub_instead_of_redirecting(): void
    {
        $this->seedRolesAndPermissions();

        $user = $this->userWithRole('staff');

        // Even with an assigned in-progress document (which /track would
        // otherwise auto-open), the finder stays on the finder.
        $this->makeDocument([
            'assigned_to' => $user->id,
            'status' => 'in_progress',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('track.index', ['find' => 1]))
            ->assertOk()
            ->assertSee('Look up a document')
            ->assertSee('Type the tracking number')
            ->assertDontSee('Requested by');
    }

    public function test_scan_mode_still_reaches_the_qr_hub(): void
    {
        // The QR hub did not go away — it moved behind ?scan=1, for when the
        // paper (and its code) is in hand.
        $this->seedRolesAndPermissions();

        $this->actingAs($this->userWithRole('staff'))
            ->get(route('track.index', ['find' => 1, 'scan' => 1]))
            ->assertOk()
            ->assertSee('Look up a document');
    }

    public function test_unknown_tracking_number_soft_fails_without_a_404(): void
    {
        // A guest scanning a bogus/forged code is soft-redirected, never 404'd —
        // to the public Track page, which says it was not found. (The staff Look
        // up hub sends guests on to the homepage, which lost that message.)
        $this->get(route('track.show', 'SPD-20260101-ZZZZZZ'))
            ->assertRedirect(route('citizen.track', ['tracking' => 'SPD-20260101-ZZZZZZ']));

        $this->get(route('citizen.track', ['tracking' => 'SPD-20260101-ZZZZZZ']))
            ->assertOk()
            ->assertSee('No document found for SPD-20260101-ZZZZZZ');

        // The public has no standalone Look up hub — guests are sent to the
        // homepage, where the inline search lives.
        $this->get(route('track.index', ['find' => 1]))
            ->assertRedirect(route('welcome'));
    }

    public function test_look_up_hub_leads_with_the_qr_upload_button(): void
    {
        $this->seedRolesAndPermissions();

        $this->actingAs($this->userWithRole('staff'))
            ->get(route('track.index', ['find' => 1, 'scan' => 1]))
            ->assertOk()
            ->assertSee('Upload QR image')
            ->assertSee('drag one onto the button')
            ->assertSeeInOrder(['Type the tracking number', 'Upload QR image', 'Open camera scanner']);
    }

    public function test_old_assignments_links_land_on_the_dashboard_tabs(): void
    {
        $this->seedRolesAndPermissions();

        $supervisor = $this->userWithRole('Supervisor');

        $this->actingAs($supervisor)
            ->get(route('admin.assignments.unclaimed'))
            ->assertRedirect(route('dashboard', ['tab' => 'pending']));
        $this->actingAs($supervisor)
            ->get(route('admin.assignments.index', ['filter' => 'unclaimed']))
            ->assertRedirect(route('dashboard', ['tab' => 'pending']));
        $this->actingAs($supervisor)
            ->get(route('admin.assignments.index', ['status' => 'completed']))
            ->assertRedirect(route('dashboard', ['tab' => 'done']));
        $this->actingAs($supervisor)
            ->get(route('admin.assignments.index', ['assignee' => $supervisor->id]))
            ->assertRedirect(route('dashboard', ['tab' => 'active', 'q' => $supervisor->name]));
    }
}
