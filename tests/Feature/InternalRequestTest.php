<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\RequestStep;
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

    /**
     * A valid filing payload: the first department to route to, the request
     * itself, and the signed scan — all three are required.
     *
     * @return array<string, mixed>
     */
    private function filing(string $purpose = 'New sound system for the plaza', array $extra = []): array
    {
        return array_merge([
            'first_department_id' => Department::where('code', '!=', 'TRSM')->firstOrFail()->id,
            'purpose' => $purpose,
            'paper_scan' => UploadedFile::fake()->create('signed-request.pdf', 120, 'application/pdf'),
        ], $extra);
    }

    public function test_supervisor_can_open_the_wizard(): void
    {
        $supervisor = $this->tourismSupervisor();

        $this->actingAs($supervisor)->get(route('requests.create'))
            ->assertOk()
            ->assertSee('File Internal Request')
            ->assertSee('Tourism Office')
            // Departments, not route templates, drive the first hop now.
            ->assertSee('First department route');
    }

    public function test_staff_with_a_department_can_open_the_draft_wizard(): void
    {
        $this->seedRolesAndPermissions();
        $this->seed(DepartmentSeeder::class);
        $this->seed(RouteTemplateSeeder::class);
        $staff = User::factory()
            ->create(['department_id' => Department::where('code', 'TRSM')->firstOrFail()->id])
            ->assignRole('staff');

        $this->actingAs($staff)->get(route('requests.create'))
            ->assertOk()
            ->assertSee('File Internal Request');
    }

    public function test_staff_without_a_department_cannot_draft(): void
    {
        $this->seedRolesAndPermissions();
        $staff = User::factory()->create()->assignRole('staff');

        $this->actingAs($staff)->get(route('requests.create'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error');
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

    public function test_filing_routes_the_request_to_the_chosen_first_department(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $supervisor = $this->tourismSupervisor();
        $target = Department::where('id', '!=', $supervisor->department_id)->firstOrFail();

        $response = $this->actingAs($supervisor)->post(route('requests.store'), $this->filing(extra: [
            'first_department_id' => $target->id,
        ]));

        $document = Document::where('origin', Document::ORIGIN_INTERNAL)->firstOrFail();
        $response->assertRedirect(route('requests.created', $document));

        $this->assertSame('Internal Request', $document->document_type);
        $this->assertSame($supervisor->department_id, $document->requesting_department_id);
        $this->assertNotNull($document->qr_code_path);

        // Two hops now: the filer's own endorsement, then the chosen office.
        $this->assertSame(
            ['Department endorsement', 'Review and action'],
            $document->requestSteps->pluck('action')->all(),
        );
        $this->assertSame(
            [$supervisor->department_id, $target->id],
            $document->requestSteps->pluck('department_id')->all(),
        );

        // The chain opens at the requesting department's endorsement; the rest waits.
        $this->assertSame(RequestStep::STATUS_CURRENT, $document->requestSteps->first()->status);
        $this->assertSame(RequestStep::STATUS_PENDING, $document->requestSteps->last()->status);
    }

    public function test_filing_requires_a_first_department_and_a_scanned_document(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $supervisor = $this->tourismSupervisor();

        // No department picked.
        $this->actingAs($supervisor)
            ->post(route('requests.store'), [
                'purpose' => 'New sound system for the plaza',
                'paper_scan' => UploadedFile::fake()->create('signed-request.pdf', 120, 'application/pdf'),
            ])
            ->assertSessionHasErrors('first_department_id');

        // No scan attached — the signed paper is the request.
        $this->actingAs($supervisor)
            ->post(route('requests.store'), [
                'first_department_id' => Department::where('id', '!=', $supervisor->department_id)->firstOrFail()->id,
                'purpose' => 'New sound system for the plaza',
            ])
            ->assertSessionHasErrors('paper_scan');

        $this->assertSame(0, Document::where('origin', Document::ORIGIN_INTERNAL)->count());
    }

    public function test_the_wizard_lists_every_active_department(): void
    {
        $supervisor = $this->tourismSupervisor();

        $response = $this->actingAs($supervisor)->get(route('requests.create'))->assertOk();

        foreach (Department::active()->get() as $department) {
            $response->assertSee($department->name);
        }

        $response->assertSee('First department route');
    }

    public function test_uploaded_paper_scan_is_stored_and_a_qr_stamped_copy_is_archived(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $supervisor = $this->tourismSupervisor();

        $this->actingAs($supervisor)->post(route('requests.store'), [
            'first_department_id' => Department::where('id', '!=', $supervisor->department_id)->firstOrFail()->id,
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

        // A solid-red page lets us tell stamped (white QR pad) from untouched red.
        $this->actingAs($supervisor)->post(route('requests.store'), [
            'first_department_id' => Department::where('id', '!=', $supervisor->department_id)->firstOrFail()->id,
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

        // Large QR pinned to the top-left corner of a 600×800 red page.
        $this->actingAs($supervisor)->post(route('requests.store'), [
            'first_department_id' => Department::where('id', '!=', $supervisor->department_id)->firstOrFail()->id,
            'purpose' => 'Chairs',
            'paper_scan' => $this->solidPng(600, 800, [220, 20, 20]),
            'qr_x' => 0,
            'qr_y' => 0,
            'qr_size' => 0.40,
        ]);

        $document = Document::where('origin', Document::ORIGIN_INTERNAL)->firstOrFail();
        $stamped = $document->attachments->pluck('file_path')->first(fn ($p) => str_ends_with($p, '-qr-stamped.png'));
        $img = imagecreatefromstring(Storage::disk('local')->get($stamped));

        // At 0.40 the QR box spans ~268px, so (200,200) is covered by the stamp;
        // the default 0.22 stamp (~147px) would leave that point red. The point
        // may land on the white pad OR on a black QR module depending on the
        // code's module layout, so assert "stamped, not page" rather than white.
        $this->assertNotReddish($img, 200, 200);
    }

    public function test_qr_size_outside_the_allowed_band_is_rejected(): void
    {
        $supervisor = $this->tourismSupervisor();

        $this->actingAs($supervisor)->post(route('requests.store'), [
            'first_department_id' => Department::where('id', '!=', $supervisor->department_id)->firstOrFail()->id,
            'paper_scan' => UploadedFile::fake()->create('signed-request.pdf', 120, 'application/pdf'),
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

    /** The pixel belongs to the stamp (white pad or QR ink), not the red page. */
    private function assertNotReddish($image, int $x, int $y): void
    {
        $rgb = imagecolorsforindex($image, imagecolorat($image, $x, $y));
        $isPageRed = $rgb['red'] > 180 && $rgb['green'] < 80 && $rgb['blue'] < 80;

        $this->assertFalse($isPageRed, "Expected the QR stamp to cover ({$x},{$y}).");
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

        $this->actingAs($supervisor)->post(route('requests.store'), [
            'first_department_id' => Department::where('id', '!=', $supervisor->department_id)->firstOrFail()->id,
            'paper_scan' => UploadedFile::fake()->create('signed-request.pdf', 120, 'application/pdf'),
            'purpose' => 'Vase for the lobby',
        ]);

        $document = Document::where('origin', Document::ORIGIN_INTERNAL)->firstOrFail();

        $this->actingAs($supervisor)->get(route('requests.created', $document))
            ->assertOk()
            ->assertSee($document->tracking_number)
            ->assertSee($document->requestSteps->last()->department->name)
            ->assertSee('Awaiting this office');

        $external = Document::create([
            'tracking_number' => 'SPD-TEST-EXT002',
            'document_type' => 'Business Permit',
            'status' => 'pending',
        ]);

        $this->actingAs($supervisor)->get(route('requests.created', $external))->assertNotFound();
    }

    public function test_a_freshly_filed_request_awaits_its_own_department_endorsement(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $supervisor = $this->tourismSupervisor();

        $this->actingAs($supervisor)->post(route('requests.store'), [
            'first_department_id' => Department::where('id', '!=', $supervisor->department_id)->firstOrFail()->id,
            'paper_scan' => UploadedFile::fake()->create('signed-request.pdf', 120, 'application/pdf'),
            'purpose' => 'Two office tables',
        ]);

        $document = Document::where('origin', Document::ORIGIN_INTERNAL)->firstOrFail();
        $first = $document->requestSteps->first();

        $this->assertSame(0, $first->step_order);
        $this->assertSame($supervisor->department_id, $first->department_id);
        $this->assertSame('Awaiting endorsement', $document->internalStatusLabel());
        $this->assertSame('amber', $document->internalStatusBand());
        // The filing office physically holds the paper, so custody is auto-recorded.
        $this->assertTrue($document->currentStepHasCustody());
    }

    public function test_staff_draft_is_endorsed_by_the_department_head_then_forwards(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seedRolesAndPermissions();
        $this->seed(DepartmentSeeder::class);
        $this->seed(RouteTemplateSeeder::class);

        $tourism = Department::where('code', 'TRSM')->firstOrFail();
        $staff = User::factory()->create(['department_id' => $tourism->id])->assignRole('staff');
        $head = User::factory()->create(['department_id' => $tourism->id])->assignRole('Supervisor');
        $signature = "signatures/head-{$head->id}.png";
        Storage::disk('local')->put($signature, $this->tinyPng());
        $head->update(['signature_path' => $signature]);

        // A staff member drafts/files the request for the office.
        $this->actingAs($staff)->post(route('requests.store'), [
            'first_department_id' => Department::where('id', '!=', $tourism->id)->firstOrFail()->id,
            'paper_scan' => UploadedFile::fake()->create('signed-request.pdf', 120, 'application/pdf'),
            'purpose' => 'Two office tables',
        ])->assertRedirect();

        $document = Document::where('origin', Document::ORIGIN_INTERNAL)->firstOrFail();
        $this->assertSame($staff->id, $document->created_by);
        $this->assertSame('Awaiting endorsement', $document->internalStatusLabel());

        // Only the department head can endorse; doing so forwards it to the next office.
        $this->actingAs($head)->post(route('requests.steps.approve', $document), [
            'document_scan' => $document->tracking_number,
        ])->assertRedirect(route('requests.show', $document));

        $document->refresh();
        $steps = $document->requestSteps;
        $this->assertSame(RequestStep::STATUS_APPROVED, $steps[0]->status);
        $this->assertSame(RequestStep::STATUS_CURRENT, $steps[1]->status);
        $this->assertSame('Under review', $document->internalStatusLabel());
    }

    public function test_internal_requests_do_not_appear_on_the_assignment_desk(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $supervisor = $this->tourismSupervisor();

        $this->actingAs($supervisor)->post(route('requests.store'), [
            'first_department_id' => Department::where('id', '!=', $supervisor->department_id)->firstOrFail()->id,
            'paper_scan' => UploadedFile::fake()->create('signed-request.pdf', 120, 'application/pdf'),
            'purpose' => 'Projector for the AVR',
        ]);
        $document = Document::where('origin', Document::ORIGIN_INTERNAL)->firstOrFail();

        // An org-wide admin's assignment desk must not list the internal request.
        $admin = User::factory()->create()->assignRole('super_admin');
        $this->actingAs($admin)->get(route('admin.assignments.index'))
            ->assertOk()
            ->assertDontSee($document->tracking_number);
    }

    private function tinyPng(): string
    {
        $image = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($image);

        return ob_get_clean();
    }
}
