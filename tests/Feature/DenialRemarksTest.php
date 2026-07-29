<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A denied request must tell the citizen who decided and why. Previously the
 * reason was stored in `remarks` but never rendered, and the decider survived
 * only as free text in the activity log.
 */
class DenialRemarksTest extends TestCase
{
    use RefreshDatabase;

    private function pendingDocument(): Document
    {
        return Document::create([
            'tracking_number' => 'SPD-20260728-3Y73WW',
            'document_type' => 'Chairs Borrowing',
            'citizen_name' => 'Francisco Consorte',
            'citizen_email' => 'francisco@example.com',
            'status' => DocumentStatus::Pending->value,
        ]);
    }

    public function test_denying_records_who_decided_and_when(): void
    {
        $this->seedRolesAndPermissions();
        $supervisor = User::factory()->create(['name' => 'Maria Santos'])->assignRole('Supervisor');
        $document = $this->pendingDocument();

        $this->actingAs($supervisor)
            ->post(route('documents.deny', $document), ['reason' => 'Chairs are already reserved that day.'])
            ->assertRedirect();

        $document->refresh();

        $this->assertSame(DocumentStatus::Denied->value, $document->status);
        $this->assertSame($supervisor->id, $document->decided_by);
        $this->assertNotNull($document->decided_at);
        $this->assertSame('Chairs are already reserved that day.', $document->remarks);
    }

    public function test_tracking_page_shows_the_denier_their_role_and_the_remarks(): void
    {
        $this->seedRolesAndPermissions();
        $supervisor = User::factory()->create(['name' => 'Maria Santos'])->assignRole('Supervisor');
        $document = $this->pendingDocument();

        $this->actingAs($supervisor)
            ->post(route('documents.deny', $document), ['reason' => 'Chairs are already reserved that day.']);

        // Viewed as the citizen would — signed out.
        auth()->logout();

        $this->get(route('track.show', $document->tracking_number))
            ->assertOk()
            ->assertSee('Reason for denial')
            ->assertSee('Maria Santos')
            ->assertSee('Supervisor')
            ->assertSee('Chairs are already reserved that day.');
    }

    public function test_a_staff_decision_is_labelled_staff_not_supervisor(): void
    {
        $this->seedRolesAndPermissions();
        $staff = User::factory()->create(['name' => 'Ana Cruz'])->assignRole('staff');
        $document = $this->pendingDocument();

        // Deny is supervisor-gated, so attribute directly to exercise the label.
        $document->update([
            'status' => DocumentStatus::Denied->value,
            'remarks' => 'Incomplete requirements.',
            'decided_by' => $staff->id,
            'decided_at' => now(),
        ]);

        $this->assertSame(
            ['name' => 'Ana Cruz', 'role' => 'Staff'],
            $document->fresh()->decisionActor()
        );
    }

    public function test_a_denial_from_before_the_decision_columns_still_names_the_decider(): void
    {
        $this->seedRolesAndPermissions();
        $supervisor = User::factory()->create(['name' => 'Maria Santos'])->assignRole('Supervisor');
        $document = $this->pendingDocument();

        $this->actingAs($supervisor)
            ->post(route('documents.deny', $document), ['reason' => 'Duplicate request.']);

        // Simulate a legacy row: the columns are empty, only the log remains.
        $document->refresh()->update(['decided_by' => null, 'decided_at' => null]);

        $this->assertSame(
            ['name' => 'Maria Santos', 'role' => 'Supervisor'],
            $document->fresh()->decisionActor()
        );
    }

    public function test_a_denial_with_no_reason_says_so_rather_than_showing_nothing(): void
    {
        $this->seedRolesAndPermissions();
        $supervisor = User::factory()->create(['name' => 'Maria Santos'])->assignRole('Supervisor');
        $document = $this->pendingDocument();

        $this->actingAs($supervisor)->post(route('documents.deny', $document), []);
        auth()->logout();

        $this->get(route('track.show', $document->tracking_number))
            ->assertOk()
            ->assertSee('No reason was recorded.');
    }

    public function test_an_open_request_shows_no_decision_block(): void
    {
        $document = $this->pendingDocument();

        $this->get(route('track.show', $document->tracking_number))
            ->assertOk()
            ->assertDontSee('Reason for denial');
    }
}
