<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\RequestType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicRequestRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private function permitType(): RequestType
    {
        $this->seedRolesAndPermissions();

        $type = RequestType::create([
            'name' => 'Business Permit',
            'kind' => RequestType::KIND_DOCUMENT,
            'is_active' => true,
        ]);
        $type->requirements()->create(['label' => 'Barangay Business Clearance', 'is_mandatory' => true, 'sort_order' => 0]);
        $type->requirements()->create(['label' => 'Cedula', 'is_mandatory' => false, 'sort_order' => 1]);

        return $type;
    }

    public function test_submitting_a_request_snapshots_its_type_requirements(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->permitType();

        $this->post(route('public.request.store'), [
            'document_type' => 'Business Permit',
            'citizen_name' => 'Jane Dela Cruz',
            'citizen_email' => 'jane@example.com',
            'consent' => '1',
        ])->assertOk();

        $doc = Document::firstOrFail();
        $this->assertCount(2, $doc->requirements);

        $mandatory = $doc->requirements->firstWhere('label', 'Barangay Business Clearance');
        $this->assertTrue($mandatory->is_mandatory);
        $this->assertNull($mandatory->verified_at);
        $this->assertNull($mandatory->uploaded_file_path);
    }

    public function test_a_citizen_can_optionally_upload_a_requirement_file(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $type = $this->permitType();
        $clearance = $type->requirements->firstWhere('label', 'Barangay Business Clearance');

        $this->post(route('public.request.store'), [
            'document_type' => 'Business Permit',
            'citizen_name' => 'Jane Dela Cruz',
            'citizen_email' => 'jane@example.com',
            'consent' => '1',
            'requirements' => [
                $clearance->id => UploadedFile::fake()->image('clearance.jpg'),
            ],
        ])->assertOk();

        $uploaded = Document::firstOrFail()->requirements->firstWhere('label', 'Barangay Business Clearance');
        $this->assertNotNull($uploaded->uploaded_file_path);
        Storage::disk('local')->assertExists($uploaded->uploaded_file_path);
    }
}
