<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use App\Notifications\DocumentEvent;
use App\Support\TrackingQr;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The team's internal-request flow: a request either has a hard copy — a
 * folder each office scans in — or is a soft copy only. Without paper there is
 * nothing to carry, so the filing office names who receives it at the first
 * office, and it reaches them (and notifies them) the moment it is filed.
 *
 * It still has to be RECEIVED before anything happens: the person it was sent
 * to (or their supervisor) scans its QR, or drags the QR image onto the
 * request — "QR image" in the trail. Only then can they approve, return or deny.
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

    /** Receive it the way the page does: the QR read from its picture. */
    private function receive(Document $document, User $as): void
    {
        $this->actingAs($as)
            ->post(route('documents.custody.store', $document), [
                'capture_method' => 'image',
                'scanned_value' => TrackingQr::payload($document->tracking_number),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
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
        // No paper, so no custody trail to start — and it is not received yet.
        $this->assertSame(0, $document->custodyEvents()->count());
        $this->assertFalse($document->currentStepHasCustody());
        $this->assertSame('Awaiting receipt', $document->internalStatusLabel());

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

    public function test_nothing_happens_until_it_is_received(): void
    {
        $document = $this->file($this->softCopy());

        $this->actingAs($this->gsoStaff)
            ->post(route('requests.steps.complete', $document), ['remarks' => 'Chairs released.'])
            ->assertSessionHasErrors('custody');

        $this->assertNotSame(DocumentStatus::Completed, $document->fresh()->statusEnum());
    }

    public function test_dropping_its_qr_image_receives_it(): void
    {
        $document = $this->file($this->softCopy());

        $this->receive($document, $this->gsoStaff);

        $document->refresh();
        $this->assertTrue($document->currentStepHasCustody());
        $this->assertSame('Under review', $document->internalStatusLabel());
        $this->assertSame('image', $document->currentCustody()->capture_method);
        $this->actingAs($this->gsoStaff)->get(route('requests.show', $document))
            ->assertOk()
            ->assertSee('QR image');
    }

    public function test_snipping_its_qr_off_the_screen_receives_it(): void
    {
        Notification::fake();
        $document = $this->file($this->softCopy());

        $this->actingAs($this->gsoStaff)
            ->post(route('documents.custody.store', $document), [
                'capture_method' => 'screen',
                // What the snipping tool reads off an internal request's QR: a
                // link to the tracker carrying the signed payload.
                'scanned_value' => TrackingQr::content($document->tracking_number),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertTrue($document->currentStepHasCustody());
        $this->assertSame('screen', $document->currentCustody()->capture_method);
        $this->actingAs($this->gsoStaff)->get(route('requests.show', $document))->assertSee('QR on screen');

        // The office that filed it hears who picked it up, and where.
        Notification::assertSentTo($this->filer, DocumentEvent::class, fn (DocumentEvent $n): bool => $n->toDatabase($this->filer)['event'] === 'internal_received'
            && str_contains($n->toDatabase($this->filer)['body'], 'received by Paolo Mendoza at General Services Office'));
    }

    public function test_the_filer_hears_about_the_receipt_only_once(): void
    {
        $document = $this->file($this->softCopy());
        $this->receive($document, $this->gsoStaff);
        Notification::fake();

        // A second scan at the same office is not a new receipt.
        $this->receive($document, $this->gsoHead);

        Notification::assertNotSentTo($this->filer, DocumentEvent::class);
    }

    public function test_another_requests_qr_is_refused(): void
    {
        $document = $this->file($this->softCopy());

        $this->actingAs($this->gsoStaff)
            ->post(route('documents.custody.store', $document), [
                'capture_method' => 'image',
                'scanned_value' => TrackingQr::payload('INT-20261019-OTHER2'),
            ])
            ->assertSessionHasErrors('custody');

        $this->assertFalse($document->fresh()->currentStepHasCustody());
    }

    public function test_only_the_person_it_was_sent_to_or_their_supervisor_can_receive_it(): void
    {
        $document = $this->file($this->softCopy());
        $colleague = User::factory()->create(['department_id' => $this->gso->id, 'is_active' => true])->assignRole('staff');

        $this->actingAs($this->budgetStaff)
            ->post(route('documents.custody.store', $document), [
                'capture_method' => 'image',
                'scanned_value' => TrackingQr::payload($document->tracking_number),
            ])
            ->assertSessionHasErrors('custody');

        $this->actingAs($colleague)
            ->post(route('documents.custody.store', $document), [
                'capture_method' => 'image',
                'scanned_value' => TrackingQr::payload($document->tracking_number),
            ])
            ->assertSessionHasErrors('custody');

        // Nor did the stranger pull it onto their own office's desk.
        $this->assertSame($this->gso->id, $document->fresh()->currentRequestStep()->department_id);

        $this->receive($document, $this->gsoHead);
        $this->assertTrue($document->fresh()->currentStepHasCustody());
    }

    public function test_a_paper_request_cannot_be_received_from_a_picture(): void
    {
        $document = $this->file($this->softCopy(['has_hard_copy' => '1', 'recipient_id' => null]));

        $this->actingAs($this->gsoHead)
            ->post(route('documents.custody.store', $document), [
                'capture_method' => 'image',
                'scanned_value' => TrackingQr::payload($document->tracking_number),
            ])
            ->assertSessionHasErrors('custody');
    }

    public function test_once_received_the_named_staff_member_decides_without_a_second_scan(): void
    {
        $document = $this->file($this->softCopy());
        $this->withSignature($this->gsoStaff);
        $this->receive($document, $this->gsoStaff);

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
        $this->receive($document, $this->gsoHead);

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
        $this->receive($document, $this->gsoStaff);

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
        // A receipt at GSO does not carry over to Budget.
        $this->assertSame('Awaiting receipt', $document->fresh()->internalStatusLabel());
    }

    public function test_a_hard_copy_still_needs_the_folder_scanned_before_a_decision(): void
    {
        $document = $this->file($this->softCopy(['has_hard_copy' => '1', 'recipient_id' => null]));

        $this->actingAs($this->gsoHead)
            ->post(route('requests.steps.return', $document), ['remarks' => 'Attach the event programme, please.'])
            ->assertSessionHasErrors('custody');
    }

    public function test_the_detail_page_asks_for_the_qr_then_offers_the_decision(): void
    {
        $document = $this->file($this->softCopy());

        // Before: receive it — snip its QR off the screen, or use the camera.
        $this->actingAs($this->gsoStaff)->get(route('requests.show', $document))
            ->assertOk()
            ->assertSee('Receive this request')
            ->assertSee('This request was sent to you.')
            ->assertSee('Select the QR on screen')
            // One click opens the request's QR and starts the snip over it.
            // (x-on:, not @: Blade swallowed "@show" as its own directive.)
            ->assertSee("snip('page')", false)
            ->assertSee('x-on:show-request-qr.window=', false)
            ->assertSee('QR open in another window?')
            // Every scanner draws the shared square camera frame.
            ->assertSee('<div id="custodyReader" class="qr-surface"></div>', false)
            ->assertSee('Soft copy only — no paper folder')
            ->assertDontSee('Scan to take custody')
            ->assertDontSee('Record custody manually');

        $this->receive($document, $this->gsoStaff);

        // After: the decision, with no second scan.
        $this->actingAs($this->gsoStaff)->get(route('requests.show', $document))
            ->assertOk()
            ->assertSee('This request was sent to you')
            ->assertSee('there is no paper folder to scan')
            ->assertSee('Received by')
            ->assertDontSee('Receive this request');
    }
}
