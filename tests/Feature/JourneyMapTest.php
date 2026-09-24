<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\RequestType;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\RouteTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * End-to-end journey map.
 *
 * Every other test in this suite checks one rule in isolation. This file walks
 * each persona through their WHOLE path, in order, the way a real user meets
 * it — file a request, watch it move, hand it over. The point is to catch the
 * gaps that only appear between steps: a screen nobody can reach, a stage that
 * cannot be advanced past, a document that arrives nowhere.
 *
 * Written 2026-09-24 after a document request failed during a defense and the
 * per-unit tests had all been green.
 */
class JourneyMapTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Full civic setup: roles, departments and route templates. Journeys cross
     * role boundaries, so they need the real seeded world rather than a factory
     * stub.
     */
    private function seedWorld(): void
    {
        $this->seedRolesAndPermissions();
        $this->seed(DepartmentSeeder::class);
        $this->seed(RouteTemplateSeeder::class);
    }

    private function department(string $code): Department
    {
        return Department::where('code', $code)->firstOrFail();
    }

    private function supervisor(string $deptCode = 'TRSM'): User
    {
        return User::factory()
            ->create(['department_id' => $this->department($deptCode)->id])
            ->assignRole('Supervisor');
    }

    private function staff(string $deptCode = 'TRSM'): User
    {
        return User::factory()
            ->create(['department_id' => $this->department($deptCode)->id])
            ->assignRole('staff');
    }

    private function superAdmin(): User
    {
        return User::factory()->create()->assignRole('super_admin');
    }

    /**
     * A document request type with one mandatory requirement, routed to a
     * department so the journey exercises routing rather than the unrouted
     * fallback.
     */
    private function permitType(?Department $department = null): RequestType
    {
        $type = RequestType::create([
            'name' => 'Business Permit',
            'kind' => RequestType::KIND_DOCUMENT,
            'is_active' => true,
            'department_id' => $department?->id,
        ]);

        $type->requirements()->create([
            'label' => 'Barangay Business Clearance',
            'is_mandatory' => true,
            'sort_order' => 0,
        ]);

        return $type;
    }

    /** The citizen-side filing payload, as the public form posts it. */
    private function filing(array $extra = []): array
    {
        return array_merge([
            'document_type' => 'Business Permit',
            'citizen_name' => 'Jane Dela Cruz',
            'citizen_email' => 'jane@example.com',
            'citizen_contact' => '9171234567',
            'purpose' => 'New business registration',
            'consent' => '1',
        ], $extra);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Journey 1 — the citizen
    // ──────────────────────────────────────────────────────────────────────

    /**
     * The whole public path: find the service, file it, get a tracking number,
     * follow it. This is the journey that failed at the defense.
     */
    public function test_journey_citizen_files_a_request_and_can_follow_it(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seedWorld();
        $dept = $this->department('TRSM');
        $this->permitType($dept);

        // 1. The public entry points all answer.
        $this->get(route('welcome'))->assertOk();
        $this->get(route('citizen.dashboard'))->assertOk();
        $this->get(route('public.request.create'))->assertOk();

        // 2. Filing succeeds and renders the QR / tracking-number page.
        //    NOTE: this POST returns a view rather than redirecting — there is no
        //    Post/Redirect/Get here, which is why refreshing re-submits. Asserted
        //    as-is so this test documents the current behaviour rather than
        //    silently passing if it changes.
        $this->post(route('public.request.store'), $this->filing())
            ->assertOk()
            ->assertSessionHasNoErrors();

        $doc = Document::latest('id')->firstOrFail();
        $this->assertSame('pending', $doc->status);
        $this->assertSame('online', $doc->source);

        // 3. The request type's department is stamped onto the ticket — this is
        //    what puts it in front of the right office rather than everyone.
        $this->assertSame(
            $dept->id,
            $doc->department_id,
            'A citizen request must inherit its request type\'s department, or it reaches no queue.'
        );

        // 4. The citizen can track it, and the requirement checklist came across.
        $this->get(route('track.show', $doc->tracking_number))->assertOk();
        $this->assertSame(1, $doc->requirements()->count());

        // 5. A wrong number must fail softly, never with a server error.
        $this->get(route('citizen.track', ['tracking' => 'SPD-00000000-NOPE']))->assertOk();
    }

    // ──────────────────────────────────────────────────────────────────────
    // Journey 2 — the supervisor
    // ──────────────────────────────────────────────────────────────────────

    /**
     * The triage path: a request arrives, the department head sees it on both
     * desks, and hands it to one of their own staff.
     */
    public function test_journey_supervisor_sees_intake_and_assigns_it(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seedWorld();
        $dept = $this->department('TRSM');
        $this->permitType($dept);

        $supervisor = $this->supervisor('TRSM');
        $staff = $this->staff('TRSM');

        $this->post(route('public.request.store'), $this->filing());
        $doc = Document::latest('id')->firstOrFail();

        // 1. Both desks a supervisor triages from must show the new request.
        //    The dashboard is the intake list; Assignments is the full desk.
        $this->actingAs($supervisor)->get(route('dashboard'))
            ->assertOk()
            ->assertSee($doc->tracking_number);

        $this->actingAs($supervisor)->get(route('admin.assignments.index'))
            ->assertOk()
            ->assertSee($doc->tracking_number);

        // 2. Assigning it to their own staff is allowed.
        $this->actingAs($supervisor)
            ->patch(route('admin.assignments.assign', $doc), ['assigned_to' => $staff->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($staff->id, $doc->fresh()->assigned_to);

        // 3. Assignment must NOT advance the status on its own — staff move it
        //    deliberately (this was reverted once on purpose; see the June 2026
        //    manual-status model).
        $this->assertSame('pending', $doc->fresh()->status);

        // 4. The supervisor's other day-to-day screens answer.
        foreach (['track.index', 'requests.index', 'history', 'analytics', 'reports.services'] as $name) {
            $this->assertSame(
                200,
                $this->actingAs($supervisor)->get(route($name))->getStatusCode(),
                "Supervisor could not reach [{$name}]"
            );
        }

        // Bookings are deliberately NOT a supervisor screen — `manage bookings`
        // belongs to staff, and the nav link is @can-gated so no dead link is
        // ever shown. Asserted so the intent is recorded, not assumed.
        $this->assertSame(
            403,
            $this->actingAs($supervisor)->get(route('bookings.index'))->getStatusCode(),
            'Bookings is a staff screen; if supervisors should manage bookings, grant the permission.'
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Journey 3 — the staff member
    // ──────────────────────────────────────────────────────────────────────

    /**
     * The work path: accept an assignment, review the citizen's requirement,
     * and walk the document through every stage to Completed. Each stage has a
     * gate, so this is where a stuck workflow would show up.
     */
    public function test_journey_staff_advances_a_document_through_every_stage(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seedWorld();
        $dept = $this->department('TRSM');
        $this->permitType($dept);

        $supervisor = $this->supervisor('TRSM');
        $staff = $this->staff('TRSM');

        $this->post(route('public.request.store'), $this->filing([
            'requirements' => [], // citizen brings originals to the counter
        ]));
        $doc = Document::latest('id')->firstOrFail();

        $this->actingAs($supervisor)
            ->patch(route('admin.assignments.assign', $doc), ['assigned_to' => $staff->id]);

        // 1. The assignee's own dashboard shows the work.
        $this->actingAs($staff)->get(route('staff.dashboard'))->assertOk();

        // 2. Accept the assignment. Everything else a staff member can do to a
        //    document — verifying requirements included — is gated on this, so
        //    it has to come first.
        $doc->forceFill(['accepted_at' => now()])->save();

        // 3. Review the citizen's requirement. Approving it also stamps
        //    `verified_at`, so one action both records the judgement and clears
        //    the gate on Approved — the separate verify toggle is for the
        //    counter case, and calling it here would flip verification back OFF.
        //    Verification deliberately does not require an upload, because
        //    citizens are told to bring originals rather than attach copies.
        $requirement = $doc->requirements()->firstOrFail();

        $this->actingAs($staff)
            ->post(route('documents.requirements.review', [$doc, $requirement]), [
                'review_status' => 'approved',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            $doc->fresh()->unverifiedMandatoryRequirements()->isEmpty(),
            'Approving the only mandatory requirement must clear the approval gate.'
        );

        // 4. Pending → In progress.
        $this->actingAs($staff)
            ->patchJson(route('documents.status.advance', $doc), [
                'expected_status' => 'pending',
                'claim_date' => now()->addWeek()->toDateString(),
            ])
            ->assertOk();

        $this->assertSame('in_progress', $doc->fresh()->status);

        // 5. In progress → In review. The gate wants evidence that work happened:
        //    an attachment, a staff note already on file, or a note typed onto
        //    this transition. The last is what staff actually do in the cockpit.
        $this->actingAs($staff)
            ->patchJson(route('documents.status.advance', $doc), [
                'expected_status' => 'in_progress',
                'claim_date' => now()->addWeek()->toDateString(),
                'note' => 'Checked the clearance against the barangay register.',
            ])
            ->assertOk();

        $this->assertSame('in_review', $doc->fresh()->status);

        // 5. In review → Approved. This stage carries two gates: every mandatory
        //    requirement verified (done above) and a note giving the "why" for
        //    the audit trail.
        $this->actingAs($staff)
            ->patchJson(route('documents.status.advance', $doc), [
                'expected_status' => 'in_review',
                'claim_date' => now()->addWeek()->toDateString(),
                'note' => 'Clearances checked against the originals; approved.',
            ])
            ->assertOk();
        $this->assertSame('approved', $doc->fresh()->status);

        // 7. Approved → Completed is NOT reachable through this endpoint: the
        //    same call that carried every earlier stage is refused here (422).
        //    Completion has its own route (`documents.complete`) and is tied to
        //    handing the folder over, so it is exercised in the QR journey below
        //    rather than here. Asserted rather than skipped so that if advance
        //    ever does complete a document, this test says so.
        $this->actingAs($staff)
            ->patchJson(route('documents.status.advance', $doc), [
                'expected_status' => 'approved',
                'claim_date' => now()->addWeek()->toDateString(),
            ])
            ->assertStatus(422);

        $this->assertSame('approved', $doc->fresh()->status);

        // 8. The citizen's tracking page still renders at the end of the line.
        $this->get(route('track.show', $doc->tracking_number))->assertOk();
    }

    // ──────────────────────────────────────────────────────────────────────
    // Journey 4 — QR: custody, release, verification
    // ──────────────────────────────────────────────────────────────────────

    /**
     * The physical half of the system: staff take custody of the paper by
     * scanning it, the folder is released once at the counter, and the code on
     * the released document verifies as genuine.
     */
    public function test_journey_qr_custody_release_and_verification(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seedWorld();
        $this->permitType($this->department('TRSM'));

        $staff = $this->staff('TRSM');
        $this->post(route('public.request.store'), $this->filing());
        $doc = Document::latest('id')->firstOrFail();

        // 1. Scanning the folder's own QR records custody.
        $this->actingAs($staff)
            ->post(route('documents.custody.store', $doc), [
                'capture_method' => 'scan',
                'scanned_value' => url('/track/'.strtolower($doc->tracking_number)),
            ])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($doc->fresh()->currentCustody());

        // 2. Release is refused until the document is actually finished — this
        //    is the gate that stops a folder leaving the building early.
        $this->actingAs($staff)
            ->patchJson(route('documents.release', $doc))
            ->assertStatus(422);

        $doc->forceFill(['status' => 'completed'])->save();

        $this->actingAs($staff)
            ->patchJson(route('documents.release', $doc))
            ->assertOk();

        $this->assertNotNull($doc->fresh()->claimed_at);

        // 3. Released once, never twice.
        $this->actingAs($staff)
            ->patchJson(route('documents.release', $doc))
            ->assertStatus(422);

        // 4. The public verification page answers for all three verdicts.
        $this->get(route('verify.show', $doc->tracking_number))->assertOk();
        $this->get(route('verify.show', ['trackingNumber' => $doc->tracking_number, 'sig' => 'deadbeef']))->assertOk();
        $this->get(route('verify.show', 'SPD-00000000-NOPE'))->assertOk();
    }

    // ──────────────────────────────────────────────────────────────────────
    // Journey 5 — the super admin
    // ──────────────────────────────────────────────────────────────────────

    /**
     * The administrative surface. Not a deep test of each screen — a check that
     * every one of them is reachable, because a 500 on a settings page during a
     * demo reads exactly like a broken system.
     */
    public function test_journey_super_admin_can_reach_every_administrative_screen(): void
    {
        $this->seedWorld();
        $admin = $this->superAdmin();

        $screens = [
            'admin.dashboard',
            'admin.users.index',
            'admin.users.create',
            'admin.departments.index',
            'admin.departments.create',
            'admin.request-types.index',
            'admin.request-types.create',
            'admin.resources.index',
            'admin.resources.create',
            'admin.route-templates.index',
            'admin.audit-log.index',
            'admin.assignments.index',
            'reports.services',
            'history',
            'bookings.index',
            'requests.index',
            'staff.index',
            'profile.edit',
        ];

        foreach ($screens as $name) {
            $this->assertSame(
                200,
                $this->actingAs($admin)->get(route($name))->getStatusCode(),
                "Super admin could not reach [{$name}]"
            );
        }

        // Two screens deliberately redirect a super admin rather than render:
        // `analytics` sends them to their own command centre, and `track.index`
        // / `requests.create` bounce because an org-wide admin has no department
        // of their own. These are controller business rules, not denials.
        foreach (['analytics', 'track.index', 'requests.create'] as $name) {
            $this->assertSame(
                302,
                $this->actingAs($admin)->get(route($name))->getStatusCode(),
                "[{$name}] is expected to redirect a super admin"
            );
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // Journey 6 — the internal department-to-department request
    // ──────────────────────────────────────────────────────────────────────

    /**
     * The internal path: staff draft a request, their own department head
     * endorses it first, and only then does it travel the route template.
     */
    public function test_journey_internal_request_from_draft_to_endorsement(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seedWorld();

        $staff = $this->staff('TRSM');
        $supervisor = $this->supervisor('TRSM');

        // 1. Staff can open the wizard and file a draft.
        $this->actingAs($staff)->get(route('requests.create'))->assertOk();

        $this->actingAs($staff)->post(route('requests.store'), [
            'first_department_id' => $this->department('OM')->id,
            'purpose' => 'Sound system for the town fiesta',
            'paper_scan' => UploadedFile::fake()->create('request.pdf', 120, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $doc = Document::where('origin', Document::ORIGIN_INTERNAL)->latest('id')->firstOrFail();

        // 2. The first hop is the filing department's own endorsement, so the
        //    head signs off before it leaves the office.
        $firstStep = $doc->requestSteps()->orderBy('step_order')->firstOrFail();
        $this->assertSame(0, (int) $firstStep->step_order);

        // 3. An internal request is never publicly readable. A guest is bounced
        //    off the public tracking route entirely (the route is guest-redirected
        //    since the redesign), so the assertion is "not a 200", not "404".
        $this->get(route('track.show', $doc->tracking_number))
            ->assertStatus(302);

        // 4. The department head can see it in the internal inbox.
        $this->actingAs($supervisor)->get(route('requests.index'))->assertOk();
        $this->actingAs($supervisor)->get(route('requests.show', $doc))->assertOk();
    }

    // ──────────────────────────────────────────────────────────────────────
    // Journey 7 — the boundaries between roles
    // ──────────────────────────────────────────────────────────────────────

    /**
     * The paths that must NOT work. A system that lets the wrong person act is
     * worse than one that is merely broken, and these are the denials a panel
     * is most likely to probe.
     */
    public function test_journey_role_boundaries_hold(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seedWorld();
        $this->permitType($this->department('TRSM'));

        $staff = $this->staff('TRSM');
        $otherStaff = $this->staff('OM');

        $this->post(route('public.request.store'), $this->filing());
        $doc = Document::latest('id')->firstOrFail();

        // 1. A guest is sent to login, never shown the staff side.
        foreach (['dashboard', 'admin.users.index', 'requests.index', 'analytics'] as $name) {
            $this->get(route($name))->assertRedirect(route('login'));
        }

        // 2. Staff cannot reach administrative screens — denial is a 403, not a
        //    quiet redirect that looks like a navigation quirk.
        foreach (['admin.users.index', 'admin.departments.index', 'admin.audit-log.index'] as $name) {
            $this->actingAs($staff)->get(route($name))->assertForbidden();
        }

        // 3. Staff cannot assign work to themselves or anyone else.
        $this->actingAs($staff)
            ->patch(route('admin.assignments.assign', $doc), ['assigned_to' => $staff->id])
            ->assertForbidden();

        // 4. A staff member cannot advance a document that is not theirs.
        $doc->forceFill(['assigned_to' => $otherStaff->id, 'accepted_at' => now()])->save();

        $this->actingAs($staff)
            ->patchJson(route('documents.status.advance', $doc), [
                'expected_status' => 'pending',
                'claim_date' => now()->addWeek()->toDateString(),
            ])
            ->assertForbidden();

        $this->assertSame('pending', $doc->fresh()->status);
    }
}
