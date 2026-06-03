<?php

namespace Tests\Feature;

use App\Mail\SlaBreachMail;
use App\Mail\SlaWarningMail;
use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckDocumentSlaTest extends TestCase
{
    use RefreshDatabase;

    private function documentSittingFor(int $hours, int $slaHours = 48): Document
    {
        $dept = Department::create(['name' => 'Accounting', 'sla_hours' => $slaHours, 'email' => 'acct@example.com']);
        $user = User::factory()->create(['department_id' => $dept->id]);

        $document = Document::create([
            'tracking_number' => 'SPD-SLA-'.uniqid(),
            'document_type' => 'Business Permit',
            'status' => 'in_transit',
            'current_department_id' => $dept->id,
            'created_by' => $user->id,
        ]);
        $document->scans()->create([
            'scanned_by' => $user->id,
            'department_id' => $dept->id,
            'action' => 'in',
            'scanned_at' => now()->subHours($hours),
            'location_ip' => '127.0.0.1',
        ]);

        return $document;
    }

    public function test_breach_email_sent_once_and_marker_set(): void
    {
        Mail::fake();
        $document = $this->documentSittingFor(72, 48); // overdue

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
        $document = $this->documentSittingFor(40, 48); // 40/48 = 83% > 75% warning, < 100%

        $this->artisan('documents:check-sla')->assertSuccessful();
        Mail::assertSent(SlaWarningMail::class, 1);
        Mail::assertNotSent(SlaBreachMail::class);
        $this->assertNotNull($document->fresh()->sla_warning_notified_at);
    }

    public function test_no_email_within_sla(): void
    {
        Mail::fake();
        $this->documentSittingFor(5, 48); // well within SLA

        $this->artisan('documents:check-sla')->assertSuccessful();
        Mail::assertNothingSent();
    }
}
