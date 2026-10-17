<?php

namespace Tests\Unit\Support;

use App\Support\ContactMask;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What the public tracking page prints in place of the requester's email and
 * phone: enough for them to recognise, too little for anyone else to use.
 */
class ContactMaskTest extends TestCase
{
    /**
     * @return array<string, array{?string, ?string}>
     */
    public static function emails(): array
    {
        return [
            'ordinary address' => ['juan.cruz@gmail.com', 'j•••@gmail.com'],
            'one-letter local part' => ['j@city.gov.ph', 'j•••@city.gov.ph'],
            'surrounding spaces' => ['  maria@example.com ', 'm•••@example.com'],
            'no @ at all' => ['not-an-email', '•••'],
            'nothing before the @' => ['@example.com', '•••'],
            'empty' => ['', null],
            'missing' => [null, null],
        ];
    }

    #[DataProvider('emails')]
    public function test_an_email_keeps_its_first_letter_and_domain(?string $email, ?string $expected): void
    {
        $this->assertSame($expected, ContactMask::email($email));
    }

    /**
     * @return array<string, array{?string, ?string}>
     */
    public static function phones(): array
    {
        return [
            'national form' => ['09171234567', '•••4567'],
            'formatted' => ['+63 917-123-4567', '•••4567'],
            'too short to show any' => ['1234', '•••'],
            'no digits' => ['n/a', null],
            'missing' => [null, null],
        ];
    }

    #[DataProvider('phones')]
    public function test_a_phone_keeps_only_its_last_four_digits(?string $phone, ?string $expected): void
    {
        $this->assertSame($expected, ContactMask::phone($phone));
    }

    public function test_the_mask_never_reveals_how_long_the_value_is(): void
    {
        $this->assertSame(
            mb_strlen((string) ContactMask::email('a@x.com')) - strlen('@x.com'),
            mb_strlen((string) ContactMask::email('averyveryverylongname@x.com')) - strlen('@x.com'),
        );
    }
}
