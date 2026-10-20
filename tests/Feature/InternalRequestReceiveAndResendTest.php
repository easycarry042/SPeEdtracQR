<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use App\Notifications\DocumentEvent;
use App\Support\InternalAnalytics;
use App\Support\InternalInbox;
use App\Support\NavBadges;
use App\Support\TrackingQr;
use Database\Seeders\DepartmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The internal-request flow as the team draws it:
 *
 *   - one scan pulls the request up AND logs it as received (the header's
 *     Scan QR Code), rather than a second scan on the Receive card;
 *   - whoever at the receiving office is handed the paper folder scans it in —
 *     deciding stays with the office's supervisor;
 *   - a request returned for revision can be resent to the office that
 *     returned it (which receives it again) or withdrawn by the filing office.
 */
class InternalRequestReceiveAndResendTest extends TestCase
{
    use RefreshDatabase;

    private Department $tourism;

    private Department $gso;

    private User $filer;

    private User $gsoStaff;

    private User $gsoOtherStaff;

    private User $gsoHead;

    private User $budgetHead;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seedRolesAndPermissions();
        $this->seed(DepartmentSeeder::class);

        $this->tourism = Department::where('code', 'TRSM')->firstOrFail();
        $this->gso = Department::where('code', 'GSO')->firstOrFail();
        $budget = Department::where('code', 'BO')->firstOrFail();

        $this->filer = User::factory()->create(['department_id' => $this->tourism->id, 'is_active' => true])->assignRole('staff');
        $this->gsoStaff = User::factory()->create(['department_id' => $this->gso->id, 'is_active' => true, 'name' => 'Paolo Mendoza'])->assignRole('staff');
        $this->gsoOtherStaff = User::factory()->create(['department_id' => $this->gso->id, 'is_active' => true])->assignRole('staff');
        $this->gsoHead = User::factory()->create(['department_id' => $this->gso->id, 'is_active' => true, 'name' => 'Jose Reyes'])->assignRole('Supervisor');
        $this->budgetHead = User::factory()->create(['department_id' => $budget->id, 'is_active' => true])->assignRole('Supervisor');
    }

    private function file(bool $paper = true): Document
    {
        $this->actingAs($this->filer)->post(route('requests.store'), [
            'first_department_id' => $this->gso->id,
            'purpose' => 'Ten monoblock chairs for the tourism fair',
            'paper_scan' => UploadedFile::fake()->image('request-letter.jpg', 600, 800),
            'has_hard_copy' => $paper ? '1' : '0',
            'recipient_id' => $paper ? null : $this->gsoStaff->id,
        ])->assertRedirect();

        return Document::internal()->latest('id')->firstOrFail();
    }

    /** What the header's Scan QR Code sends after reading the QR. */
    private function scan(Document $document, User $as, string $method = 'scan')
    {
        return $this->actingAs($as)->postJson(route('documents.custody.scan'), [
            'scanned_value' => TrackingQr::content($document->tracking_number),
            'capture_method' => $method,
        ]);
    }

    /** Received at GSO and returned to Tourism for revision. */
    private function returned(bool $paper = true): Document
    {
        $document = $this->file($paper);
        $this->scan($document, $paper ? $this->gsoHead : $this->gsoStaff)->assertJsonPath('received', true);

        $this->actingAs($paper ? $this->gsoHead : $this->gsoStaff)->post(route('requests.steps.return', $document), [
            'remarks' => 'Attach the signed canvass.',
            'document_scan' => $paper ? TrackingQr::payload($document->tracking_number) : null,
        ])->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertSame(DocumentStatus::Returned, $document->statusEnum());

        // Revising takes a while; custody is compared by timestamp.
        $this->travel(2)->hours();

        return $document;
    }

    // ── 1. One scan opens and receives ─────────────────────────────────────

    public function test_scanning_a_folder_sent_to_your_office_opens_it_and_logs_it_received(): void
    {
        $document = $this->file();
        $this->assertSame('Awaiting receipt', $document->internalStatusLabel());
        Notification::fake();

        $this->scan($document, $this->gsoHead)
            ->assertOk()
            ->assertJsonPath('received', true)
            ->assertJsonPath('url', route('requests.show', $document));

        $this->assertTrue($document->currentStepHasCustody());
        $this->assertSame('scan', $document->currentCustody()->capture_method);
        $this->assertSame('Under review', $document->fresh()->internalStatusLabel());
        Notification::assertSentTo($this->filer, DocumentEvent::class, fn (DocumentEvent $n) => $n->event === 'internal_received');

        // The page it opens says so, and the decision is ready.
        $this->actingAs($this->gsoHead)->get(route('requests.show', $document))
            ->assertSee('Custody recorded')
            ->assertSee('Approve &amp; sign', false);
    }

    public function test_a_second_scan_only_opens_it(): void
    {
        $document = $this->file();
        $this->scan($document, $this->gsoStaff)->assertJsonPath('received', true);

        $this->scan($document, $this->gsoHead)->assertOk()->assertJsonPath('received', false);
        $this->assertSame(2, $document->custodyEvents()->count(), 'Filing office + the one receipt.');
    }

    public function test_a_picture_of_a_paper_folders_qr_opens_it_but_does_not_receive_it(): void
    {
        $document = $this->file();

        $this->scan($document, $this->gsoHead, 'image')
            ->assertOk()
            ->assertJsonPath('received', false)
            ->assertJsonPath('url', route('requests.show', $document));

        $this->assertFalse($document->currentStepHasCustody());
    }

    public function test_a_soft_copy_is_received_by_its_recipient_from_the_header_scan_but_not_by_a_colleague(): void
    {
        $document = $this->file(paper: false);

        $this->scan($document, $this->gsoOtherStaff, 'image')->assertJsonPath('received', false);
        $this->assertFalse($document->currentStepHasCustody());

        $this->scan($document, $this->gsoStaff, 'image')->assertJsonPath('received', true);
        $this->assertTrue($document->currentStepHasCustody());
        $this->assertSame('image', $document->currentCustody()->capture_method);
    }

    public function test_another_office_and_citizen_codes_are_only_opened(): void
    {
        $document = $this->file();

        $this->scan($document, $this->budgetHead)->assertOk()->assertJsonPath('received', false);
        $this->assertFalse($document->currentStepHasCustody());

        $citizen = Document::create(['tracking_number' => 'SPD-20261020-ABCDEF', 'document_type' => 'Cedula', 'status' => 'pending']);
        $this->scan($citizen, $this->gsoHead)
            ->assertOk()
            ->assertJsonPath('url', route('track.show', $citizen->tracking_number));
        $this->assertSame(0, $citizen->custodyEvents()->count());

        $this->actingAs($this->gsoHead)->postJson(route('documents.custody.scan'), [
            'scanned_value' => 'https://example.com/not-ours',
            'capture_method' => 'scan',
        ])->assertUnprocessable();
    }

    // ── 2. Any staff at the office scans the folder in ─────────────────────

    public function test_staff_at_the_receiving_office_scan_the_folder_in_but_the_supervisor_decides(): void
    {
        $document = $this->file();
        $this->assertTrue($document->canReceiveCurrentStep($this->gsoStaff));
        $this->assertFalse($document->canActOnCurrentStep($this->gsoStaff));
        $this->assertFalse($document->canReceiveCurrentStep($this->budgetHead));

        $this->actingAs($this->gsoStaff)->get(route('requests.show', $document))
            ->assertSee('This folder was sent to your office')
            ->assertDontSee('Approve &amp; sign', false);

        $this->actingAs($this->gsoStaff)->post(route('documents.custody.store', $document), [
            'capture_method' => 'scan',
            'scanned_value' => TrackingQr::payload($document->tracking_number),
        ])->assertSessionHas('status', fn (string $m) => str_contains($m, 'Your supervisor approves'));

        // Received for the office: the supervisor goes straight to deciding.
        $this->assertTrue($document->currentStepHasCustody());
        $this->actingAs($this->gsoStaff)->get(route('requests.show', $document))
            ->assertDontSee('This folder was sent to your office')
            ->assertDontSee('Approve &amp; sign', false);
        $this->actingAs($this->gsoHead)->get(route('requests.show', $document))
            ->assertSee('Approve &amp; sign', false);

        $this->actingAs($this->gsoStaff)->post(route('requests.steps.return', $document), [
            'remarks' => 'Not mine to decide.',
            'document_scan' => TrackingQr::payload($document->tracking_number),
        ])->assertForbidden();
    }

    public function test_a_soft_copy_is_still_received_only_by_its_recipient_or_their_supervisor(): void
    {
        $document = $this->file(paper: false);

        $this->assertFalse($document->canReceiveCurrentStep($this->gsoOtherStaff));
        $this->assertTrue($document->canReceiveCurrentStep($this->gsoStaff));
        $this->assertTrue($document->canReceiveCurrentStep($this->gsoHead));
    }

    // ── 3. Returned: resend or withdraw ─────────────────────────────────────

    public function test_a_returned_paper_request_is_resent_to_the_office_that_returned_it(): void
    {
        $document = $this->returned();
        $this->assertSame(1, NavBadges::for($this->filer)['internal'], 'Waiting on the filer.');

        $this->actingAs($this->filer)->get(route('requests.show', $document))
            ->assertSee('Returned to your office')
            ->assertSee('Attach the signed canvass.')
            ->assertSee('Resend to '.$this->gso->name);

        Notification::fake();
        $this->actingAs($this->filer)->post(route('requests.resend', $document), [
            'note' => 'Canvass attached.',
            'paper_scan' => UploadedFile::fake()->create('revised.pdf', 40, 'application/pdf'),
        ])->assertRedirect(route('requests.show', $document))->assertSessionHas('status');

        $document->refresh();
        $step = $document->currentRequestStep();
        $this->assertSame($this->gso->id, $step->department_id);
        $this->assertSame('Review the revision', $step->action);
        $this->assertSame(DocumentStatus::Pending, $document->statusEnum());
        $this->assertSame(2, $document->attachments()->where('file_path', 'not like', '%qr-stamped%')->count());
        $this->assertSame(0, NavBadges::for($this->filer)['internal']);
        Notification::assertSentTo($this->gsoHead, DocumentEvent::class, fn (DocumentEvent $n) => $n->event === 'internal_hop');

        // GSO receives it again before deciding.
        $this->assertSame('Awaiting receipt', $document->internalStatusLabel());
        $this->assertFalse($document->currentStepHasCustody());
        $this->scan($document, $this->gsoHead)->assertJsonPath('received', true);
        $this->assertSame('Under review', $document->fresh()->internalStatusLabel());
    }

    public function test_a_resent_soft_copy_goes_back_to_the_same_person(): void
    {
        $document = $this->returned(paper: false);

        $this->actingAs($this->filer)->post(route('requests.resend', $document), ['note' => 'Fixed the amount.'])
            ->assertSessionHasNoErrors();

        $step = $document->fresh()->currentRequestStep();
        $this->assertSame($this->gsoStaff->id, $step->recipient_id);
        $this->assertTrue($document->canActOnCurrentStep($this->gsoStaff));
    }

    public function test_only_the_filing_office_resends_and_only_a_returned_request(): void
    {
        $document = $this->returned();

        $this->actingAs($this->gsoHead)->post(route('requests.resend', $document), ['note' => 'x'])->assertForbidden();
        $this->actingAs($this->filer)->post(route('requests.resend', $document), ['note' => ''])->assertSessionHasErrors('note');

        // A colleague at the filing office may resend it for the filer.
        $colleague = User::factory()->create(['department_id' => $this->tourism->id, 'is_active' => true])->assignRole('Supervisor');
        $this->actingAs($colleague)->post(route('requests.resend', $document), ['note' => 'Done.'])->assertSessionHasNoErrors();

        // Now it is moving again: nothing left to resend or withdraw.
        $this->actingAs($this->filer)->post(route('requests.resend', $document), ['note' => 'Again.'])->assertForbidden();
        $this->actingAs($this->filer)->post(route('requests.withdraw', $document))->assertForbidden();
    }

    public function test_withdrawing_closes_a_returned_request(): void
    {
        $document = $this->returned();
        Notification::fake();

        $this->actingAs($this->filer)->post(route('requests.withdraw', $document), ['reason' => 'No longer needed.'])
            ->assertSessionHas('status');

        $document->refresh();
        $this->assertSame(DocumentStatus::Withdrawn, $document->statusEnum());
        $this->assertSame('Withdrawn', $document->internalStatusLabel());
        $this->assertNull($document->currentRequestStep());
        $this->assertNotNull($document->completed_at);
        Notification::assertSentTo($this->gsoHead, DocumentEvent::class, fn (DocumentEvent $n) => $n->event === 'internal_outcome');

        $inbox = InternalInbox::for($this->filer);
        $this->assertTrue($inbox['closed']->contains($document));
        $this->assertFalse($inbox['filed']->contains($document));

        // Counted apart from denials, and not as still moving.
        $kpis = (new InternalAnalytics(now()->subDay(), now()->addDay()))->kpis();
        $this->assertSame(0, $kpis['denied']);
        $this->assertSame(0, $kpis['moving']);

        $this->actingAs($this->filer)->get(route('requests.show', $document))
            ->assertSee('Withdrawn')
            ->assertDontSee('Returned to your office');
    }

    public function test_withdrawn_stays_off_citizen_pages(): void
    {
        $this->assertNotContains(DocumentStatus::Withdrawn, DocumentStatus::citizenCases());
        $this->assertTrue(DocumentStatus::Withdrawn->isTerminal());
        $this->assertNotContains('withdrawn', DocumentStatus::activeValues());
    }
}
