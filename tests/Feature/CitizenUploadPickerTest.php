<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Support\UploadRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The citizen's "Upload supporting documents" panel.
 *
 * The bare file input reported only "3 files" and offered no way to drop one of
 * them, so a mis-picked file meant redoing the whole selection. Each file is
 * now listed by name with its own Remove button, and the 5-file cap is visible
 * as it fills.
 */
class CitizenUploadPickerTest extends TestCase
{
    use RefreshDatabase;

    private function document(): Document
    {
        return Document::create([
            'tracking_number' => 'SPD-UPL-'.uniqid(),
            'document_type' => 'Business Permit',
            'citizen_name' => 'Maria Santos',
            'status' => 'in_progress',
        ]);
    }

    public function test_the_picker_shows_the_cap_and_a_place_for_the_chosen_files(): void
    {
        $document = $this->document();

        $response = $this->get(route('track.show', $document->tracking_number))->assertOk();

        $response->assertSee('data-file-picker', false);
        $response->assertSee('data-max-files="5"', false);
        $response->assertSee('0 of 5 files selected');
        $response->assertSee('Up to 5 files per upload.');
        // The list each chosen file is rendered into, and the per-file control.
        $response->assertSee('data-file-list', false);
        $response->assertSee("remove.textContent = 'Remove';", false);
        // Nothing to send yet, so the button says so before it is pressed.
        $response->assertSee('data-file-submit disabled', false);
    }

    public function test_the_server_limit_the_picker_shows_is_the_one_it_enforces(): void
    {
        Storage::fake('local');
        $document = $this->document();

        $files = collect(range(1, 6))
            ->map(fn (int $n) => UploadedFile::fake()->create("proof-{$n}.pdf", 10))
            ->all();

        $this->post(route('track.citizen-upload', $document->tracking_number), [
            'attachments' => $files,
        ])->assertSessionHasErrors('attachments');

        $this->assertSame(0, $document->attachments()->count());
    }

    public function test_five_files_are_accepted_and_each_is_recorded(): void
    {
        Storage::fake('local');
        $document = $this->document();

        $files = collect(range(1, 5))
            ->map(fn (int $n) => UploadedFile::fake()->create("proof-{$n}.pdf", 10))
            ->all();

        $this->post(route('track.citizen-upload', $document->tracking_number), [
            'attachments' => $files,
        ])->assertSessionHasNoErrors();

        $this->assertSame(5, $document->attachments()->count());
    }

    public function test_the_size_limit_shown_comes_from_the_upload_rules(): void
    {
        $document = $this->document();

        $this->get(route('track.show', $document->tracking_number))
            ->assertOk()
            ->assertSee('data-max-kilobytes="'.UploadRules::MAX_KILOBYTES.'"', false)
            ->assertSee(UploadRules::hint());
    }
}
