<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentOverdueTest extends TestCase
{
    use RefreshDatabase;

    /** Overdue is now measured per status STAGE from status_changed_at. */
    private function documentInStageFor(int $hours): Document
    {
        $user = User::factory()->create();

        return Document::create([
            'tracking_number' => 'SPD-OVERDUE-'.uniqid(),
            'document_type' => 'Business Permit',
            'status' => DocumentStatus::InReview->value, // SLA 48h
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'status_changed_at' => now()->subHours($hours),
        ]);
    }

    public function test_document_is_overdue_when_stay_exceeds_sla(): void
    {
        $document = $this->documentInStageFor(72); // 72h > 48h SLA

        $this->assertTrue($document->fresh()->isOverdue());
    }

    public function test_document_is_not_overdue_within_sla(): void
    {
        $document = $this->documentInStageFor(5); // 5h < 48h SLA

        $this->assertFalse($document->fresh()->isOverdue());
    }
}
