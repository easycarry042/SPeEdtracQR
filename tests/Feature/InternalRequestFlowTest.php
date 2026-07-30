<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\RequestStep;
use App\Models\User;
use App\Notifications\DocumentEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InternalRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    private Department $tourism;

    private Department $mayor;

    private Department $budget;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        $this->seedRolesAndPermissions();

        $this->tourism = Department::factory()->create(['name' => 'Tourism Office', 'code' => 'TRSM']);
        $this->mayor = Department::factory()->create(['name' => 'Office of the Mayor', 'code' => 'OM']);
        $this->budget = Department::factory()->create(['name' => 'Municipal Budget Office', 'code' => 'BO']);
    }

    private function supervisorOf(Department $department, bool $withSignature = true): User
    {
        $user = User::factory()
            ->create(['department_id' => $department->id])
            ->assignRole('Supervisor');

        if ($withSignature) {
            $path = "signatures/user-{$user->id}.png";
            Storage::disk('local')->put($path, $this->tinyPng());
            $user->update(['signature_path' => $path]);
        }

        return $user;
    }

    /** Two-hop internal request: Mayor (current) → Budget (pending). */
    private function makeRequest(): Document
    {
        $document = Document::create([
            'tracking_number' => 'INT-'.now()->format('Ymd').'-K7M9Q2',
            'document_type' => 'Procurement Request',
            'purpose' => 'Chairs for the lobby',
            'status' => DocumentStatus::Pending->value,
            'status_changed_at' => now(),
            'origin' => Document::ORIGIN_INTERNAL,
            'requesting_department_id' => $this->tourism->id,
            'amount' => 50_000,
            'created_by' => $this->supervisorOf($this->tourism)->id,
        ]);

        $document->requestSteps()->createMany([
            ['step_order' => 1, 'department_id' => $this->mayor->id, 'action' => 'Approve request', 'status' => RequestStep::STATUS_CURRENT, 'started_at' => now()],
            ['step_order' => 2, 'department_id' => $this->budget->id, 'action' => 'Certify fund availability', 'status' => RequestStep::STATUS_PENDING],
        ]);

        return $document;
    }

    private function tinyPng(): string
    {
        $image = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($image);

        return ob_get_clean();
    }

    /** Record that $user physically holds the folder for the request's current hop. */
    private function takeCustody(Document $document, User $user, string $method = 'scan'): void
    {
        $document->custodyEvents()->create([
            'user_id' => $user->id,
            'capture_method' => $method,
            'override_reason' => $method === 'manual' ? 'QR sticker torn off the folder' : null,
        ]);
    }

    public function test_supervisor_can_register_view_and_remove_their_signature(): void
    {
        $supervisor = $this->supervisorOf($this->mayor, withSignature: false);
        $dataUrl = 'data:image/png;base64,'.base64_encode($this->tinyPng());

        $this->actingAs($supervisor)
            ->post(route('profile.signature.store'), ['signature' => $dataUrl])
            ->assertRedirect()
            ->assertSessionHas('status', 'signature-saved');

        $supervisor->refresh();
        $this->assertNotNull($supervisor->signature_path);
        Storage::disk('local')->assertExists($supervisor->signature_path);

        $this->actingAs($supervisor)->get(route('profile.signature.show'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->actingAs($supervisor)
            ->delete(route('profile.signature.destroy'))
            ->assertRedirect();

        $this->assertNull($supervisor->fresh()->signature_path);
    }

    public function test_garbage_signature_payload_is_rejected(): void
    {
        $supervisor = $this->supervisorOf($this->mayor, withSignature: false);

        $this->actingAs($supervisor)
            ->post(route('profile.signature.store'), ['signature' => 'data:image/png;base64,not-a-png'])
            ->assertSessionHasErrors('signature');

        $this->assertNull($supervisor->fresh()->signature_path);
    }

    public function test_current_hop_supervisor_approves_and_the_chain_advances(): void
    {
        Notification::fake();
        $document = $this->makeRequest();
        $mayorSupervisor = $this->supervisorOf($this->mayor);
        $budgetSupervisor = $this->supervisorOf($this->budget);
        $this->takeCustody($document, $mayorSupervisor);

        $this->actingAs($mayorSupervisor)
            ->post(route('requests.steps.approve', $document), [
                'document_scan' => $document->tracking_number,
                'remarks' => 'Approved for the lobby refresh.',
            ])
            ->assertRedirect(route('requests.show', $document));

        $document->refresh();
        [$first, $second] = $document->requestSteps->all();

        $this->assertSame(RequestStep::STATUS_APPROVED, $first->status);
        $this->assertSame($mayorSupervisor->id, $first->acted_by);
        $this->assertNotNull($first->signature_path);
        Storage::disk('local')->assertExists($first->signature_path);

        $this->assertSame(RequestStep::STATUS_CURRENT, $second->status);
        $this->assertNotNull($second->started_at);
        $this->assertSame(DocumentStatus::InProgress, $document->statusEnum());

        // The next office's supervisors are pinged that the paper is coming.
        Notification::assertSentTo($budgetSupervisor, DocumentEvent::class);
    }

    public function test_the_holding_office_chooses_where_the_request_goes_next(): void
    {
        $document = $this->makeRequest();
        $document->requestSteps()->where('step_order', 1)->update(['status' => RequestStep::STATUS_APPROVED]);
        $document->requestSteps()->where('step_order', 2)->update(['status' => RequestStep::STATUS_CURRENT, 'started_at' => now()]);
        $budgetSupervisor = $this->supervisorOf($this->budget);
        $this->takeCustody($document, $budgetSupervisor);

        // Past the hops queued at filing, approving must name the next office.
        $this->actingAs($budgetSupervisor)
            ->post(route('requests.steps.approve', $document), ['document_scan' => $document->tracking_number])
            ->assertSessionHasErrors('next_department_id');

        $engineering = Department::factory()->create(['name' => 'Engineering Office', 'code' => 'ENGR']);

        $this->actingAs($budgetSupervisor)
            ->post(route('requests.steps.approve', $document), [
                'document_scan' => $document->tracking_number,
                'next_department_id' => $engineering->id,
            ])
            ->assertRedirect(route('requests.show', $document));

        // The chain grew by the chosen office, and approving never completes.
        $document->refresh();
        $current = $document->currentRequestStep();
        $this->assertNotNull($current);
        $this->assertSame($engineering->id, $current->department_id);
        $this->assertSame(DocumentStatus::InProgress, $document->statusEnum());
        $this->assertNull($document->completed_at);
    }

    public function test_an_office_cannot_forward_a_request_to_itself(): void
    {
        $document = $this->makeRequest();
        $document->requestSteps()->where('step_order', 1)->update(['status' => RequestStep::STATUS_APPROVED]);
        $document->requestSteps()->where('step_order', 2)->update(['status' => RequestStep::STATUS_CURRENT, 'started_at' => now()]);
        $budgetSupervisor = $this->supervisorOf($this->budget);
        $this->takeCustody($document, $budgetSupervisor);

        $this->actingAs($budgetSupervisor)
            ->post(route('requests.steps.approve', $document), [
                'document_scan' => $document->tracking_number,
                'next_department_id' => $this->budget->id,
            ])
            ->assertSessionHasErrors('next_department_id');
    }

    public function test_scanning_custody_logs_the_receiving_department_onto_the_chain(): void
    {
        $document = $this->makeRequest();
        $document->requestSteps()->where('step_order', 1)->update(['status' => RequestStep::STATUS_APPROVED]);
        $document->requestSteps()->where('step_order', 2)->update(['status' => RequestStep::STATUS_CURRENT, 'started_at' => now()]);

        // A scan must carry a tracking number this system issued.
        $document->update(['tracking_number' => 'INT-'.now()->format('Ymd').'-K7M9Q2']);

        // A fourth office receives the paper — it was never on the planned route.
        $engineering = Department::factory()->create(['name' => 'Engineering Office', 'code' => 'ENGR']);
        $engineer = $this->supervisorOf($engineering);

        $this->actingAs($engineer)
            ->post(route('documents.custody.store', $document), [
                'capture_method' => 'scan',
                'scanned_value' => $document->tracking_number,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $document->refresh();
        $current = $document->currentRequestStep();
        $this->assertNotNull($current);
        $this->assertSame($engineering->id, $current->department_id);

        // The office that was holding it is logged as having passed it on.
        $this->assertSame(
            RequestStep::STATUS_FORWARDED,
            $document->requestSteps()->where('step_order', 2)->first()->status,
        );

        // Still not complete — only "mark as done" ends the chain. (Custody is
        // additive: receiving the folder never moves the document's status.)
        $this->assertNotSame(DocumentStatus::Completed, $document->statusEnum());
    }

    public function test_the_holding_office_marks_the_request_done_to_complete_it(): void
    {
        $document = $this->makeRequest();
        $mayorSupervisor = $this->supervisorOf($this->mayor);
        $this->takeCustody($document, $mayorSupervisor);

        $this->actingAs($mayorSupervisor)
            ->post(route('requests.steps.complete', $document), [
                'document_scan' => $document->tracking_number,
                'remarks' => 'Chairs delivered to the lobby.',
            ])
            ->assertRedirect(route('requests.show', $document));

        $document->refresh();
        $this->assertSame(DocumentStatus::Completed, $document->statusEnum());
        $this->assertNotNull($document->completed_at);
        $this->assertNull($document->currentRequestStep());

        // Hops still queued behind the finished one are moot.
        $this->assertSame(
            RequestStep::STATUS_SKIPPED,
            $document->requestSteps()->where('step_order', 2)->first()->status,
        );
    }

    public function test_wrong_department_supervisor_cannot_act(): void
    {
        $document = $this->makeRequest();

        // Budget's hop is not open yet — its supervisor must wait for the Mayor's.
        $budgetSupervisor = $this->supervisorOf($this->budget);

        $this->actingAs($budgetSupervisor)
            ->post(route('requests.steps.approve', $document), ['document_scan' => $document->tracking_number])
            ->assertForbidden();

        $this->assertSame(RequestStep::STATUS_CURRENT, $document->requestSteps->first()->status);
    }

    public function test_a_foreign_or_mismatched_qr_blocks_the_decision(): void
    {
        $document = $this->makeRequest();
        $mayorSupervisor = $this->supervisorOf($this->mayor);
        $this->takeCustody($document, $mayorSupervisor);

        // A QR this system never issued.
        $this->actingAs($mayorSupervisor)
            ->post(route('requests.steps.approve', $document), ['document_scan' => 'https://example.com/pay/12345'])
            ->assertSessionHasErrors('document_scan');

        // A valid code, but for the folder on the next desk.
        $this->actingAs($mayorSupervisor)
            ->post(route('requests.steps.approve', $document), [
                'document_scan' => 'INT-'.now()->format('Ymd').'-ZZZ999',
            ])
            ->assertSessionHasErrors('document_scan');

        $this->assertSame(RequestStep::STATUS_CURRENT, $document->fresh()->requestSteps->first()->status);
    }

    public function test_approval_requires_a_registered_signature(): void
    {
        $document = $this->makeRequest();
        $mayorSupervisor = $this->supervisorOf($this->mayor, withSignature: false);
        $this->takeCustody($document, $mayorSupervisor);

        $this->actingAs($mayorSupervisor)
            ->post(route('requests.steps.approve', $document), ['document_scan' => $document->tracking_number])
            ->assertSessionHasErrors('signature');

        $this->assertSame(RequestStep::STATUS_CURRENT, $document->fresh()->requestSteps->first()->status);
    }

    public function test_denying_a_hop_is_terminal_and_requires_remarks(): void
    {
        $document = $this->makeRequest();
        $mayorSupervisor = $this->supervisorOf($this->mayor);
        $this->takeCustody($document, $mayorSupervisor);

        $this->actingAs($mayorSupervisor)
            ->post(route('requests.steps.deny', $document), ['document_scan' => $document->tracking_number])
            ->assertSessionHasErrors('remarks');

        $this->actingAs($mayorSupervisor)
            ->post(route('requests.steps.deny', $document), [
                'document_scan' => $document->tracking_number,
                'remarks' => 'No budget line for decorative items this quarter.',
            ])
            ->assertRedirect(route('requests.show', $document));

        $document->refresh();
        $this->assertSame(DocumentStatus::Denied, $document->statusEnum());
        $this->assertSame(RequestStep::STATUS_DENIED, $document->requestSteps->first()->status);
        // Deny does not affix a signature — only approvals do.
        $this->assertNull($document->requestSteps->first()->signature_path);
    }

    public function test_returning_a_hop_sends_the_request_back_for_revision(): void
    {
        $document = $this->makeRequest();
        $mayorSupervisor = $this->supervisorOf($this->mayor);
        $this->takeCustody($document, $mayorSupervisor);

        $this->actingAs($mayorSupervisor)
            ->post(route('requests.steps.return', $document), [
                'document_scan' => $document->tracking_number,
                'remarks' => 'Attach three price quotations first.',
            ])
            ->assertRedirect(route('requests.show', $document));

        $document->refresh();
        $this->assertSame(DocumentStatus::Returned, $document->statusEnum());
        $this->assertSame(RequestStep::STATUS_RETURNED, $document->requestSteps->first()->status);
    }

    public function test_show_page_offers_the_action_panel_only_to_the_holding_office(): void
    {
        $document = $this->makeRequest();
        $mayorSupervisor = $this->supervisorOf($this->mayor);

        // Before custody, the holding office sees only the scan-to-take-custody step.
        $this->actingAs($mayorSupervisor)
            ->get(route('requests.show', $document))
            ->assertOk()
            ->assertSee('Scan to take custody')
            ->assertDontSee("Scan this request's QR", false);

        // Once the folder is scanned in, the decision form unlocks.
        $this->takeCustody($document, $mayorSupervisor);
        $this->actingAs($mayorSupervisor)
            ->get(route('requests.show', $document))
            ->assertOk()
            ->assertSee('Your office holds this request')
            ->assertSee("Scan this request's QR", false);

        $this->actingAs($this->supervisorOf($this->budget))
            ->get(route('requests.show', $document))
            ->assertOk()
            ->assertDontSee('Your office holds this request')
            ->assertSee('Awaiting');

        $staff = User::factory()->create()->assignRole('staff');
        $this->actingAs($staff)
            ->get(route('requests.show', $document))
            ->assertOk()
            ->assertDontSee('Your office holds this request');
    }

    public function test_endorsement_is_locked_until_the_office_scans_the_folder(): void
    {
        $document = $this->makeRequest();
        $mayorSupervisor = $this->supervisorOf($this->mayor);

        // No custody yet: approve, deny, and return are all refused.
        foreach (['requests.steps.approve', 'requests.steps.deny', 'requests.steps.return'] as $route) {
            $this->actingAs($mayorSupervisor)
                ->post(route($route, $document), ['document_scan' => $document->tracking_number, 'remarks' => 'x'])
                ->assertSessionHasErrors('custody');
        }

        $this->assertSame(RequestStep::STATUS_CURRENT, $document->fresh()->requestSteps->first()->status);

        // After scanning the folder, the same approval goes through.
        $this->takeCustody($document, $mayorSupervisor);
        $this->actingAs($mayorSupervisor)
            ->post(route('requests.steps.approve', $document), ['document_scan' => $document->tracking_number])
            ->assertRedirect(route('requests.show', $document));

        $this->assertSame(RequestStep::STATUS_APPROVED, $document->fresh()->requestSteps->first()->status);
    }

    public function test_manual_override_custody_satisfies_the_gate(): void
    {
        $document = $this->makeRequest();
        $mayorSupervisor = $this->supervisorOf($this->mayor);

        // The audited "QR unreadable" fallback still counts as custody.
        $this->takeCustody($document, $mayorSupervisor, method: 'manual');

        $this->actingAs($mayorSupervisor)
            ->post(route('requests.steps.approve', $document), ['document_scan' => $document->tracking_number])
            ->assertRedirect(route('requests.show', $document));

        $this->assertSame(RequestStep::STATUS_APPROVED, $document->fresh()->requestSteps->first()->status);
    }

    public function test_custody_by_another_office_does_not_unlock_this_hop(): void
    {
        $document = $this->makeRequest();
        $mayorSupervisor = $this->supervisorOf($this->mayor);

        // A Budget-office user holding the folder is not the office that holds
        // this hop, so the Mayor's endorsement stays locked.
        $this->takeCustody($document, $this->supervisorOf($this->budget));

        $this->assertFalse($document->currentStepHasCustody());

        $this->actingAs($mayorSupervisor)
            ->post(route('requests.steps.approve', $document), ['document_scan' => $document->tracking_number])
            ->assertSessionHasErrors('custody');
    }

    public function test_custody_taken_before_the_hop_began_does_not_carry_over(): void
    {
        $document = $this->makeRequest();
        $mayorSupervisor = $this->supervisorOf($this->mayor);

        // Custody recorded before this hop started (e.g. while filing) is stale.
        $stale = $document->custodyEvents()->create([
            'user_id' => $mayorSupervisor->id,
            'capture_method' => 'scan',
        ]);
        $stale->forceFill(['created_at' => now()->subDay()])->save();

        $this->assertFalse($document->currentStepHasCustody());

        $this->actingAs($mayorSupervisor)
            ->post(route('requests.steps.approve', $document), ['document_scan' => $document->tracking_number])
            ->assertSessionHasErrors('custody');
    }

    public function test_step_signature_is_served_to_staff_and_hidden_from_guests(): void
    {
        $document = $this->makeRequest();
        $mayorSupervisor = $this->supervisorOf($this->mayor);
        $this->takeCustody($document, $mayorSupervisor);
        $this->actingAs($mayorSupervisor)
            ->post(route('requests.steps.approve', $document), ['document_scan' => $document->tracking_number]);

        $step = $document->requestSteps()->first();
        $this->assertNotNull($step->signature_path);

        $staff = User::factory()->create()->assignRole('staff');
        $this->actingAs($staff)
            ->get(route('requests.steps.signature', $step))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        auth()->logout();
        $this->get(route('requests.steps.signature', $step))->assertRedirect(route('login'));
    }
}
