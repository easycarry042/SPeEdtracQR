<?php

namespace Tests\Feature;

use App\Events\DocumentStatusUpdated;
use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use App\Support\Ai\LlmProvider;
use App\Support\Ai\NullProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Staff stay anonymous to the public (legal advice): every citizen surface
 * names the office handling a request, never the staff member.
 */
class StaffAnonymityTest extends TestCase
{
    use RefreshDatabase;

    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->app->instance(LlmProvider::class, new NullProvider);

        $office = Department::create(['name' => 'General Services Office', 'code' => 'GSO', 'is_active' => true]);
        $staff = User::factory()->create(['is_active' => true, 'name' => 'Jose Reyes', 'department_id' => $office->id])->assignRole('staff');

        $this->document = Document::create([
            'tracking_number' => 'SPD-20261018-ANN234',
            'document_type' => 'Chairs Borrowing',
            'citizen_name' => 'Maria Santos',
            'citizen_email' => 'maria@example.com',
            'status' => 'in_progress',
            'status_changed_at' => now(),
            'department_id' => $office->id,
            'assigned_to' => $staff->id,
        ]);
    }

    public function test_the_tracking_page_names_the_office_not_the_staff_member(): void
    {
        $this->get($this->document->citizenTrackingUrl())
            ->assertOk()
            ->assertSeeInOrder(['Handled by', 'General Services Office'])
            ->assertDontSee('Staff assigned')
            ->assertDontSee('Jose Reyes');
    }

    public function test_a_request_without_an_office_says_so(): void
    {
        $this->document->update(['department_id' => null, 'assigned_to' => null]);

        $this->get($this->document->citizenTrackingUrl())
            ->assertOk()
            ->assertSee('Not yet routed');
    }

    public function test_the_status_poll_names_the_office(): void
    {
        $this->getJson(route('track.status', $this->document->tracking_number))
            ->assertOk()
            ->assertJsonPath('current_department', 'General Services Office')
            ->assertDontSee('Jose Reyes');
    }

    public function test_the_public_status_broadcast_names_no_one(): void
    {
        $payload = (new DocumentStatusUpdated($this->document, $this->document->assignedTo))->broadcastWith();

        $this->assertSame('General Services Office', $payload['current_department']);
        $this->assertSame('Updated to '.$this->document->statusEnum()->label(), $payload['event']);
        $this->assertArrayNotHasKey('assignee', $payload);
        $this->assertStringNotContainsString('Jose', json_encode($payload));
    }

    public function test_speedy_says_which_office_is_handling_it(): void
    {
        $answer = $this->postJson(route('speedy.ask'), [
            'question' => 'Who is handling my request?',
            'tracking' => $this->document->tracking_number,
        ])->assertOk()->json('answer');

        $this->assertStringContainsString('handled by the General Services Office', $answer);
        $this->assertStringNotContainsString('Jose', $answer);
    }
}
