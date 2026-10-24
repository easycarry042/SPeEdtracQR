<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Support\UploadRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Offices attach more than photos: PDFs, Word files, spreadsheets, and the HEIC
 * photos iPhones produce. Every upload path shares one allow-list.
 */
class MultiFormatUploadTest extends TestCase
{
    use RefreshDatabase;

    private function staffWithDocument(): array
    {
        Storage::fake('local');
        $this->seedRolesAndPermissions();

        $staff = User::factory()->create(['is_active' => true])->assignRole('staff');
        $document = Document::create([
            'tracking_number' => 'SPD-UPL-1',
            'document_type' => 'Business Permit',
            'status' => 'in_progress',
            'assigned_to' => $staff->id,
            'assigned_at' => now(),
            'accepted_at' => now(),
        ]);

        return [$staff, $document];
    }

    /** @return list<array{0: string}> */
    public static function acceptedExtensions(): array
    {
        return array_map(fn (string $extension): array => [$extension], [
            'pdf', 'docx', 'doc', 'xlsx', 'csv', 'txt', 'odt', 'rtf', 'png', 'jpg', 'heic',
        ]);
    }

    #[DataProvider('acceptedExtensions')]
    public function test_staff_can_attach_each_supported_format(string $extension): void
    {
        [$staff, $document] = $this->staffWithDocument();

        $this->actingAs($staff)
            ->post(route('documents.attachments.store', $document), [
                'attachments' => [UploadedFile::fake()->create("evidence.{$extension}", 64)],
            ])
            ->assertOk();

        $this->assertSame(
            1,
            $document->attachments()->count(),
            "Expected a .{$extension} upload to be accepted."
        );
    }

    public function test_executables_are_still_refused(): void
    {
        [$staff, $document] = $this->staffWithDocument();

        $this->actingAs($staff)
            ->post(route('documents.attachments.store', $document), [
                'attachments' => [UploadedFile::fake()->create('payload.exe', 64)],
            ])
            ->assertStatus(302); // validation failure — nothing stored

        $this->assertSame(0, $document->attachments()->count());
    }

    public function test_the_file_picker_advertises_the_same_formats_as_the_validator(): void
    {
        // A picker that hides a format the server accepts (or offers one it
        // rejects) is how "you can only upload images" bugs are reported.
        $accept = UploadRules::accept();

        $this->assertStringContainsString('.pdf', $accept);
        $this->assertStringContainsString('.docx', $accept);
        $this->assertStringContainsString('.xlsx', $accept);
        $this->assertStringContainsString('.heic', $accept);
        $this->assertStringNotContainsString('.exe', $accept);
    }

    public function test_heic_is_accepted_but_not_treated_as_an_inline_image(): void
    {
        // Browsers cannot render HEIC inline, so it must show as a file chip
        // rather than a broken thumbnail.
        $this->assertTrue(in_array('heic', UploadRules::extensions(), true));
        $this->assertFalse(UploadRules::isImage('photo.heic'));
        $this->assertTrue(UploadRules::isImage('photo.jpg'));
    }
}
