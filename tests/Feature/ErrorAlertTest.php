<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\RequestType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One error report for the whole app: a pop-up pinned to the top of the
 * viewport, driven by window.ErrorAlert.
 *
 * Refusals used to be printed wherever the field happened to be — under an
 * input near the bottom of a long form, or in a box inside the create modal's
 * scrolling column — which is off-screen as often as not, so a refused submit
 * read as a dead button.
 */
class ErrorAlertTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seedRolesAndPermissions();

        return User::factory()->create()->assignRole('staff');
    }

    public function test_the_staff_shell_carries_the_shared_pop_up(): void
    {
        $response = $this->actingAs($this->staff())->get(route('staff.dashboard'))->assertOk();

        $response->assertSee('window.ErrorAlert = (function ()', false);
        $response->assertSee('id="formErrorAlert"', false);
        // Above the create-document modal, which sits at z-[100].
        $response->assertSee('z-[120]', false);
    }

    public function test_the_staff_intake_modal_reports_through_the_pop_up(): void
    {
        // A refusal about the type or the citizen's name used to appear in a box
        // inside the right-hand column, below the fold on a short window.
        //
        // The modal renders for whoever holds 'create documents' without
        // 'manage system' (see AppServiceProvider), so the permission is granted
        // directly here rather than through a role.
        $user = $this->staff();
        $user->givePermissionTo('create documents');

        $response = $this->actingAs($user)->get(route('staff.dashboard'))->assertOk();

        $response->assertSee('window.ErrorAlert.show(messages, target);', false);
        $response->assertSee('showSubmitErrors(Object.values(errors).flat(), target);', false);
    }

    public function test_the_citizen_upload_panel_reports_through_the_pop_up(): void
    {
        $document = Document::create([
            'tracking_number' => 'SPD-ALERT-'.uniqid(),
            'document_type' => 'Business Permit',
            'citizen_name' => 'Maria Santos',
            'status' => 'in_progress',
        ]);

        $response = $this->get(route('track.show', $document->tracking_number))->assertOk();

        $response->assertSee('id="formErrorAlert"', false);
        $response->assertSee('window.ErrorAlert?.show(problems, browseBtn', false);
        $response->assertSee('Your files were not sent', false);
        // The line under the file list is gone; the pop-up is the report.
        $response->assertDontSee('data-file-error', false);
    }

    public function test_a_refused_upload_is_handed_to_the_pop_up(): void
    {
        $document = Document::create([
            'tracking_number' => 'SPD-ALERT-'.uniqid(),
            'document_type' => 'Business Permit',
            'citizen_name' => 'Maria Santos',
            'status' => 'in_progress',
        ]);

        $response = $this->from(route('track.show', $document->tracking_number))
            ->followingRedirects()
            ->post(route('track.citizen-upload', $document->tracking_number), ['attachments' => []])
            ->assertOk();

        // Still rendered for a browser without JavaScript, then lifted.
        $response->assertSee('id="uploadErrorSummary"', false);
        $response->assertSee("document.getElementById('uploadErrorSummary')", false);
    }

    public function test_the_public_request_form_uses_the_same_pop_up(): void
    {
        $this->seedRolesAndPermissions();
        RequestType::create([
            'name' => 'Barangay Clearance',
            'kind' => RequestType::KIND_DOCUMENT,
            'is_active' => true,
        ]);

        // One implementation, three surfaces — the page includes the component
        // rather than carrying its own copy of the card and its script.
        $this->get(route('public.request.create'))
            ->assertOk()
            ->assertSee('window.ErrorAlert = (function ()', false)
            ->assertSee('window.ErrorAlert.show(problems.map(', false);
    }
}
