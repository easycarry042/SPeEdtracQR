<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Support\DocumentSeal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QR lifecycle pillars: physical custody trail, QR-gated release, and the
 * public authenticity verification with signed URLs.
 */
class QrLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seedRolesAndPermissions();

        return User::factory()->create()->assignRole('staff');
    }

    private function doc(User $staff, array $attributes = []): Document
    {
        return Document::create(array_merge([
            // Real production format — the custody scan regex depends on it.
            'tracking_number' => 'SPD-'.now()->format('Ymd').'-'.substr(strtoupper(uniqid()), -6),
            'document_type' => 'Business Permit',
            'citizen_name' => 'Jane Citizen',
            'status' => 'in_progress',
            'assigned_to' => $staff->id,
            'assigned_at' => now(),
            'accepted_at' => now(),
            'created_by' => $staff->id,
        ], $attributes));
    }

    public function test_staff_can_record_physical_custody_by_scanning(): void
    {
        $staff = $this->staff();
        $doc = $this->doc($staff);

        $scan = ['capture_method' => 'scan', 'scanned_value' => url('/track/'.$doc->tracking_number)];

        $this->actingAs($staff)
            ->post(route('documents.custody.store', $doc), $scan)
            ->assertRedirect();

        $custody = $doc->fresh()->currentCustody();
        $this->assertNotNull($custody);
        $this->assertSame($staff->id, $custody->user_id);

        // A second staff member scanning the folder becomes the new custodian.
        $other = User::factory()->create()->assignRole('staff');
        $this->actingAs($other)->post(route('documents.custody.store', $doc), $scan);

        $this->assertSame($other->id, $doc->fresh()->currentCustody()->user_id);
        $this->assertSame(2, $doc->custodyEvents()->count());
    }

    public function test_release_requires_a_completed_document(): void
    {
        $staff = $this->staff();
        $doc = $this->doc($staff); // in_progress

        $this->actingAs($staff)
            ->patchJson(route('documents.release', $doc))
            ->assertUnprocessable();

        $this->assertNull($doc->fresh()->claimed_at);
    }

    public function test_completed_document_can_be_released_once(): void
    {
        $staff = $this->staff();
        $doc = $this->doc($staff, ['status' => 'completed', 'completed_at' => now()]);

        $this->actingAs($staff)
            ->patchJson(route('documents.release', $doc))
            ->assertOk();

        $doc->refresh();
        $this->assertNotNull($doc->claimed_at);
        $this->assertSame($staff->id, $doc->released_by);

        // Double release refused.
        $this->actingAs($staff)
            ->patchJson(route('documents.release', $doc))
            ->assertUnprocessable();
    }

    public function test_verification_page_confirms_a_genuine_document(): void
    {
        $staff = $this->staff();
        $doc = $this->doc($staff, ['status' => 'completed', 'completed_at' => now()]);

        $this->get(DocumentSeal::url($doc))
            ->assertOk()
            ->assertSee('Genuine record')
            ->assertSee($doc->tracking_number);
    }

    public function test_verification_rejects_a_bad_signature(): void
    {
        $staff = $this->staff();
        $doc = $this->doc($staff, ['status' => 'completed', 'completed_at' => now()]);

        $this->get(route('verify.show', ['trackingNumber' => $doc->tracking_number, 'sig' => 'forgedsignature12345']))
            ->assertOk()
            ->assertSee('Verification failed')
            ->assertDontSee('Genuine record');

        // Missing signature is equally invalid.
        $this->get(route('verify.show', $doc->tracking_number))
            ->assertOk()
            ->assertSee('Verification failed');
    }

    public function test_verification_reports_unknown_tracking_numbers(): void
    {
        $this->get(route('verify.show', ['trackingNumber' => 'SPD-20260722-ZZZZZZ', 'sig' => 'whatever']))
            ->assertOk()
            ->assertSee('Not on record');
    }

    public function test_citizen_page_shows_pickup_and_released_states(): void
    {
        $staff = $this->staff();
        $doc = $this->doc($staff, ['status' => 'completed', 'completed_at' => now()]);

        // Guest (citizen) view: ready for pickup.
        $this->get(route('track.show', $doc->tracking_number))
            ->assertOk()
            ->assertSee('Ready for pickup');

        $doc->forceFill(['claimed_at' => now(), 'released_by' => $staff->id])->save();

        $this->get(route('track.show', $doc->tracking_number))
            ->assertOk()
            ->assertSee('Released');
    }
}
