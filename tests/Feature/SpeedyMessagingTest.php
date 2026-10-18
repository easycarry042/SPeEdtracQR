<?php

namespace Tests\Feature;

use App\Events\CitizenThreadChanged;
use App\Events\DocumentCommentPosted;
use App\Mail\StaffMessage;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Talking to the office through Speedy's "Office messages" tab.
 *
 * The conversation used to sit at the foot of the tracking page: readable by
 * anyone holding the tracking number, broadcast live on a public channel with
 * its full text, and silent — nothing told the citizen a reply was waiting
 * unless they went back and scrolled to it. It now lives in Speedy on every
 * page, behind the contact check for reading as well as writing, with an
 * unread badge on the launcher.
 */
class SpeedyMessagingTest extends TestCase
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
            'tracking_number' => 'SPD-20261018-TLK234',
            'document_type' => 'Business Permit',
            'citizen_name' => 'Maria Santos',
            'citizen_email' => 'maria@example.com',
            'citizen_contact' => '09171234567',
            'status' => 'in_progress',
            'assigned_to' => $this->staff->id,
            'notify_citizen' => true,
        ]);
    }

    private function staffReply(string $body): DocumentComment
    {
        return $this->document->comments()->create([
            'author_id' => $this->staff->id,
            'author_type' => DocumentComment::AUTHOR_STAFF,
            'author_name' => $this->staff->name,
            'body' => $body,
            'visibility' => DocumentComment::VISIBILITY_PUBLIC,
        ]);
    }

    private function confirm(): void
    {
        $this->postJson(route('track.messages.verify', $this->document->tracking_number), ['detail' => 'maria@example.com'])
            ->assertOk();
    }

    // ── the tracking page points at Speedy ───────────────────────────────

    public function test_the_tracking_page_no_longer_carries_the_conversation(): void
    {
        $this->staffReply('Please bring your original DTI certificate.');
        $this->confirm();

        // Even confirmed: one conversation, in Speedy, not a second copy here.
        $this->get($this->document->citizenTrackingUrl())
            ->assertOk()
            ->assertSee('Messages with the office')
            ->assertSee('speedy:open-messages', false)
            ->assertDontSee('Please bring your original DTI certificate.');
    }

    public function test_the_tracking_page_says_when_replies_are_waiting(): void
    {
        $this->staffReply('First reply.');
        $this->staffReply('Second reply.');
        $this->confirm();
        $this->staffReply('Third reply.');

        $this->get($this->document->citizenTrackingUrl())
            ->assertOk()
            ->assertSee('1 new reply from the office.')
            ->assertSee('Read the replies');
    }

    public function test_opening_the_page_does_not_mark_replies_read_only_opening_the_thread_does(): void
    {
        $reply = $this->staffReply('Your permit is ready.');

        // Anyone holding the number used to mark the citizen's replies read
        // just by loading the page.
        $this->get($this->document->citizenTrackingUrl())->assertOk();
        $this->assertNull($reply->fresh()->citizen_read_at);

        $this->confirm();
        $this->assertNotNull($reply->fresh()->citizen_read_at);
    }

    // ── confirming, reading, writing — the JSON Speedy uses ──────────────

    public function test_confirming_returns_the_conversation(): void
    {
        $this->staffReply('We received your request.');

        $this->postJson(route('track.messages.verify', $this->document->tracking_number), ['detail' => '0917 123 4567'])
            ->assertOk()
            ->assertJsonPath('verified', true)
            ->assertJsonPath('messages.0.body', 'We received your request.')
            ->assertJsonPath('messages.0.from', 'staff')
            ->assertJsonPath('messages.0.name', 'City Hall')
            ->assertDontSee('Jose Reyes');
    }

    public function test_a_wrong_detail_is_refused_without_saying_what_is_on_file(): void
    {
        $this->postJson(route('track.messages.verify', $this->document->tracking_number), ['detail' => 'someone@else.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('detail')
            ->assertDontSee('maria@example.com')
            ->assertDontSee('09171234567');
    }

    public function test_writing_still_needs_the_check(): void
    {
        $this->postJson(route('track.messages.store', $this->document->tracking_number), ['body' => 'Hello?'])
            ->assertForbidden();
    }

    public function test_a_message_sent_from_speedy_comes_back_in_the_thread(): void
    {
        $this->confirm();

        $this->post(route('track.messages.store', $this->document->tracking_number), [
            'body' => 'Here is my clearance.',
            'attachment' => UploadedFile::fake()->create('clearance.pdf', 40, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'Here is my clearance.')
            ->assertJsonPath('messages.0.from', 'citizen')
            ->assertJsonPath('messages.0.name', 'You')
            ->assertJsonPath('messages.0.attachment.name', 'clearance.pdf');
    }

    // ── the unread badge ─────────────────────────────────────────────────

    public function test_the_inbox_is_empty_for_a_browser_that_confirmed_nothing(): void
    {
        $this->staffReply('A reply nobody here may read.');

        $this->getJson(route('speedy.inbox'))
            ->assertOk()
            ->assertExactJson(['total' => 0, 'threads' => []]);
    }

    public function test_the_inbox_counts_new_replies_on_every_confirmed_request(): void
    {
        $this->confirm();
        $this->staffReply('One.');
        $this->staffReply('Two.');

        $this->getJson(route('speedy.inbox'))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('threads.0.tracking', 'SPD-20261018-TLK234')
            ->assertJsonPath('threads.0.unread', 2);

        // Reading the thread clears them.
        $this->getJson(route('track.messages.index', $this->document->tracking_number))->assertOk();
        $this->getJson(route('speedy.inbox'))->assertJsonPath('total', 0);
    }

    public function test_the_launcher_shows_the_count_on_any_page(): void
    {
        $this->confirm();
        $this->staffReply('Your permit is ready.');

        // The landing page, not the request's own page: the badge follows the
        // citizen around the site.
        $this->get(route('welcome'))
            ->assertOk()
            ->assertSee('speedy-badge', false)
            // @js() writes each quote as a backslash-u0022 escape inside the
            // x-data attribute.
            ->assertSee(str_replace('"', chr(92).'u0022', '"total":1'), false);
    }

    public function test_speedy_knows_when_a_request_is_already_confirmed(): void
    {
        $this->confirm();
        $this->staffReply('Unread.');

        $this->postJson(route('speedy.ask'), ['question' => 'SPD-20261018-TLK234'])
            ->assertOk()
            ->assertJsonPath('context.verified', true)
            ->assertJsonPath('context.unread', 1)
            ->assertJsonPath('context.can_verify', true);
    }

    public function test_an_unconfirmed_request_never_reports_its_unread_count(): void
    {
        $this->staffReply('Unread.');

        $this->postJson(route('speedy.ask'), ['question' => 'SPD-20261018-TLK234'])
            ->assertOk()
            ->assertJsonPath('context.verified', false)
            ->assertJsonPath('context.unread', 0);
    }

    // ── the live channel carries no content ──────────────────────────────

    public function test_the_public_channel_hears_that_something_changed_never_what(): void
    {
        Event::fake([CitizenThreadChanged::class]);

        $this->staffReply('Your Mayor’s clearance number is 12345.');

        Event::assertDispatched(CitizenThreadChanged::class, function (CitizenThreadChanged $event): bool {
            $channels = $event->broadcastOn();

            return count($channels) === 1
                && $channels[0] instanceof Channel
                && ! $channels[0] instanceof PrivateChannel
                && $channels[0]->name === 'documents.SPD-20261018-TLK234'
                && $event->broadcastWith() === ['from' => 'staff'];
        });
    }

    public function test_an_internal_note_does_not_even_ping_the_public_channel(): void
    {
        Event::fake([CitizenThreadChanged::class]);

        $this->document->comments()->create([
            'author_id' => $this->staff->id,
            'author_type' => DocumentComment::AUTHOR_STAFF,
            'body' => 'Internal only.',
            'visibility' => DocumentComment::VISIBILITY_INTERNAL,
        ]);

        Event::assertNotDispatched(CitizenThreadChanged::class);
    }

    public function test_the_full_message_goes_only_to_the_private_staff_channel(): void
    {
        $event = new DocumentCommentPosted($this->staffReply('Visible to the citizen, but not to strangers.'));

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
    }

    // ── the reply email ──────────────────────────────────────────────────

    public function test_the_reply_email_opens_speedy_on_the_conversation(): void
    {
        $html = (new StaffMessage($this->staffReply('Please come in on Monday.')))->render();

        $this->assertStringContainsString('speedy=messages', $html);
        $this->assertStringContainsString('view=citizen', $html);
        $this->assertStringContainsString('Reply in Speedy', $html);
    }
}
