<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Document;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Staff set the date a request is served on while reviewing it. A date is
 * exclusive per resource: once July 22 is taken on the Covered Court, no other
 * staff member can put another request on the court that day.
 */
class RequestSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private Resource $court;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seedRolesAndPermissions();
        $this->court = Resource::create(['name' => 'Covered Court', 'is_active' => true]);
    }

    private function assignedDocument(User $staff, string $tracking = 'SPD-SCH-1'): Document
    {
        return Document::create([
            'tracking_number' => $tracking,
            'document_type' => 'Covered Court Reservation',
            'citizen_name' => 'Maria Santos',
            'status' => 'in_progress',
            'assigned_to' => $staff->id,
            'assigned_at' => now(),
            'accepted_at' => now(),
        ]);
    }

    private function staff(): User
    {
        return User::factory()->create(['is_active' => true])->assignRole('staff');
    }

    private function date(): string
    {
        return now()->addDays(10)->toDateString();
    }

    public function test_assigned_staff_can_set_a_date(): void
    {
        $staff = $this->staff();
        $document = $this->assignedDocument($staff);
        $date = $this->date();

        $this->actingAs($staff)
            ->patchJson(route('documents.schedule', $document), [
                'scheduled_date' => $date,
                'resource_id' => $this->court->id,
            ])
            ->assertOk()
            ->assertJson(['schedule_date' => $date]);

        $this->assertDatabaseHas('bookings', [
            'document_id' => $document->id,
            'resource_id' => $this->court->id,
            'status' => Booking::STATUS_APPROVED,
        ]);
    }

    public function test_a_date_already_taken_on_the_same_resource_is_refused(): void
    {
        $first = $this->staff();
        $second = $this->staff();
        $date = $this->date();

        $this->actingAs($first)
            ->patchJson(route('documents.schedule', $this->assignedDocument($first, 'SPD-SCH-A')), [
                'scheduled_date' => $date,
                'resource_id' => $this->court->id,
            ])
            ->assertOk();

        // A different staff member, a different request, the same court and day.
        $this->actingAs($second)
            ->patchJson(route('documents.schedule', $this->assignedDocument($second, 'SPD-SCH-B')), [
                'scheduled_date' => $date,
                'resource_id' => $this->court->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('scheduled_date');

        $this->assertSame(1, Booking::count());
    }

    public function test_the_same_date_is_free_on_a_different_resource(): void
    {
        $staff = $this->staff();
        $plaza = Resource::create(['name' => 'Plaza', 'is_active' => true]);
        $date = $this->date();

        $this->actingAs($staff)
            ->patchJson(route('documents.schedule', $this->assignedDocument($staff, 'SPD-SCH-C')), [
                'scheduled_date' => $date,
                'resource_id' => $this->court->id,
            ])
            ->assertOk();

        $this->actingAs($staff)
            ->patchJson(route('documents.schedule', $this->assignedDocument($staff, 'SPD-SCH-D')), [
                'scheduled_date' => $date,
                'resource_id' => $plaza->id,
            ])
            ->assertOk();

        $this->assertSame(2, Booking::count());
    }

    public function test_a_request_can_be_moved_to_another_date_and_keeps_one_booking(): void
    {
        $staff = $this->staff();
        $document = $this->assignedDocument($staff);
        $later = now()->addDays(20)->toDateString();

        foreach ([$this->date(), $this->date(), $later] as $date) {
            $this->actingAs($staff)
                ->patchJson(route('documents.schedule', $document), [
                    'scheduled_date' => $date,
                    'resource_id' => $this->court->id,
                ])
                ->assertOk();
        }

        $this->assertSame(1, Booking::count());
        $this->assertSame($later, $document->booking->fresh()->starts_at->toDateString());
    }

    public function test_past_dates_are_rejected(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)
            ->patchJson(route('documents.schedule', $this->assignedDocument($staff)), [
                'scheduled_date' => now()->subDay()->toDateString(),
                'resource_id' => $this->court->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('scheduled_date');
    }

    public function test_staff_cannot_schedule_a_request_assigned_to_someone_else(): void
    {
        $owner = $this->staff();
        $intruder = $this->staff();

        $this->actingAs($intruder)
            ->patchJson(route('documents.schedule', $this->assignedDocument($owner)), [
                'scheduled_date' => $this->date(),
                'resource_id' => $this->court->id,
            ])
            ->assertForbidden();
    }

    public function test_booked_dates_endpoint_lists_taken_days_and_skips_this_request(): void
    {
        $staff = $this->staff();
        $mine = $this->assignedDocument($staff, 'SPD-SCH-MINE');
        $theirs = $this->assignedDocument($staff, 'SPD-SCH-THEIRS');
        $mineDate = $this->date();
        $theirsDate = now()->addDays(11)->toDateString();

        foreach ([[$mine, $mineDate], [$theirs, $theirsDate]] as [$document, $date]) {
            $this->actingAs($staff)->patchJson(route('documents.schedule', $document), [
                'scheduled_date' => $date,
                'resource_id' => $this->court->id,
            ])->assertOk();
        }

        $response = $this->actingAs($staff)
            ->getJson(route('resources.booked-dates', $this->court).'?ignore_document='.$mine->id)
            ->assertOk();

        $this->assertContains($theirsDate, $response->json('dates'));
        $this->assertNotContains($mineDate, $response->json('dates'));
    }

    public function test_the_cockpit_renders_the_scheduling_panel(): void
    {
        $staff = $this->staff();
        $this->assignedDocument($staff);

        $this->actingAs($staff)
            ->get(route('staff.profile', ['user' => $staff->id, 'tab' => 'assigned']))
            ->assertOk()
            ->assertSee('Scheduled date')
            ->assertSee('Covered Court');
    }
}
