<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboards' Completed tab: every finished citizen request, fetched a
 * page at a time from history.data in the Review modal's payload.
 */
class DashboardCompletedTabTest extends TestCase
{
    use RefreshDatabase;

    private Department $gso;

    private User $paolo;

    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();

        $this->gso = Department::create(['name' => 'General Services Office', 'code' => 'GSO', 'is_active' => true]);
        $this->paolo = User::factory()->create(['department_id' => $this->gso->id, 'is_active' => true])->assignRole('staff');
        $this->ana = User::factory()->create(['department_id' => $this->gso->id, 'is_active' => true])->assignRole('staff');
    }

    private function request(string $tracking, array $attributes = []): Document
    {
        return Document::create(array_merge([
            'tracking_number' => $tracking,
            'document_type' => 'Chairs Borrowing',
            'citizen_name' => 'Juan Dela Cruz',
            'status' => 'completed',
            'department_id' => $this->gso->id,
            'assigned_to' => $this->paolo->id,
            'completed_at' => now(),
        ], $attributes));
    }

    public function test_the_staff_dashboard_has_active_and_completed_tabs(): void
    {
        $this->request('SPD-DONE-1');
        $this->request('SPD-DONE-ANA', ['assigned_to' => $this->ana->id]);

        $this->actingAs($this->paolo)->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('Active <span class="st-tab-count"', false)
            ->assertSee('Completed <span class="st-tab-count">1</span>', false)
            ->assertSee('history\/data', false); // @js() escapes the slashes
    }

    public function test_staff_see_only_the_requests_they_finished(): void
    {
        $this->request('SPD-MINE-OLD', ['completed_at' => now()->subYear()]);
        $this->request('SPD-MINE-NEW');
        $this->request('SPD-ANAS', ['assigned_to' => $this->ana->id]);
        $this->request('SPD-STILL-OPEN', ['status' => 'in_review', 'completed_at' => null]);
        $this->request('INT-20261021-AAAAAA', ['origin' => Document::ORIGIN_INTERNAL]);

        $response = $this->actingAs($this->paolo)->getJson(route('history.data'))
            ->assertOk()
            ->assertJsonPath('total', 2);

        // Newest first, in the modal's payload (with the completion date).
        $this->assertSame(['SPD-MINE-NEW', 'SPD-MINE-OLD'], collect($response->json('data'))->pluck('tracking_number')->all());
        $this->assertNotNull($response->json('data.0.completed_at'));
        $this->assertSame('completed', $response->json('data.0.status'));
    }

    public function test_it_pages_searches_and_filters_on_the_server(): void
    {
        foreach (range(1, 12) as $i) {
            $this->request(sprintf('SPD-PAGE-%02d', $i), ['completed_at' => now()->subMinutes($i)]);
        }
        $this->request('SPD-TABLES', ['document_type' => 'Tables Borrowing', 'citizen_name' => 'Maria Santos']);

        $this->actingAs($this->paolo)->getJson(route('history.data', ['page' => 2]))
            ->assertOk()
            ->assertJsonPath('total', 13)
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('current_page', 2)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('types', ['Chairs Borrowing', 'Tables Borrowing']);

        $this->actingAs($this->paolo)->getJson(route('history.data', ['search' => 'maria']))
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.tracking_number', 'SPD-TABLES');

        $this->actingAs($this->paolo)->getJson(route('history.data', ['document_type' => 'Tables Borrowing']))
            ->assertJsonPath('total', 1);
    }

    public function test_guests_cannot_read_it(): void
    {
        $this->getJson(route('history.data'))->assertUnauthorized();
    }
}
