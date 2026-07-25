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

    public function test_filing_a_procurement_request_materializes_the_full_chain(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $supervisor = $this->tourismSupervisor();
        $template = RouteTemplate::where('name', 'Procurement Request')->firstOrFail();

        $response = $this->actingAs($supervisor)->post(route('requests.store'), [
            'route_template_id' => $template->id,
            'purpose' => 'New sound system for the plaza',
        ]);

        $document = Document::where('origin', Document::ORIGIN_INTERNAL)->firstOrFail();
        $response->assertRedirect(route('requests.created', $document));

        $this->assertSame('Procurement Request', $document->document_type);
        $this->assertSame($supervisor->department_id, $document->requesting_department_id);
        $this->assertNotNull($document->qr_code_path);

        // The route drives the chain directly — no amount branching.
        $actions = $document->requestSteps->pluck('action')->all();
        $this->assertSame(['Approve request', 'Certify fund availability', 'Procurement', 'Delivery & inspection'], $actions);

        // The chain opens at the Mayor's Office hop; everything else waits.
        $this->assertSame(RequestStep::STATUS_CURRENT, $document->requestSteps->first()->status);
        $this->assertSame(
            [RequestStep::STATUS_PENDING],
            $document->requestSteps->slice(1)->pluck('status')->unique()->values()->all(),
        );
    }

    public function test_a_non_monetary_route_materializes_its_own_chain(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $supervisor = $this->tourismSupervisor();
        $template = RouteTemplate::where('name', 'Vehicle Request')->firstOrFail();

        $this->actingAs($supervisor)->post(route('requests.store'), [
            'route_template_id' => $template->id,
            'purpose' => 'Van to ferry delegates to the tourism summit',
        ]);

        $document = Document::where('origin', Document::ORIGIN_INTERNAL)->firstOrFail();
        $this->assertNull($document->amount);
        $this->assertSame(
            ['Approve vehicle use', 'Dispatch vehicle & issue trip ticket'],
            $document->requestSteps->pluck('action')->all(),
        );
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

    public function test_supervisor_chosen_qr_position_is_stamped_where_requested(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $supervisor = $this->tourismSupervisor();
        $template = RouteTemplate::where('name', 'Procurement Request')->firstOrFail();

        // A solid-red page lets us tell stamped (white QR pad) from untouched red.
        $this->actingAs($supervisor)->post(route('requests.store'), [
            'route_template_id' => $template->id,
            'purpose' => 'Chairs',
            'paper_scan' => $this->solidPng(600, 800, [220, 20, 20]),
            'qr_x' => 0,
            'qr_y' => 0,
        ]);

        $document = Document::where('origin', Document::ORIGIN_INTERNAL)->firstOrFail();
        $stamped = $document->attachments->pluck('file_path')->first(fn ($p) => str_ends_with($p, '-qr-stamped.png'));
        $this->assertNotNull($stamped, 'Expected a QR-stamped copy.');

        $img = imagecreatefromstring(Storage::disk('local')->get($stamped));
        // QR pinned to the top-left; the opposite corner stays the original red.
        $this->assertWhitish($img, 3, 3);
        $this->assertReddish($img, imagesx($img) - 3, imagesy($img) - 3);
    }

    public function test_supervisor_chosen_qr_size_scales_the_stamp(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $supervisor = $this->tourismSupervisor();
        $template = RouteTemplate::where('name', 'Procurement Request')->firstOrFail();

        // Large QR pinned to the top-left corner of a 600×800 red page.
        $this->actingAs($supervisor)->post(route('requests.store'), [
            'route_template_id' => $template->id,
            'purpose' => 'Chairs',
            'paper_scan' => $this->solidPng(600, 800, [220, 20, 20]),
            'qr_x' => 0,
            'qr_y' => 0,
            'qr_size' => 0.40,
        ]);

        $document = Document::where('origin', Document::ORIGIN_INTERNAL)->firstOrFail();
        $stamped = $document->attachments->pluck('file_path')->first(fn ($p) => str_ends_with($p, '-qr-stamped.png'));
        $img = imagecreatefromstring(Storage::disk('local')->get($stamped));

        // At 0.40 the white QR box spans ~268px, so (200,200) is covered; the
        // default 0.22 stamp (~147px) would leave that point red.
        $this->assertWhitish($img, 200, 200);
    }

    public function test_qr_size_outside_the_allowed_band_is_rejected(): void
    {
        $supervisor = $this->tourismSupervisor();
        $template = RouteTemplate::where('name', 'Procurement Request')->firstOrFail();

        $this->actingAs($supervisor)->post(route('requests.store'), [
            'route_template_id' => $template->id,
            'purpose' => 'Chairs',
            'qr_size' => 0.95,
        ])->assertSessionHasErrors('qr_size');
    }

    private function solidPng(int $width, int $height, array $rgb): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, ...$rgb));
        $path = tempnam(sys_get_temp_dir(), 'scan').'.png';
        imagepng($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, 'scan.png', 'image/png', null, true);
    }

    private function assertWhitish($image, int $x, int $y): void
    {
        $rgb = imagecolorsforindex($image, imagecolorat($image, $x, $y));
        $this->assertGreaterThan(230, min($rgb['red'], $rgb['green'], $rgb['blue']), "Expected a white QR pad at ({$x},{$y}).");
    }

    private function assertReddish($image, int $x, int $y): void
    {
        $rgb = imagecolorsforindex($image, imagecolorat($image, $x, $y));
        $this->assertGreaterThan(150, $rgb['red'], "Expected the original red at ({$x},{$y}).");
        $this->assertLessThan(90, $rgb['green'], "Expected the original red at ({$x},{$y}).");
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
        ]);

        $document = Document::where('origin', Document::ORIGIN_INTERNAL)->firstOrFail();

        $this->actingAs($supervisor)->get(route('requests.created', $document))
            ->assertOk()
            ->assertSee($document->tracking_number)
            ->assertSee('Procurement')
            ->assertSee('Awaiting this office');

        $external = Document::create([
            'tracking_number' => 'SPD-TEST-EXT002',
            'document_type' => 'Business Permit',
            'status' => 'pending',
        ]);

        $this->actingAs($supervisor)->get(route('requests.created', $external))->assertNotFound();
    }
}
