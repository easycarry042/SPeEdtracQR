<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use App\Notifications\DocumentEvent;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The team's internal-request flow: a request either has a hard copy — a
 * folder each office scans in — or is a soft copy only. Without paper there is
 * nothing to carry or scan, so the filing office names who receives it at the
 * first office, and it reaches them (and notifies them) the moment it is filed.
 */
class SoftCopyInternalRequestTest extends TestCase
{
    use RefreshDatabase;

    private Department $tourism;

    private Department $gso;

    private Department $budget;

    private User $filer;

    private User $gsoStaff;

    private User $gsoHead;

    private User $budgetStaff;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seedRolesAndPermissions();
        $this->seed(DepartmentSeeder::class);

        $this->tourism = Department::where('code', 'TRSM')->firstOrFail();
        $this->gso = Department::where('code', 'GSO')->firstOrFail();
        $this->budget = Department::where('code', 'BO')->firstOrFail();

        $this->filer = User::factory()->create(['department_id' => $this->tourism->id, 'is_active' => true])->assignRole('Supervisor');
        $this->gsoStaff = User::factory()->create(['department_id' => $this->gso->id, 'is_active' => true, 'name' => 'Paolo Mendoza'])->assignRole('staff');
        $this->gsoHead = User::factory()->create(['department_id' => $this->gso->id, 'is_active' => true])->assignRole('Supervisor');
        $this->budgetStaff = User::factory()->create(['department_id' => $this->budget->id, 'is_active' => true])->assignRole('staff');
    }

    /** @return array<string, mixed> */
    private function softCopy(array $extra = []): array
    {
        return array_merge([
            'first_department_id' => $this->gso->id,
            'purpose' => 'Ten monoblock chairs for the tourism fair',
            'paper_scan' => UploadedFile::fake()->create('request-letter.pdf', 80, 'application/pdf'),
            'has_hard_copy' => '0',
            'recipient_id' => $this->gsoStaff->id,
        ], $extra);
    }

    private function file(array $payload): Document
    {
        $this->actingAs($this->filer)->post(route('requests.store'), $payload)->assertRedirect();

        return Document::internal()->latest('id')->firstOrFail();
    }

    private function withSignature(User $user): User
    {
        $path = "signatures/{$user->id}.png";
        Storage::disk('local')->put($path, 'png');
        $user->update(['signature_path' => $path]);

        return $user;
    }

    // ── filing ───────────────────────────────────────────────────────────

    public function test_a_soft_copy_needs_someone_named_to_receive_it(): void
    {
        $this->actingAs($this->filer)
            ->post(route('requests.store'), $this->softCopy(['recipient_id' => null]))
            ->assertSessionHasErrors('recipient_id');

        $this->assertSame(0, Document::internal()->count());
    }

    public function test_the_recipient_must_work_in_the_first_office(): void
    {
        $this->actingAs($this->filer)
            ->post(route('requests.store'), $this->softCopy(['recipient_id' => $this->budgetStaff->id]))
            ->assertSessionHasErrors('recipient_id');
    }

    public function test_a_soft_copy_reaches_the_named_person_at_once(): void
    {
        Notification::fake();

        $document = $this->file($this->softCopy());
        $step = $document->currentRequestStep();

        $this->assertTrue($document->isSoftCopy());
        $this->assertSame($this->gso->id, $step->department_id);
        $this->assertSame($this->gsoStaff->id, $step->recipient_id);
        // No paper, so no custody trail to start — and nothing to scan in.
        $this->assertSame(0, $document->custodyEvents()->count());
        $this->assertTrue($document->currentStepHasCustody());

        Notification::assertSentTo($this->gsoStaff, DocumentEvent::class);
        Notification::assertSentTo($this->gsoHead, DocumentEvent::class);
    }

    public function test_leaving_the_choice_out_means_a_hard_copy_as_before(): void
    {
        $document = $this->file([
            'first_department_id' => $this->gso->id,
            'purpose' => 'Folding tables',
            'paper_scan' => UploadedFile::fake()->create('letter.pdf', 80, 'application/pdf'),
        ]);

        $this->assertFalse($document->isSoftCopy());
        $this->assertNull($document->currentRequestStep()->recipient_id);
        // Paper must be scanned in by the receiving office before anyone acts.
        $this->assertFalse($document->currentStepHasCustody());
    }

    public function test_the_filing_form_offers_the_hard_copy_choice(): void
    {
        $this->actingAs($this->filer)->get(route('requests.create'))
            ->assertOk()
            ->assertSee('A hard copy goes to the first office')
            ->assertSee('Who in the first office receives it?')
            ->assertSee('name="has_hard_copy"', false)
            // The staff list is fetched per office, not carried on every page.
            ->assertDontSee('Paolo Mendoza');
    }

    public function test_the_form_fetches_who_can_receive_it_at_the_chosen_office(): void
    {
        $inactive = User::factory()->create(['department_id' => $this->gso->id, 'is_active' => false, 'name' => 'Gone Person'])->assignRole('staff');

        $people = $this->actingAs($this->filer)
            ->getJson(route('requests.recipients', $this->gso))
            ->assertOk()
            ->json();

        $names = array_column($people, 'name');
        $this->assertContains('Paolo Mendoza', $names);
        $this->assertNotContains($inactive->name, $names);
        $this->assertNotContains($this->budgetStaff->name, $names);
    }

    // ── acting on it ─────────────────────────────────────────────────────

    public function test_the_named_staff_member_can_act_without_scanning_anything(): void
    {
        $document = $this->file($this->softCopy());
        $this->withSignature($this->gsoStaff);

        $this->assertTrue($document->canActOnCurrentStep($this->gsoStaff));

        $this->actingAs($this->gsoStaff)
            ->post(route('requests.steps.complete', $document), ['remarks' => 'Chairs released.'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(DocumentStatus::Completed, $document->fresh()->statusEnum());
    }

    public function test_other_staff_in_the_office_cannot_act_on_someone_elses_soft_copy(): void
    {
        $document = $this->file($this->softCopy());
        $colleague = User::factory()->create(['department_id' => $this->gso->id, 'is_active' => true])->assignRole('staff');

        $this->assertFalse($document->canActOnCurrentStep($colleague));
        $this->actingAs($colleague)
            ->post(route('requests.steps.return', $document), ['remarks' => 'Please attach the budget.'])
            ->assertForbidden();
    }

    public function test_the_offices_supervisor_can_still_act_on_it(): void
    {
        $document = $this->file($this->softCopy());

        $this->actingAs($this->gsoHead)
            ->post(route('requests.steps.return', $document), ['remarks' => 'Attach the event programme, please.'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(DocumentStatus::Returned, $document->fresh()->statusEnum());
    }

    public function test_forwarding_a_soft_copy_names_who_receives_it_next(): void
    {
        $document = $this->file($this->softCopy());
        $this->withSignature($this->gsoStaff);

        $this->actingAs($this->gsoStaff)
            ->post(route('requests.steps.approve', $document), ['next_department_id' => $this->budget->id])
            ->assertSessionHasErrors('next_recipient_id');

        $this->actingAs($this->gsoStaff)
            ->post(route('requests.steps.approve', $document), [
                'next_department_id' => $this->budget->id,
                'next_recipient_id' => $this->gsoHead->id, // not in Budget
            ])
            ->assertSessionHasErrors('next_recipient_id');

        $this->actingAs($this->gsoStaff)
            ->post(route('requests.steps.approve', $document), [
                'next_department_id' => $this->budget->id,
                'next_recipient_id' => $this->budgetStaff->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $next = $document->fresh()->currentRequestStep();
        $this->assertSame($this->budget->id, $next->department_id);
        $this->assertSame($this->budgetStaff->id, $next->recipient_id);
    }

    public function test_a_hard_copy_still_needs_the_folder_scanned_before_a_decision(): void
    {
        $document = $this->file($this->softCopy(['has_hard_copy' => '1', 'recipient_id' => null]));

        $this->actingAs($this->gsoHead)
            ->post(route('requests.steps.return', $document), ['remarks' => 'Attach the event programme, please.'])
            ->assertSessionHasErrors('custody');
    }

    public function test_the_detail_page_says_it_is_a_soft_copy_and_drops_the_scan_steps(): void
    {
        $document = $this->file($this->softCopy());

        $this->actingAs($this->gsoStaff)->get(route('requests.show', $document))
            ->assertOk()
            ->assertSee('This request was sent to you')
            ->assertSee('Soft copy only — no paper folder')
            ->assertSee('there is no paper folder to scan')
            ->assertDontSee('Scan Trail')
            ->assertDontSee('Scan to take custody');
    }
}
