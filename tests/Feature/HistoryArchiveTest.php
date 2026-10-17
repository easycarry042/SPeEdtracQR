<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * History is the completed archive: finished work only, and no row actions —
 * it is a record to read, not a worklist to act on.
 */
class HistoryArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function supervisor(): User
    {
        $this->seedRolesAndPermissions();

        return User::factory()->create(['is_active' => true])->assignRole('Supervisor');
    }

    private function document(string $tracking, string $status): Document
    {
        return Document::create([
            'tracking_number' => $tracking,
            'document_type' => 'Business Permit',
            'citizen_name' => 'Maria Santos',
            'status' => $status,
            'completed_at' => $status === 'completed' ? now() : null,
        ]);
    }

    public function test_history_lists_completed_requests_only(): void
    {
        $user = $this->supervisor();

        $completed = $this->document('SPD-HIS-DONE', 'completed');
        $inProgress = $this->document('SPD-HIS-WIP', 'in_progress');
        $pending = $this->document('SPD-HIS-NEW', 'pending');
        $denied = $this->document('SPD-HIS-NO', 'denied');

        $response = $this->actingAs($user)->get(route('history'))->assertOk();

        $response->assertSee($completed->tracking_number);
        $response->assertDontSee($inProgress->tracking_number);
        $response->assertDontSee($pending->tracking_number);
        $response->assertDontSee($denied->tracking_number);
    }

    public function test_history_rows_carry_no_actions(): void
    {
        $user = $this->supervisor();
        $this->document('SPD-HIS-DONE2', 'completed');

        $this->actingAs($user)
            ->get(route('history'))
            ->assertOk()
            ->assertDontSee('Print QR')
            ->assertDontSee('Sticker');
    }

    public function test_the_csv_export_is_also_completed_only(): void
    {
        $user = $this->supervisor();
        $this->document('SPD-HIS-CSV-DONE', 'completed');
        $this->document('SPD-HIS-CSV-WIP', 'in_progress');

        $csv = $this->actingAs($user)->get(route('history.export'))->assertOk()->getContent();

        $this->assertStringContainsString('SPD-HIS-CSV-DONE', $csv);
        $this->assertStringNotContainsString('SPD-HIS-CSV-WIP', $csv);
    }
}
