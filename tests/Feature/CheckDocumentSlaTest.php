<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Mail\SlaBreachMail;
use App\Mail\SlaWarningMail;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckDocumentSlaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A document parked in the In Review stage (SLA 48h) for $hours, assigned to a
     * staff member with an email. The sweep now measures time-in-stage from
     * status_changed_at and notifies the assignee.
     */
    private function documentSittingFor(int $hours): Document
    {
        $assignee = User::factory()->create(['email' => 'staff'.uniqid().'@example.com']);

        return Document::create([
            'tracking_number' => 'SPD-SLA-'.uniqid(),
            'document_type' => 'Business Permit',
            'status' => DocumentStatus::InReview->value, // SLA 48h
            'created_by' => $assignee->id,
            'assigned_to' => $assignee->id,
            'status_changed_at' => now()->subHours($hours),
        ]);
    }

    public function test_breach_email_sent_once_and_marker_set(): void
    {
        Mail::fake();
        $document = $this->documentSittingFor(72); // 72h > 48h SLA → breach

        $this->artisan('documents:check-sla')->assertSuccessful();
        Mail::assertSent(SlaBreachMail::class, 1);
        $this->assertNotNull($document->fresh()->sla_breach_notified_at);

        // Second run must not re-send (dedup via marker).
        $this->artisan('documents:check-sla')->assertSuccessful();
        Mail::assertSent(SlaBreachMail::class, 1);
    }

    public function test_warning_email_sent_between_threshold_and_sla(): void
    {
        Mail::fake();
        $document = $this->documentSittingFor(40); // 40/48 = 83% > 75% warning, < 100%

        $this->artisan('documents:check-sla')->assertSuccessful();
        Mail::assertSent(SlaWarningMail::class, 1);
        Mail::assertNotSent(SlaBreachMail::class);
        $this->assertNotNull($document->fresh()->sla_warning_notified_at);
    }

    public function test_no_email_within_sla(): void
    {
        Mail::fake();
        $this->documentSittingFor(5); // well within SLA

        $this->artisan('documents:check-sla')->assertSuccessful();
        Mail::assertNothingSent();
    }
}
