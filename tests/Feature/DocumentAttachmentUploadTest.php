<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentAttachmentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_upload_multiple_attachments_to_a_document(): void
    {
        Storage::fake('local');
        $this->seedRolesAndPermissions();

        $dept = Department::create(['name' => 'Reception', 'sla_hours' => 48]);
        $user = User::factory()->create(['department_id' => $dept->id])->assignRole('staff');

        $document = Document::create([
            'tracking_number' => 'SPD-TEST-IMG01',
            'document_type' => 'Business Permit',
            'status' => 'in_transit',
            'current_department_id' => $dept->id,
            'created_by' => $user->id,
        ]);
        $document->syncRouteSteps([$dept->id]);

        $response = $this->actingAs($user)->postJson(route('documents.attachments.store', $document), [
            'attachments' => [
                UploadedFile::fake()->image('page-1.jpg'),
                UploadedFile::fake()->image('page-2.jpg'),
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('attachments.0.url', fn ($url) => is_string($url) && $url !== '')
            ->assertJsonPath('attachments.1.url', fn ($url) => is_string($url) && $url !== '');

        $this->assertEquals(2, $document->attachments()->count());
    }
}
