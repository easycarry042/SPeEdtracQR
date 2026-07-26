<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Custody capture verification: taking physical custody requires a live scan
 * of THIS folder's QR (verified server-side), with a required-reason manual
 * override as the audited fallback.
 */
class DocumentCustodyTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seedRolesAndPermissions();

        return User::factory()->create()->assignRole('staff');
    }

    private function doc(User $staff): Document
    {
        return Document::create([
            // Real production format — the custody scan regex depends on it.
            'tracking_number' => 'SPD-'.now()->format('Ymd').'-'.substr(strtoupper(uniqid()), -6),
            'document_type' => 'Business Permit',
            'status' => 'in_progress',
            'assigned_to' => $staff->id,
            'assigned_at' => now(),
            'accepted_at' => now(),
            'created_by' => $staff->id,
        ]);
    }

    public function test_scan_matching_the_document_records_custody(): void
    {
        $staff = $this->staff();
        $doc = $this->doc($staff);

        // The QR encodes the tracking URL — extraction, not raw comparison.
        $this->actingAs($staff)
            ->post(route('documents.custody.store', $doc), [
                'capture_method' => 'scan',
                'scanned_value' => url('/track/'.strtolower($doc->tracking_number)),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $custody = $doc->fresh()->currentCustody();
        $this->assertSame('scan', $custody->capture_method);
        $this->assertNull($custody->override_reason);
    }

    public function test_scan_of_a_different_folder_is_refused(): void
    {
        $staff = $this->staff();
        $doc = $this->doc($staff);

        $this->actingAs($staff)
            ->postJson(route('documents.custody.store', $doc), [
                'capture_method' => 'scan',
                'scanned_value' => url('/track/SPD-20260722-WRONGX'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('custody');

        $this->assertSame(0, $doc->custodyEvents()->count());
    }

    public function test_scan_without_a_tracking_number_is_refused(): void
    {
        $staff = $this->staff();
        $doc = $this->doc($staff);

        $this->actingAs($staff)
            ->postJson(route('documents.custody.store', $doc), [
                'capture_method' => 'scan',
                'scanned_value' => 'https://example.com/not-a-tracking-code',
            ])
            ->assertUnprocessable();

        $this->assertSame(0, $doc->custodyEvents()->count());
    }

    public function test_manual_capture_requires_a_reason(): void
    {
        $staff = $this->staff();
        $doc = $this->doc($staff);

        $this->actingAs($staff)
            ->postJson(route('documents.custody.store', $doc), ['capture_method' => 'manual'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('override_reason');

        $this->assertSame(0, $doc->custodyEvents()->count());

        $this->actingAs($staff)
            ->post(route('documents.custody.store', $doc), [
                'capture_method' => 'manual',
                'override_reason' => 'QR sticker torn off the folder',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $custody = $doc->fresh()->currentCustody();
        $this->assertSame('manual', $custody->capture_method);
        $this->assertSame('QR sticker torn off the folder', $custody->override_reason);

        // The audit trail marks the manual path with its reason.
        $entry = Activity::where('subject_type', $doc->getMorphClass())
            ->where('subject_id', $doc->id)
            ->where('description', 'like', 'Recorded custody manually%')
            ->first();
        $this->assertNotNull($entry);
        $this->assertSame('manual', data_get($entry->properties, 'capture_method'));
    }

    public function test_unrelated_user_cannot_record_custody(): void
    {
        $staff = $this->staff();
        $doc = $this->doc($staff);

        // No role, no relation to the document → no custody.
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('documents.custody.store', $doc), [
                'capture_method' => 'scan',
                'scanned_value' => url('/track/'.$doc->tracking_number),
            ])
            ->assertForbidden();
    }
}
