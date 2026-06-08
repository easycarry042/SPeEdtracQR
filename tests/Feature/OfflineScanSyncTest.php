<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfflineScanSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_offline_scan_batch_syncs_via_api_endpoint(): void
    {
        $this->seedRolesAndPermissions();

        $dept = Department::create(['name' => 'Reception', 'sla_hours' => 48]);
        $user = User::factory()->create(['department_id' => $dept->id])->assignRole('staff');

        $document = Document::create([
            'tracking_number' => 'SPD-TEST-SYNC01',
            'document_type' => 'Business Permit',
            'status' => 'pending',
            'created_by' => $user->id,
        ]);
        $document->syncRouteSteps([$dept->id]);

        $offlineUuid = 'offline-uuid-123';

        $response = $this->actingAs($user)->postJson('/api/scan/sync', [
            'scans' => [[
                'tracking_number' => $document->tracking_number,
                'department_id' => $dept->id,
                'action' => 'in',
                'offline_uuid' => $offlineUuid,
            ]],
        ]);

        $response->assertOk()
            ->assertJsonPath('synced.0.offline_uuid', $offlineUuid);

        $this->assertDatabaseHas('document_scans', [
            'document_id' => $document->id,
            'offline_uuid' => $offlineUuid,
            'action' => 'in',
        ]);

        // Duplicate sync with the same offline_uuid is idempotent.
        $this->actingAs($user)->postJson('/api/scan/sync', [
            'scans' => [[
                'tracking_number' => $document->tracking_number,
                'department_id' => $dept->id,
                'action' => 'in',
                'offline_uuid' => $offlineUuid,
            ]],
        ])->assertOk();

        $this->assertEquals(1, $document->scans()->where('offline_uuid', $offlineUuid)->count());
    }
}
