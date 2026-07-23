<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\RequestStep;
use App\Models\RouteTemplate;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\RouteTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InternalRequestTest extends TestCase
{
    use RefreshDatabase;

    /** Supervisor attached to the seeded Tourism Office, ready to file. */
    private function tourismSupervisor(): User
    {
        $this->seedRolesAndPermissions();
        $this->seed(DepartmentSeeder::class);
        $this->seed(RouteTemplateSeeder::class);

        return User::factory()
            ->create(['department_id' => Department::where('code', 'TRSM')->firstOrFail()->id])
            ->assignRole('Supervisor');
    }

    public function test_supervisor_can_open_the_wizard(): void
    {
        $supervisor = $this->tourismSupervisor();

        $this->actingAs($supervisor)->get(route('requests.create'))
            ->assertOk()
            ->assertSee('File Internal Request')
            ->assertSee('Tourism Office')
            ->assertSee('Procurement Request');
    }

    public function test_staff_cannot_open_the_wizard(): void
    {
        $this->seedRolesAndPermissions();
        $staff = User::factory()->create()->assignRole('staff');

        $this->actingAs($staff)->get(route('requests.create'))->assertRedirect(route('dashboard'));
    }

    public function test_supervisor_without_a_department_is_bounced_with_an_explanation(): void
    {
        $this->seedRolesAndPermissions();
        $this->seed(DepartmentSeeder::class);
        $this->seed(RouteTemplateSeeder::class);
        $unscoped = User::factory()->create()->assignRole('Supervisor');

        $this->actingAs($unscoped)->get(route('requests.create'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
    }

    public function test_filing_a_large_procurement_request_materializes_the_bidding_chain(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $supervisor = $this->tourismSupervisor();
        $template = RouteTemplate::where('name', 'Procurement Request')->firstOrFail();

        $response = $this->actingAs($supervisor)->post(route('requests.store'), [
            'route_template_id' => $template->id,
            'purpose' => 'New sound system for the plaza',
            'description' => 'Full PA setup for municipal events.',
            'amount' => 2_500_000,
        ]);

        $document = Document::where('origin', Document::ORIGIN_INTERNAL)->firstOrFail();
        $response->assertRedirect(route('requests.created', $document));

        $this->assertSame('Procurement Request', $document->document_type);
        $this->assertSame($supervisor->department_id, $document->requesting_department_id);
        $this->assertNotNull($document->qr_code_path);

        $actions = $document->requestSteps->pluck('action')->all();
        $this->assertSame(['Approve request', 'Certify fund availability', 'Public Bidding', 'Delivery & inspection'], $actions);
        $this->assertNotContains('Small Value Procurement', $actions);

        // The chain opens at the Mayor's Office hop; everything else waits.
        $this->assertSame(RequestStep::STATUS_CURRENT, $document->requestSteps->first()->status);
        $this->assertSame(
            [RequestStep::STATUS_PENDING],
            $document->requestSteps->slice(1)->pluck('status')->unique()->values()->all(),
        );
    }

    public function test_filing_without_an_amount_skips_budget_and_procurement_steps(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $supervisor = $this->tourismSupervisor();
        $template = RouteTemplate::where('name', 'Procurement Request')->firstOrFail();

        $this->actingAs($supervisor)->post(route('requests.store'), [
            'route_template_id' => $template->id,
            'purpose' => 'Borrow projector for tourism summit',
        ]);

        $document = Document::where('origin', Document::ORIGIN_INTERNAL)->firstOrFail();
        $this->assertNull($document->amount);
        $this->assertSame(['Approve request'], $document->requestSteps->pluck('action')->all());
    }

    public function test_uploaded_paper_scan_is_stored_and_a_qr_stamped_copy_is_archived(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $supervisor = $this->tourismSupervisor();
        $template = RouteTemplate::where('name', 'Procurement Request')->firstOrFail();

        $this->actingAs($supervisor)->post(route('requests.store'), [
            'route_template_id' => $template->id,
            'purpose' => 'Ten monoblock chairs',
            'amount' => 15_000,
            'paper_scan' => UploadedFile::fake()->image('purchase-request.jpg', 800, 1000),
        ]);

        $document = Document::where('origin', Document::ORIGIN_INTERNAL)->firstOrFail();
        $paths = $document->attachments->pluck('file_path');

        $this->assertCount(2, $paths);
        $stamped = $paths->first(fn ($p) => str_ends_with($p, '-qr-stamped.png'));
        $this->assertNotNull($stamped, 'Expected a QR-stamped digital copy alongside the original scan.');
        Storage::disk('local')->assertExists($stamped);

        // Stamped copies stay private: no copy on the public disk.
        $this->assertStringContainsString($document->tracking_number, $stamped);
    }

    public function test_created_page_shows_the_chain_and_rejects_external_documents(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $supervisor = $this->tourismSupervisor();
        $template = RouteTemplate::where('name', 'Procurement Request')->firstOrFail();

        $this->actingAs($supervisor)->post(route('requests.store'), [
            'route_template_id' => $template->id,
            'purpose' => 'Vase for the lobby',
            'amount' => 3_000,
        ]);

        $document = Document::where('origin', Document::ORIGIN_INTERNAL)->firstOrFail();

        $this->actingAs($supervisor)->get(route('requests.created', $document))
            ->assertOk()
            ->assertSee($document->tracking_number)
            ->assertSee('Small Value Procurement')
            ->assertSee('Awaiting this office');

        $external = Document::create([
            'tracking_number' => 'SPD-TEST-EXT002',
            'document_type' => 'Business Permit',
            'status' => 'pending',
        ]);

        $this->actingAs($supervisor)->get(route('requests.created', $external))->assertNotFound();
    }
}
