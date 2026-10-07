<?php

namespace Tests\Feature;

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
            ->assertSee('aria-label="View Attachments"', false)
            ->assertSee('Messages')
            ->assertSee('Tracking Number')
            ->assertSeeInOrder(['Logs', 'More Info']);
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
            ->assertSee('aria-label="Scan a QR code"', false)
            ->assertSee(route('track.index', ['scan' => 1]), false)
            ->assertSee('images/staff/icon-scan.svg', false)
            ->assertSee('images/staff/icon-bell.svg', false);
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
