<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\RequestType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A contact number is exactly 11 digits (09XXXXXXXXX).
 *
 * A number that is one digit long or one digit short is a request the office
 * cannot follow up on, so the length is enforced — and the refusal has to say
 * how many digits were entered and what to do about it, not just "invalid".
 */
class ContactNumberTest extends TestCase
{
    use RefreshDatabase;

    private function permitType(): void
    {
        $this->seedRolesAndPermissions();

        RequestType::create([
            'name' => 'Barangay Clearance',
            'kind' => RequestType::KIND_DOCUMENT,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function filing(array $overrides = []): array
    {
        return array_merge([
            'document_type' => 'Barangay Clearance',
            'citizen_name' => 'Juan Dela Cruz',
            'citizen_email' => 'juan@example.com',
            'consent' => '1',
        ], $overrides);
    }

    public function test_an_eleven_digit_number_is_accepted(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->permitType();

        $this->post(route('public.request.store'), $this->filing(['citizen_contact' => '09123456789']))
            ->assertSessionHasNoErrors();

        $this->assertSame('09123456789', Document::firstOrFail()->citizen_contact);
    }

    public function test_more_than_eleven_digits_is_refused_with_the_count_and_the_fix(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->permitType();

        $response = $this->post(route('public.request.store'), $this->filing([
            'citizen_contact' => '091234567890123',
        ]));

        $response->assertSessionHasErrors('citizen_contact');
        $this->assertSame(0, Document::count());

        $message = session('errors')->first('citizen_contact');
        $this->assertStringContainsString('15 digits', $message);
        $this->assertStringContainsString('exactly 11 digits', $message);
        $this->assertStringContainsString('Remove the extra 4 digits', $message);
        $this->assertStringContainsString('09123456789', $message);
    }

    public function test_fewer_than_eleven_digits_is_refused_with_how_many_are_missing(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->permitType();

        $message = $this->post(route('public.request.store'), $this->filing([
            'citizen_contact' => '0912345',
        ]))->assertSessionHasErrors('citizen_contact')
            ->getSession()
            ->get('errors')
            ->first('citizen_contact');

        $this->assertStringContainsString('7 digits', $message);
        $this->assertStringContainsString('Add the missing 4 digits', $message);
    }

    public function test_a_number_that_does_not_start_with_zero_nine_is_refused(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->permitType();

        $message = $this->post(route('public.request.store'), $this->filing([
            'citizen_contact' => '12345678901',
        ]))->assertSessionHasErrors('citizen_contact')
            ->getSession()
            ->get('errors')
            ->first('citizen_contact');

        $this->assertStringContainsString('starts with 09', $message);
    }

    public function test_letters_are_refused_with_a_digits_only_explanation(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->permitType();

        $message = $this->post(route('public.request.store'), $this->filing([
            'citizen_contact' => '0912-CALL-ME',
        ]))->assertSessionHasErrors('citizen_contact')
            ->getSession()
            ->get('errors')
            ->first('citizen_contact');

        $this->assertStringContainsString('digits only', $message);
    }

    /**
     * Forgiving of how people write the number, strict about what it is: every
     * accepted spelling is stored in the one canonical form the contact check
     * on the citizen thread compares against.
     *
     * @return list<array{string, string}>
     */
    public static function equivalentSpellings(): array
    {
        return [
            'spaces' => ['0912 345 6789', '09123456789'],
            'dashes' => ['0912-345-6789', '09123456789'],
            'country code' => ['+63 912 345 6789', '09123456789'],
            'country code, no plus' => ['639123456789', '09123456789'],
            'trunk zero dropped' => ['9123456789', '09123456789'],
        ];
    }

    #[DataProvider('equivalentSpellings')]
    public function test_separators_and_country_codes_are_normalised(string $typed, string $stored): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->permitType();

        $this->post(route('public.request.store'), $this->filing(['citizen_contact' => $typed]))
            ->assertSessionHasNoErrors();

        $this->assertSame($stored, Document::firstOrFail()->citizen_contact);
    }

    public function test_the_public_form_caps_the_field_at_eleven_digits(): void
    {
        $this->permitType();

        $response = $this->get(route('public.request.create'))->assertOk();

        $response->assertSee('maxlength="11"', false);
        $response->assertSee('11 digits, starting with 09', false);
        // The old +63 affix implied a 10-digit number and is gone.
        $response->assertDontSee('req-affix-label', false);
    }

    public function test_the_staff_edit_form_enforces_the_same_length(): void
    {
        $this->seedRolesAndPermissions();

        $staff = User::factory()->create()->assignRole('staff');

        $document = Document::create([
            'tracking_number' => 'SPD-CONTACT-'.uniqid(),
            'document_type' => 'Business Permit',
            'citizen_name' => 'Juan Dela Cruz',
            'citizen_contact' => '09123456789',
            'status' => 'in_progress',
            'created_by' => $staff->id,
            'assigned_to' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->put(route('documents.update', $document), [
                'document_type' => $document->document_type,
                'citizen_name' => 'Juan Dela Cruz',
                'citizen_contact' => '0912345678912',
            ])
            ->assertSessionHasErrors('citizen_contact');

        $this->assertSame('09123456789', $document->fresh()->citizen_contact);
    }
}
