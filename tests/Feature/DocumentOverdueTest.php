<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentOverdueTest extends TestCase
{
    use RefreshDatabase;

    private function documentAtDept(Department $dept, User $user): Document
    {
        return Document::create([
            'tracking_number' => 'SPD-OVERDUE-'.uniqid(),
            'document_type' => 'Business Permit',
            'status' => 'in_transit',
            'current_department_id' => $dept->id,
            'created_by' => $user->id,
        ]);
    }

    public function test_document_is_overdue_when_stay_exceeds_sla(): void
    {
        $dept = Department::create(['name' => 'Accounting', 'sla_hours' => 48]);
        $user = User::factory()->create(['department_id' => $dept->id]);
        $document = $this->documentAtDept($dept, $user);

        $document->scans()->create([
            'scanned_by' => $user->id,
            'department_id' => $dept->id,
            'action' => 'in',
            'scanned_at' => now()->subHours(72), // 72h > 48h SLA
            'location_ip' => '127.0.0.1',
        ]);

        $this->assertTrue($document->fresh()->isOverdue());
    }

    public function test_document_is_not_overdue_within_sla(): void
    {
        $dept = Department::create(['name' => 'Accounting', 'sla_hours' => 48]);
        $user = User::factory()->create(['department_id' => $dept->id]);
        $document = $this->documentAtDept($dept, $user);

        $document->scans()->create([
            'scanned_by' => $user->id,
            'department_id' => $dept->id,
            'action' => 'in',
            'scanned_at' => now()->subHours(5), // 5h < 48h SLA
            'location_ip' => '127.0.0.1',
        ]);

        $this->assertFalse($document->fresh()->isOverdue());
    }
}
