<?php

namespace Tests\Feature;

use App\Mail\CitizenMessage;
use App\Mail\StaffMessage;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The request conversation, both ways:
 *   citizen ↔ staff on the public thread, staff ↔ staff on the internal one.
 *
 * The rule that matters most: an internal note must never be readable, listable,
 * or replyable from anything a citizen can reach.
 */
class TwoWayMessagingTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        $this->seedRolesAndPermissions();

        $this->staff = User::factory()->create(['is_active' => true, 'name' => 'Jose Reyes'])->assignRole('staff');
        $this->document = Document::create([
            'tracking_number' => 'SPD-20260730-MSG001',
            'document_type' => 'Business Permit',
            'citizen_name' => 'Maria Santos',
            'citizen_email' => 'maria@example.com',
            'citizen_contact' => '0917 123 4567',
            'status' => 'in_progress',
            'assigned_to' => $this->staff->id,
            'assigned_at' => now(),
            'accepted_at' => now(),
            'notify_citizen' => true,
        ]);
    }

    private function verifyCitizen(string $detail = 'maria@example.com'): void
    {
        $this->post(route('track.messages.verify', $this->document->tracking_number), ['detail' => $detail])
            ->assertRedirect();
    }

    /**
     * The conversation as Speedy's "Office messages" tab fetches it, for a
     * confirmed citizen.
     *
     * @return list<array<string, mixed>>
     */
    private function thread(): array
    {
        $this->verifyCitizen();

        return $this->getJson(route('track.messages.index', $this->document->tracking_number))
            ->assertOk()
            ->json('messages');
    }

    // ── Verification gate ────────────────────────────────────────────────────

    public function test_the_conversation_is_locked_until_a_contact_detail_is_confirmed(): void
    {
        // Reading needs the check too, not only writing: the tracking number
        // is printed on slips and stickers, and replies can carry personal
        // details.
        $this->getJson(route('track.messages.index', $this->document->tracking_number))
            ->assertForbidden()
            ->assertJsonPath('verified', false)
            ->assertJsonPath('can_verify', true);

        $this->verifyCitizen();

        $this->getJson(route('track.messages.index', $this->document->tracking_number))
            ->assertOk()
            ->assertJsonPath('verified', true);
    }

    public function test_the_mobile_number_matches_however_it_is_formatted(): void
    {
        foreach (['0917 123 4567', '09171234567', '+63 917 123 4567', '0917-123-4567'] as $format) {
            $this->flushSession();
            $this->post(route('track.messages.verify', $this->document->tracking_number), ['detail' => $format])
                ->assertSessionHasNoErrors();
        }
    }

    public function test_a_wrong_detail_is_refused_without_revealing_what_is_on_file(): void
    {
        $response = $this->post(route('track.messages.verify', $this->document->tracking_number), [
            'detail' => 'someone.else@example.com',
        ])->assertSessionHasErrors('detail');

        $error = session('errors')->first('detail');
        $this->assertStringNotContainsString('maria@example.com', $error);
        $this->assertStringNotContainsString('0917', $error);
    }

    public function test_posting_without_verifying_is_forbidden(): void
    {
        $this->post(route('track.messages.store', $this->document->tracking_number), ['body' => 'Hello?'])
            ->assertForbidden();

        $this->assertSame(0, $this->document->comments()->count());
    }

    // ── Citizen → staff ─────────────────────────────────────────────────────

    public function test_a_verified_citizen_can_post_and_staff_are_notified(): void
    {
        $this->verifyCitizen();

        $this->post(route('track.messages.store', $this->document->tracking_number), [
            'body' => 'Good day, what else do I need to submit?',
        ])->assertRedirect();

        $message = $this->document->comments()->latest('id')->first();
        $this->assertSame(DocumentComment::AUTHOR_CITIZEN, $message->author_type);
        $this->assertSame(DocumentComment::VISIBILITY_PUBLIC, $message->visibility);
        $this->assertNull($message->author_id);
        $this->assertNull($message->staff_read_at, 'A new citizen message must start unread for staff.');

        // Bell for the assignee, and an email so they see it away from the desk.
        $this->assertNotNull(
            $this->staff->fresh()->unreadNotifications->firstWhere('data.event', 'citizen_message')
        );
        Mail::assertQueued(CitizenMessage::class);
    }

    public function test_the_citizen_can_reply_under_a_staff_message(): void
    {
        $staffMessage = $this->document->comments()->create([
            'author_id' => $this->staff->id,
            'author_type' => DocumentComment::AUTHOR_STAFF,
            'body' => 'Please bring your barangay clearance.',
            'visibility' => DocumentComment::VISIBILITY_PUBLIC,
        ]);

        $this->verifyCitizen();

        $this->post(route('track.messages.store', $this->document->tracking_number), [
            'body' => 'Noted, I will bring it tomorrow.',
            'parent_id' => $staffMessage->id,
        ])->assertSessionHasNoErrors()->assertRedirect();

        // Queried unscoped: Document::comments() is top-level-only, so a reply is
        // deliberately not in it.
        $reply = $this->document->allComments()->latest('id')->first();
        $this->assertSame($staffMessage->id, $reply->parent_id);
        $this->assertTrue($staffMessage->fresh()->replies->contains($reply));
    }

    public function test_a_citizen_cannot_reply_to_an_internal_note(): void
    {
        $internal = $this->document->comments()->create([
            'author_id' => $this->staff->id,
            'author_type' => DocumentComment::AUTHOR_STAFF,
            'body' => 'Applicant has an unpaid balance — check with the treasury.',
            'visibility' => DocumentComment::VISIBILITY_INTERNAL,
        ]);

        $this->verifyCitizen();

        $this->post(route('track.messages.store', $this->document->tracking_number), [
            'body' => 'What balance?',
            'parent_id' => $internal->id,
        ])->assertSessionHasErrors('parent_id');

        $this->assertSame(0, $internal->fresh()->replies->count());
    }

    // ── Internal notes stay internal ─────────────────────────────────────────

    public function test_internal_notes_never_reach_the_citizen(): void
    {
        $this->document->comments()->create([
            'author_id' => $this->staff->id,
            'author_type' => DocumentComment::AUTHOR_STAFF,
            'body' => 'SECRET-INTERNAL-NOTE: applicant is a repeat offender',
            'visibility' => DocumentComment::VISIBILITY_INTERNAL,
        ]);
        $this->document->comments()->create([
            'author_id' => $this->staff->id,
            'author_type' => DocumentComment::AUTHOR_STAFF,
            'body' => 'Your permit is being processed.',
            'visibility' => DocumentComment::VISIBILITY_PUBLIC,
        ]);

        $bodies = array_column($this->thread(), 'body');

        $this->assertContains('Your permit is being processed.', $bodies);
        $this->assertNotContains('SECRET-INTERNAL-NOTE: applicant is a repeat offender', $bodies);

        $this->get(route('track.show', $this->document->tracking_number))
            ->assertOk()
            ->assertDontSee('SECRET-INTERNAL-NOTE');
    }

    public function test_an_internal_notes_attachment_is_not_downloadable_by_a_citizen(): void
    {
        Storage::disk('local')->put('message-attachments/secret.pdf', '%PDF-1.4 internal');
        $internal = $this->document->comments()->create([
            'author_id' => $this->staff->id,
            'author_type' => DocumentComment::AUTHOR_STAFF,
            'body' => 'Internal memo attached.',
            'visibility' => DocumentComment::VISIBILITY_INTERNAL,
            'attachment_path' => 'message-attachments/secret.pdf',
            'attachment_name' => 'secret.pdf',
        ]);

        $this->verifyCitizen();

        // Even a verified citizen cannot reach an internal thread's file.
        $this->get(route('track.messages.attachment', $internal))->assertNotFound();
    }

    // ── Staff ↔ staff internal thread ───────────────────────────────────────

    public function test_an_internal_question_notifies_the_assignee(): void
    {
        $supervisor = User::factory()->create(['is_active' => true, 'name' => 'Ana Cruz'])->assignRole('Supervisor');

        $this->actingAs($supervisor)
            ->post(route('documents.comments.store', $this->document), [
                'body' => 'Has the treasury confirmed payment on this one?',
                'visibility' => 'internal',
            ])->assertRedirect();

        $notification = $this->staff->fresh()->unreadNotifications->firstWhere('data.event', 'internal_question');
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Ana Cruz', data_get($notification->data, 'title'));
    }

    public function test_an_internal_answer_notifies_whoever_asked(): void
    {
        $supervisor = User::factory()->create(['is_active' => true, 'name' => 'Ana Cruz'])->assignRole('Supervisor');

        $question = $this->document->comments()->create([
            'author_id' => $supervisor->id,
            'author_type' => DocumentComment::AUTHOR_STAFF,
            'body' => 'Any update here?',
            'visibility' => DocumentComment::VISIBILITY_INTERNAL,
        ]);

        $this->actingAs($this->staff)
            ->post(route('documents.comments.store', $this->document), [
                'body' => 'Waiting on the treasury, following up today.',
                'visibility' => 'internal',
                'parent_id' => $question->id,
            ])->assertRedirect();

        $this->assertNotNull(
            $supervisor->fresh()->unreadNotifications->firstWhere('data.event', 'internal_answer')
        );
        // The answerer does not get pinged about their own answer.
        $this->assertNull($this->staff->fresh()->unreadNotifications->firstWhere('data.event', 'internal_answer'));
    }

    public function test_a_staff_reply_in_the_citizen_thread_emails_the_citizen(): void
    {
        $this->actingAs($this->staff)
            ->post(route('documents.comments.store', $this->document), [
                'body' => 'Your permit is ready for pickup.',
                'visibility' => 'public',
            ])->assertRedirect();

        Mail::assertQueued(StaffMessage::class);
    }

    // ── Attachments ─────────────────────────────────────────────────────────

    public function test_both_sides_can_attach_a_file_to_a_message(): void
    {
        $this->verifyCitizen();

        $this->post(route('track.messages.store', $this->document->tracking_number), [
            'body' => 'Here is the scanned clearance.',
            'attachment' => UploadedFile::fake()->create('clearance.pdf', 64),
        ])->assertRedirect();

        $citizenMessage = $this->document->comments()->latest('id')->first();
        $this->assertTrue($citizenMessage->hasAttachment());
        $this->assertSame('clearance.pdf', $citizenMessage->attachment_name);
        Storage::disk('local')->assertExists($citizenMessage->attachment_path);

        $this->actingAs($this->staff)
            ->post(route('documents.comments.store', $this->document), [
                'body' => 'Here is the assessment form.',
                'visibility' => 'public',
                'attachment' => UploadedFile::fake()->create('assessment.docx', 32),
            ])->assertRedirect();

        $staffMessage = $this->document->comments()->latest('id')->first();
        $this->assertSame('assessment.docx', $staffMessage->attachment_name);
    }

    public function test_a_verified_citizen_can_download_a_thread_attachment_and_a_stranger_cannot(): void
    {
        Storage::disk('local')->put('message-attachments/notice.pdf', '%PDF-1.4 notice');
        $message = $this->document->comments()->create([
            'author_id' => $this->staff->id,
            'author_type' => DocumentComment::AUTHOR_STAFF,
            'body' => 'Assessment notice attached.',
            'visibility' => DocumentComment::VISIBILITY_PUBLIC,
            'attachment_path' => 'message-attachments/notice.pdf',
            'attachment_name' => 'notice.pdf',
        ]);

        // Not verified yet: the file is not served on the tracking number alone.
        $this->get(route('track.messages.attachment', $message))->assertForbidden();

        $this->verifyCitizen();
        $this->get(route('track.messages.attachment', $message))->assertOk();
    }

    // ── Unread state ────────────────────────────────────────────────────────

    public function test_opening_the_request_clears_the_unread_badge(): void
    {
        $this->verifyCitizen();
        $this->post(route('track.messages.store', $this->document->tracking_number), ['body' => 'Any update?'])
            ->assertRedirect();

        $this->assertSame(1, $this->document->comments()->unreadByStaff()->count());

        $this->actingAs($this->staff)->get(route('track.show', $this->document->tracking_number))->assertOk();

        $this->assertSame(0, $this->document->comments()->unreadByStaff()->count());
    }

    public function test_the_staff_queue_shows_an_unread_count(): void
    {
        $this->verifyCitizen();
        $this->post(route('track.messages.store', $this->document->tracking_number), ['body' => 'Any update?'])
            ->assertRedirect();

        // Asserted on the view data, not the HTML: the queue payload is @js'd into
        // an x-data attribute, where its quotes come out "-escaped.
        $response = $this->actingAs($this->staff)->get(route('staff.dashboard'))->assertOk();

        $row = collect($response->viewData('requestPayload'))
            ->firstWhere('tracking_number', $this->document->tracking_number);

        $this->assertSame(1, $row['unread_messages']);
        // …and the badge that reads it is actually bound in the queue row.
        $response->assertSee('unread_messages', false);
    }

    public function test_a_citizen_reply_counts_as_unread_and_is_cleared_on_open(): void
    {
        $staffMessage = $this->document->comments()->create([
            'author_id' => $this->staff->id,
            'author_type' => DocumentComment::AUTHOR_STAFF,
            'body' => 'Please confirm your address.',
            'visibility' => DocumentComment::VISIBILITY_PUBLIC,
        ]);

        $this->verifyCitizen();
        $this->post(route('track.messages.store', $this->document->tracking_number), [
            'body' => 'Confirmed, it is unchanged.',
            'parent_id' => $staffMessage->id,
        ])->assertSessionHasNoErrors();

        // A reply is a message the office still owes an answer to, so it must
        // raise the badge — and opening the request must clear it.
        $this->assertSame(1, $this->document->allComments()->unreadByStaff()->count());

        $this->actingAs($this->staff)->get(route('track.show', $this->document->tracking_number))->assertOk();

        $this->assertSame(0, $this->document->allComments()->unreadByStaff()->count());
    }

    public function test_replies_are_capped_at_one_level_of_nesting(): void
    {
        $question = $this->document->comments()->create([
            'author_id' => $this->staff->id,
            'author_type' => DocumentComment::AUTHOR_STAFF,
            'body' => 'Any update?',
            'visibility' => DocumentComment::VISIBILITY_INTERNAL,
        ]);
        $answer = $this->document->allComments()->create([
            'author_id' => $this->staff->id,
            'author_type' => DocumentComment::AUTHOR_STAFF,
            'body' => 'Following up today.',
            'visibility' => DocumentComment::VISIBILITY_INTERNAL,
            'parent_id' => $question->id,
        ]);

        // Nesting under a reply would save a message no view ever renders.
        $this->actingAs($this->staff)
            ->post(route('documents.comments.store', $this->document), [
                'body' => 'Thanks.',
                'visibility' => 'internal',
                'parent_id' => $answer->id,
            ])->assertSessionHasErrors('parent_id');

        $this->assertSame(0, $answer->fresh()->replies->count());
    }

    public function test_the_citizen_thread_reads_oldest_first(): void
    {
        foreach (['First message', 'Second message', 'Third message'] as $i => $body) {
            $this->document->comments()->create([
                'author_id' => $this->staff->id,
                'author_type' => DocumentComment::AUTHOR_STAFF,
                'body' => $body,
                'visibility' => DocumentComment::VISIBILITY_PUBLIC,
                'created_at' => now()->addMinutes($i),
            ]);
        }

        $this->assertSame(['First message', 'Second message', 'Third message'], array_column($this->thread(), 'body'));
    }

    // ── Who the citizen sees as the sender ──────────────────────────────────
    //
    // Staff stay anonymous to the public (legal advice): a reply is signed by
    // the office, never the person, and still marked as staff rather than the
    // automated assistant.

    public function test_the_citizen_sees_which_office_answered_never_who(): void
    {
        $office = Department::create(['name' => 'Business Permits Office', 'code' => 'BPLO', 'is_active' => true]);
        $supervisor = User::factory()->create(['is_active' => true, 'name' => 'Ana Cruz', 'department_id' => $office->id])->assignRole('Supervisor');

        $this->actingAs($supervisor)
            ->post(route('documents.comments.store', $this->document), [
                'body' => 'Your permit is ready for pickup.',
                'visibility' => 'public',
            ])->assertRedirect();

        auth()->logout();
        $this->flushSession();

        $reply = collect($this->thread())->firstWhere('body', 'Your permit is ready for pickup.');

        $this->assertSame('staff', $reply['from']);
        $this->assertSame('Business Permits Office', $reply['name']);
        $this->assertStringNotContainsString('Ana Cruz', json_encode($this->thread()));
    }

    public function test_a_reply_from_someone_without_an_office_is_signed_by_the_requests_office(): void
    {
        // A super admin belongs to no department.
        $office = Department::create(['name' => 'General Services Office', 'code' => 'GSO', 'is_active' => true]);
        $this->document->update(['department_id' => $office->id]);
        $admin = User::factory()->create(['is_active' => true, 'name' => 'Root Admin', 'department_id' => null]);

        $this->document->comments()->create([
            'author_id' => $admin->id,
            'author_type' => DocumentComment::AUTHOR_STAFF,
            'author_name' => $admin->name,
            'body' => 'We are on it.',
            'visibility' => DocumentComment::VISIBILITY_PUBLIC,
        ]);

        $this->assertSame('General Services Office', collect($this->thread())->firstWhere('body', 'We are on it.')['name']);
    }

    public function test_no_staff_name_reaches_the_citizen_thread(): void
    {
        $hidden = User::factory()->create(['is_active' => true, 'name' => 'Ramon Dela Cruz'])->assignRole('staff');
        $answering = User::factory()->create(['is_active' => true, 'name' => 'Ana Cruz'])->assignRole('staff');

        $this->document->comments()->create([
            'author_id' => $hidden->id,
            'author_type' => DocumentComment::AUTHOR_STAFF,
            'author_name' => $hidden->name,
            'body' => 'Check the treasury balance before releasing.',
            'visibility' => DocumentComment::VISIBILITY_INTERNAL,
        ]);
        $this->document->comments()->create([
            'author_id' => $answering->id,
            'author_type' => DocumentComment::AUTHOR_STAFF,
            'author_name' => $answering->name,
            'body' => 'Your permit is being processed.',
            'visibility' => DocumentComment::VISIBILITY_PUBLIC,
        ]);

        $thread = json_encode($this->thread());

        $this->assertStringNotContainsString('Ana Cruz', $thread);
        $this->assertStringNotContainsString('Ramon Dela Cruz', $thread);
    }

    public function test_the_citizen_sees_their_own_message_as_you_and_a_system_entry_as_update(): void
    {
        $this->verifyCitizen();
        $this->post(route('track.messages.store', $this->document->tracking_number), ['body' => 'Any update?'])
            ->assertRedirect();

        $this->document->comments()->create([
            'author_id' => null,
            'author_type' => DocumentComment::AUTHOR_SYSTEM,
            'body' => 'Status advanced to In Review.',
            'visibility' => DocumentComment::VISIBILITY_PUBLIC,
        ]);

        $byBody = collect($this->thread())->keyBy('body');

        // Their own words read as "You", not their name echoed back at them.
        $this->assertSame('You', $byBody['Any update?']['name']);
        $this->assertSame('citizen', $byBody['Any update?']['from']);
        $this->assertSame('Update', $byBody['Status advanced to In Review.']['name']);
        $this->assertSame('system', $byBody['Status advanced to In Review.']['from']);
    }

    public function test_a_message_whose_author_record_is_gone_still_has_a_byline(): void
    {
        $this->document->comments()->create([
            'author_id' => null,
            'author_type' => DocumentComment::AUTHOR_STAFF,
            'body' => 'Posted before author names were recorded.',
            'visibility' => DocumentComment::VISIBILITY_PUBLIC,
        ]);

        $orphan = collect($this->thread())->firstWhere('body', 'Posted before author names were recorded.');

        $this->assertSame('City Hall', $orphan['name']);
    }

    public function test_the_citizen_email_does_not_name_the_staff_member_who_replied(): void
    {
        $this->actingAs($this->staff)
            ->post(route('documents.comments.store', $this->document), [
                'body' => 'Your permit is ready for pickup.',
                'visibility' => 'public',
            ])->assertRedirect();

        Mail::assertQueued(StaffMessage::class, function (StaffMessage $mail): bool {
            return ! str_contains($mail->render(), 'Jose Reyes');
        });
    }

    public function test_a_message_on_an_internal_request_is_not_publicly_postable(): void
    {
        $internalRequest = Document::create([
            'tracking_number' => 'INT-20260730-AAA111',
            'document_type' => 'Procurement Request',
            'status' => 'pending',
            'origin' => Document::ORIGIN_INTERNAL,
        ]);

        $this->post(route('track.messages.verify', $internalRequest->tracking_number), ['detail' => 'x'])
            ->assertNotFound();
        $this->post(route('track.messages.store', $internalRequest->tracking_number), ['body' => 'hi'])
            ->assertNotFound();
    }
}
