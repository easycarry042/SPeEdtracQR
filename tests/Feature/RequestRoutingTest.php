<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\RequestType;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\RouteTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * External vs internal is decided by HOW a request is filed, never by a field
 * someone has to pick: the public form always produces an external (citizen)
 * request, the internal wizard always produces an internal one.
 */
class RequestRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_form_always_files_an_external_request(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seedRolesAndPermissions();

        RequestType::create(['name' => 'Business Permit', 'kind' => RequestType::KIND_DOCUMENT, 'is_active' => true]);

        $this->post(route('public.request.store'), [
            'document_type' => 'Business Permit',
            'citizen_name' => 'Maria Santos',
            'citizen_email' => 'maria@example.com',
            'consent' => '1',
        ])->assertOk();

        $document = Document::latest('id')->first();
        $this->assertSame(Document::ORIGIN_EXTERNAL, $document->origin);
        $this->assertFalse($document->isInternal());
        $this->assertStringStartsWith('SPD-', $document->tracking_number);
    }

    public function test_the_public_form_offers_no_external_internal_choice(): void
    {
        $this->get(route('public.request.create'))
            ->assertOk()
            ->assertDontSee('name="origin"', false)
            ->assertDontSee('Internal request');
    }

    public function test_the_internal_wizard_files_an_internal_request(): void
    {
        $this->seedRolesAndPermissions();
        $this->seed(DepartmentSeeder::class);
        $this->seed(RouteTemplateSeeder::class);

        // The wizard belongs to a department head, so the supervisor needs a dept.
        $supervisor = User::factory()
            ->create(['department_id' => Department::where('code', 'TRSM')->firstOrFail()->id])
            ->assignRole('Supervisor');

        // Origin is stamped by the wizard itself — there is no form field for it.
        $this->actingAs($supervisor)
            ->get(route('requests.create'))
            ->assertOk()
            ->assertDontSee('name="origin"', false);
    }
}
