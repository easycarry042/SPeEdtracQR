<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The Look Up desk: a table of the work in scope with a detail modal per row
 * (Figma: STAFF LOOK UP, frames 1293:1331 + 1294:1680). Replaces the two-pane
 * list + detail, which is still reachable at /track/{trackingNumber}.
 */
class LookUpDeskTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seedRolesAndPermissions();

        return User::factory()->create()->assignRole('staff');
    }

    private function makeDocument(array $attributes = []): Document
    {
        return Document::create(array_merge([
            'tracking_number' => 'SPD-DESK-'.strtoupper(uniqid()),
            'document_type' => 'Lei Making',
            'citizen_name' => 'Francisco Consorte',
            'citizen_email' => 'consorte@example.test',
            'citizen_contact' => '09123456789',
            'status' => 'pending',
        ], $attributes));
    }

    public function test_the_desk_lists_work_in_scope_with_its_request_type_and_requester(): void
    {
        $staff = $this->staff();
        $this->makeDocument(['assigned_to' => $staff->id]);

        $this->actingAs($staff)
            ->get(route('track.index', ['find' => 1]))
            ->assertOk()
            ->assertSee('Lei Making')
            ->assertSee('Francisco Consorte')
            ->assertSeeInOrder(['Request Type', 'Date', 'Requested by', 'Action']);
    }

    public function test_the_two_queues_are_pending_and_in_progress(): void
    {
        $staff = $this->staff();
        $this->makeDocument(['assigned_to' => $staff->id, 'status' => 'pending']);
        $this->makeDocument(['assigned_to' => $staff->id, 'status' => 'in_progress']);

        $response = $this->actingAs($staff)
            ->get(route('track.index', ['find' => 1]))
            ->assertOk();

        $response->assertSee('Pending');
        $response->assertSee('In Progress');

        // Both rows travel in the payload; the tabs split them client-side.
        $content = $response->getContent();

        $this->assertSame(1, substr_count($content, '\u0022status\u0022:\u0022pending\u0022'));
        $this->assertSame(1, substr_count($content, '\u0022status\u0022:\u0022in_progress\u0022'));
    }

    public function test_completed_work_is_not_on_the_desk(): void
    {
        // Finished work belongs in History, not in a queue of things to do.
        $staff = $this->staff();
        $this->makeDocument([
            'assigned_to' => $staff->id,
            'status' => 'completed',
            'document_type' => 'Borrowing of Flag',
        ]);

        $this->actingAs($staff)
            ->get(route('track.index', ['find' => 1]))
            ->assertOk()
            ->assertDontSee('Borrowing of Flag');
    }

    public function test_the_modal_carries_the_frames_own_blocks(): void
    {
        $staff = $this->staff();
        $this->makeDocument(['assigned_to' => $staff->id]);

        $this->actingAs($staff)
            ->get(route('track.index', ['find' => 1]))
            ->assertOk()
            ->assertSee('Mark as Completed')
            ->assertSee('Requested Claiming Date')
            ->assertSee('Adjust Date')
            ->assertSee('<span>View Attachments</span>', false)
            ->assertSee('Messages')
            ->assertSee('Tracking Number')
            ->assertSeeInOrder(['Logs', 'More Info'])
            // No link out to the request's own page from the modal.
            ->assertDontSee('Open the full record');
    }

    public function test_the_desk_only_shows_work_the_viewer_may_see(): void
    {
        $staff = $this->staff();
        $other = User::factory()->create()->assignRole('staff');

        $this->makeDocument([
            'assigned_to' => $other->id,
            'document_type' => 'Someone Elses Request',
        ]);

        $this->actingAs($staff)
            ->get(route('track.index', ['find' => 1]))
            ->assertOk()
            ->assertDontSee('Someone Elses Request');
    }

    public function test_internal_requests_stay_off_the_citizen_desk(): void
    {
        // Dept-to-dept work has its own desk; mixing it in would put it in front
        // of people who only handle citizen requests.
        $staff = $this->staff();
        $this->makeDocument([
            'assigned_to' => $staff->id,
            'origin' => Document::ORIGIN_INTERNAL,
            'document_type' => 'Internal Memo',
        ]);

        $this->actingAs($staff)
            ->get(route('track.index', ['find' => 1]))
            ->assertOk()
            ->assertDontSee('Internal Memo');
    }

    public function test_staff_can_move_the_claiming_date_without_moving_the_stage(): void
    {
        // "Adjust Date" records a date agreed with the citizen. Before this the
        // only way to set one was to advance the stage as well.
        $staff = $this->staff();
        $document = $this->makeDocument([
            'assigned_to' => $staff->id,
            'status' => 'in_progress',
        ]);

        $date = now()->addWeek()->toDateString();

        $this->actingAs($staff)
            ->patch(route('documents.claim-date', $document), ['claim_date' => $date])
            ->assertRedirect();

        $document->refresh();

        $this->assertSame($date, $document->claim_date?->toDateString());
        $this->assertSame('in_progress', $document->status);
    }

    public function test_the_claiming_date_cannot_be_put_in_the_past(): void
    {
        $staff = $this->staff();
        $document = $this->makeDocument(['assigned_to' => $staff->id]);

        $this->actingAs($staff)
            ->patch(route('documents.claim-date', $document), [
                'claim_date' => now()->subDay()->toDateString(),
            ])
            ->assertSessionHasErrors('claim_date');

        $this->assertNull($document->refresh()->claim_date);
    }

    public function test_the_sidebar_look_up_link_opens_the_desk_not_one_request(): void
    {
        // Regression: /track used to auto-open whichever request the viewer had
        // touched last, so the sidebar's "Look Up" dropped staff into a single
        // request's page and this screen was unreachable without typing ?find=1.
        $staff = $this->staff();
        $this->makeDocument(['assigned_to' => $staff->id, 'status' => 'in_progress']);

        $this->actingAs($staff)
            ->get(route('track.index'))
            ->assertOk()
            ->assertSeeInOrder(['Request Type', 'Date', 'Requested by', 'Action']);
    }

    public function test_the_qr_hub_is_still_reachable_for_when_the_paper_is_in_hand(): void
    {
        $staff = $this->staff();
        $this->makeDocument(['assigned_to' => $staff->id]);

        $this->actingAs($staff)
            ->get(route('track.index', ['scan' => 1]))
            ->assertOk()
            ->assertDontSee('Requested by');
    }

    public function test_the_header_carries_the_scan_disc_beside_the_bell(): void
    {
        // The frame puts three controls on the bar: scan, notifications, then
        // the identity chip. The scan disc was the one never wired up, which
        // left staff with no way to the QR hub once Look Up stopped redirecting.
        $staff = $this->staff();

        $this->actingAs($staff)
            ->get(route('track.index'))
            ->assertOk()
            ->assertSee('<span>Scan QR Code</span>', false)
            ->assertSee(route('track.index', ['scan' => 1]), false)
            ->assertSee('aria-label="Notifications', false)
            ->assertSee('class="header-disc"', false)
            ->assertSee('class="header-chip"', false);
    }

    public function test_the_detail_modal_draws_the_four_stage_progress_bar(): void
    {
        $staff = $this->staff();
        $this->makeDocument(['assigned_to' => $staff->id, 'status' => 'approved']);

        $response = $this->actingAs($staff)->get(route('track.index'))->assertOk();

        // The prototype's four stages, not the five statuses.
        $response->assertViewHas('rail', ['Pending', 'In review', 'Processing', 'Completed']);
        $response->assertSee('<ol class="dm-steps" aria-label="Progress">', false);

        // Approved sits on "Processing", the third stage.
        $this->assertSame(3, $response->viewData('activeRows')[0]['rail_stage']);
    }

    public function test_the_date_picker_marks_the_requested_and_current_claiming_dates(): void
    {
        $staff = $this->staff();
        $this->makeDocument([
            'assigned_to' => $staff->id,
            'status' => 'in_progress',
            'needed_by' => '2026-11-05',
            'claim_date' => '2026-11-07',
        ]);

        $response = $this->actingAs($staff)->get(route('track.index'))->assertOk();

        // Machine-readable days, so the picker can mark them on the grid.
        $row = $response->viewData('activeRows')[0];
        $this->assertSame('2026-11-05', $row['requested_claim_on']);
        $this->assertSame('2026-11-07', $row['claim_on']);

        $response->assertSee("'is-requested': cell.iso === selected?.requested_claim_on", false);
        $response->assertSee("'is-set': cell.iso === selected?.claim_on", false);
        $response->assertSee('<div class="dp-grid" role="grid" aria-label="Claiming date">', false);
    }

    public function test_the_tracking_number_tile_opens_the_requests_qr_code(): void
    {
        $staff = $this->staff();
        $document = $this->makeDocument(['assigned_to' => $staff->id, 'status' => 'in_progress']);
        $document->update(['qr_code_path' => 'qrcodes/'.$document->tracking_number.'.png']);

        $response = $this->actingAs($staff)->get(route('track.index'))->assertOk();

        // The row carries the generated QR and the print-ready claim slip.
        $row = $response->viewData('activeRows')[0];
        $this->assertSame('/storage/qrcodes/'.$document->tracking_number.'.png', $row['qr_url']);
        $this->assertSame(route('track.slip', $document->tracking_number), $row['slip_url']);

        // The tile is a button that opens the viewer, and the footer has no Cancel.
        $response->assertSee('@click="qrOpen = true" class="dm-qr-btn"', false)
            ->assertSee('class="dm-qr-layer"', false)
            ->assertSee('Print slip')
            ->assertDontSee('<button type="button" @click="close()" class="cr-btn">Cancel</button>', false);
    }

    public function test_file_request_and_scan_are_labelled_header_buttons_on_every_page(): void
    {
        $this->seedRolesAndPermissions();
        // Filing needs an office to file from, so the button only shows with one.
        $department = Department::factory()->create();
        $supervisor = User::factory()->create(['department_id' => $department->id])->assignRole('Supervisor');

        foreach (['dashboard', 'track.index', 'requests.index', 'staff.index'] as $route) {
            $this->actingAs($supervisor)
                ->get(route($route))
                ->assertOk()
                ->assertSee('<span>File Request</span>', false)
                ->assertSee('<span>Scan QR Code</span>', false)
                // The filing form rides along, ready to open in place.
                ->assertSee('aria-label="File internal request"', false);
        }
    }

    public function test_file_request_is_hidden_without_an_office_to_file_from(): void
    {
        $this->actingAs($this->staff())
            ->get(route('track.index'))
            ->assertOk()
            ->assertDontSee('<span>File Request</span>', false)
            ->assertSee('<span>Scan QR Code</span>', false);
    }

    public function test_the_scan_disc_opens_a_camera_modal_instead_of_leaving_the_page(): void
    {
        // The disc used to navigate to the upload-only hub. It now opens the
        // live camera in a modal over the current page; the href stays as the
        // no-JavaScript fallback.
        $this->actingAs($this->staff())
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('x-data="headerQrScanner"', false)
            ->assertSee('@click.prevent="open()"', false)
            ->assertSee('role="dialog" aria-modal="true" aria-labelledby="headerScanTitle"', false)
            ->assertSee('id="headerScanReader"', false)
            ->assertSee("window.SpeedQr.start('headerScanReader'", false)
            // The shared scanner rules (accepted codes, camera lifecycle) are on the page.
            ->assertSee('window.SpeedQr = (function ()', false);
    }

    public function test_the_modal_carries_both_side_panels(): void
    {
        // Frames 1321:1370 and 1321:1750 slide a second card in beside the
        // detail card: the claiming-date calendar and the conversation.
        $staff = $this->staff();
        $this->makeDocument(['assigned_to' => $staff->id]);

        $this->actingAs($staff)
            ->get(route('track.index', ['find' => 1]))
            ->assertOk()
            ->assertSee('Adjust Claiming Date')
            ->assertSee('Apply')
            ->assertSee('You are now chatting with the client')
            ->assertSee('Your conversation is secured')
            ->assertSee('Write your message...');
    }

    public function test_the_conversation_travels_with_the_row_without_internal_notes(): void
    {
        $staff = $this->staff();
        $document = $this->makeDocument(['assigned_to' => $staff->id]);

        $document->allComments()->create([
            'author_type' => 'citizen',
            'author_name' => 'Francisco Consorte',
            'body' => 'Good morning po, any update?',
            'visibility' => 'public',
        ]);

        // Staff-only notes belong to the other thread and must never surface in
        // a panel that is about the citizen conversation.
        $document->allComments()->create([
            'author_id' => $staff->id,
            'author_type' => 'staff',
            'author_name' => $staff->name,
            'body' => 'Colleagues only: chase the endorsement.',
            'visibility' => 'internal',
        ]);

        $this->actingAs($staff)
            ->get(route('track.index', ['find' => 1]))
            ->assertOk()
            ->assertSee('Good morning po, any update?')
            ->assertDontSee('Colleagues only');
    }

    public function test_the_panel_posts_a_message_and_gets_it_back_as_json(): void
    {
        // The panel appends the reply in place, so the post must answer with the
        // message rather than redirect — a redirect would reload the desk and
        // close the modal the staff member is still working in.
        $staff = $this->staff();
        $document = $this->makeDocument(['assigned_to' => $staff->id]);

        $this->actingAs($staff)
            ->postJson(route('documents.comments.store', $document), [
                'body' => 'Your permit is ready for pick-up.',
                'visibility' => 'public',
            ])
            ->assertCreated()
            ->assertJsonPath('comment.from', 'staff')
            ->assertJsonPath('comment.body', 'Your permit is ready for pick-up.')
            ->assertJsonPath('comment.read', false);
    }

    public function test_a_file_can_be_attached_to_a_message_from_the_panel(): void
    {
        // The composer's "+" used to link out to the request's own page; it now
        // stages a file and posts it with the message it belongs to.
        Storage::fake('local');

        $staff = $this->staff();
        $document = $this->makeDocument(['assigned_to' => $staff->id]);

        $response = $this->actingAs($staff)
            ->post(route('documents.comments.store', $document), [
                'body' => 'Here is the approved permit.',
                'visibility' => 'public',
                'attachment' => UploadedFile::fake()->create('permit.pdf', 120, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $response->assertJsonPath('comment.attachment.name', 'permit.pdf');

        $comment = $document->allComments()->latest('id')->first();

        $this->assertNotNull($comment->attachment_path);
        Storage::disk('local')->assertExists($comment->attachment_path);
    }

    public function test_an_attachment_the_rules_reject_comes_back_as_a_field_error(): void
    {
        $staff = $this->staff();
        $document = $this->makeDocument(['assigned_to' => $staff->id]);

        $this->actingAs($staff)
            ->post(route('documents.comments.store', $document), [
                'body' => 'Trying an executable.',
                'visibility' => 'public',
                'attachment' => UploadedFile::fake()->create('payload.exe', 10),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('attachment');
    }

    public function test_opening_the_conversation_clears_the_unread_badge(): void
    {
        $staff = $this->staff();
        $document = $this->makeDocument(['assigned_to' => $staff->id]);

        $document->allComments()->create([
            'author_type' => 'citizen',
            'author_name' => 'Francisco Consorte',
            'body' => 'Following up po.',
            'visibility' => 'public',
        ]);

        $this->assertSame(1, $document->allComments()->unreadByStaff()->count());

        $this->actingAs($staff)
            ->postJson(route('documents.comments.read', $document))
            ->assertOk();

        $this->assertSame(0, $document->allComments()->unreadByStaff()->count());
    }

    public function test_a_staff_member_who_does_not_own_the_request_cannot_post_on_it(): void
    {
        $staff = $this->staff();
        $other = User::factory()->create()->assignRole('staff');
        $document = $this->makeDocument(['assigned_to' => $other->id]);

        $this->actingAs($staff)
            ->postJson(route('documents.comments.store', $document), [
                'body' => 'Not my ticket.',
                'visibility' => 'public',
            ])
            ->assertForbidden();
    }
}
