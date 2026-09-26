<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\RouteTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The decision-scan gate, and its escape hatch.
 *
 * Scanning the folder's QR is what makes the QR load-bearing rather than
 * decorative, so the gate must hold. But before 2026-09-26 there was no way
 * past it at all: a machine with no working camera could not approve, deny or
 * return an internal request, which stops an office dead. These tests pin both
 * halves — the gate still refuses an unconfirmed decision, and the written
 * override is accepted AND recorded.
 */
class InternalRequestScanOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function supervisor(string $code = 'TRSM'): User
    {
        $this->seedRolesAndPermissions();
        $this->seed(DepartmentSeeder::class);
        $this->seed(RouteTemplateSeeder::class);

        return User::factory()
            ->create(['department_id' => Department::where('code', $code)->firstOrFail()->id])
            ->assignRole('Supervisor');
    }

    /**
     * File an internal request so it sits on the filing office's own hop, and
     * give the filer a registered e-signature — approving is blocked without
     * one, which would otherwise mask what these tests are checking.
     */
    private function fileRequest(User $filer): Document
    {
        Storage::fake('local');
        Storage::fake('public');

        $signature = 'signatures/user-'.$filer->id.'.png';
        Storage::disk('local')->put($signature, 'fake-png-bytes');
        $filer->forceFill(['signature_path' => $signature])->save();

        $this->actingAs($filer)->post(route('requests.store'), [
            'first_department_id' => Department::where('code', 'OM')->firstOrFail()->id,
            'purpose' => 'Vehicle request for the fiesta delegation',
            'paper_scan' => UploadedFile::fake()->create('request.pdf', 120, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        return Document::where('origin', Document::ORIGIN_INTERNAL)->latest('id')->firstOrFail();
    }

    public function test_a_decision_is_refused_with_neither_a_scan_nor_a_reason(): void
    {
        $supervisor = $this->supervisor();
        $doc = $this->fileRequest($supervisor);

        $this->actingAs($supervisor)
            ->post(route('requests.steps.approve', $doc), ['document_scan' => ''])
            ->assertSessionHasErrors('document_scan');

        $this->assertSame('current', $doc->requestSteps()->orderBy('step_order')->first()->status);
    }

    public function test_a_scan_of_the_right_folder_is_accepted(): void
    {
        $supervisor = $this->supervisor();
        $doc = $this->fileRequest($supervisor);

        $this->actingAs($supervisor)
            ->post(route('requests.steps.approve', $doc), [
                'document_scan' => url('/track/'.strtolower($doc->tracking_number)),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $doc->requestSteps()->orderBy('step_order')->first()->status);
    }

    /** Scanning a different folder must never confirm this decision. */
    public function test_a_scan_of_a_different_folder_is_refused(): void
    {
        $supervisor = $this->supervisor();
        $doc = $this->fileRequest($supervisor);

        $this->actingAs($supervisor)
            ->post(route('requests.steps.approve', $doc), [
                'document_scan' => url('/track/INT-20260101-OTHERX'),
            ])
            ->assertSessionHasErrors('document_scan');
    }

    /**
     * The point of the change: no camera must not mean no work. A written
     * reason confirms the decision in place of a scan.
     */
    public function test_a_written_reason_confirms_the_decision_without_a_scan(): void
    {
        $supervisor = $this->supervisor();
        $doc = $this->fileRequest($supervisor);

        $this->actingAs($supervisor)
            ->post(route('requests.steps.approve', $doc), [
                'document_scan' => '',
                'scan_override_reason' => 'No camera on the counter terminal; tracking number checked by hand.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $doc->requestSteps()->orderBy('step_order')->first()->status);
    }

    /** A bare "x" is not a reason — the override has to say something. */
    public function test_a_too_short_reason_is_refused(): void
    {
        $supervisor = $this->supervisor();
        $doc = $this->fileRequest($supervisor);

        $this->actingAs($supervisor)
            ->post(route('requests.steps.approve', $doc), [
                'document_scan' => '',
                'scan_override_reason' => 'no cam',
            ])
            ->assertSessionHasErrors('scan_override_reason');
    }

    /**
     * An override that left no trace would be worse than no override at all —
     * it would quietly undo the custody story the QR gate exists to tell.
     */
    public function test_the_override_is_recorded_in_the_audit_trail_and_the_request_feed(): void
    {
        $supervisor = $this->supervisor();
        $doc = $this->fileRequest($supervisor);
        $reason = 'Sticker torn off the folder cover; verified the number by hand.';

        $this->actingAs($supervisor)
            ->post(route('requests.steps.approve', $doc), [
                'document_scan' => '',
                'scan_override_reason' => $reason,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $doc->id,
            'description' => 'Decision confirmed WITHOUT a QR scan — reason: '.$reason,
        ]);

        $this->assertTrue(
            $doc->comments()->where('body', 'like', '%without scanning the QR%')->exists(),
            'The override must also appear in the request feed, not only the activity log.'
        );
    }
}
