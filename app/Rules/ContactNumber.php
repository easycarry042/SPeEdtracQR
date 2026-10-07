<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * One definition of a citizen contact number, shared by every form that
 * collects one (public request, staff intake, staff edit).
 *
 * A Philippine mobile number is exactly 11 digits in national form
 * (09XXXXXXXXX). Anything longer is almost always a typo or a country code
 * typed twice, and a number that is wrong by one digit is a request nobody can
 * follow up on — so the length is a hard constraint rather than a suggestion.
 *
 * Every failure message names WHAT is wrong (including the count actually
 * entered) and HOW to fix it: Norman's "good error messages" — the system
 * should explain the slip, not just refuse the input.
 */
class ContactNumber implements ValidationRule
{
    /** Digits in a Philippine mobile number in national form (09XXXXXXXXX). */
    public const int DIGITS = 11;

    /** An example in the canonical shape, quoted back in error messages and hints. */
    public const string EXAMPLE = '09123456789';

    /** Separators a citizen may reasonably type; they are stripped, not rejected. */
    private const string SEPARATORS = '/[\s()\-.]+/';

    /**
     * Reduce whatever was typed to the canonical 11-digit national form, so
     * "+63 917 123 4567", "(0917) 123-4567" and "9171234567" all store
     * identically — which is also what CitizenThreadAccess compares against.
     *
     * Input that cannot be read as a phone number at all (letters, or a
     * non-string smuggled in by a crafted request) is returned untouched so the
     * rule below can describe it.
     */
    public static function normalise(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $compact = (string) preg_replace(self::SEPARATORS, '', $trimmed);

        // Only strip a leading + when the rest is digits; otherwise leave the
        // value alone and let validation report it.
        $digits = Str::startsWith($compact, '+') ? substr($compact, 1) : $compact;

        if (! ctype_digit($digits) || $digits === '') {
            return $trimmed;
        }

        // +63 917 123 4567 / 63 917 123 4567 → 0917 123 4567
        if (strlen($digits) === self::DIGITS + 1 && Str::startsWith($digits, '63')) {
            return '0'.substr($digits, 2);
        }

        // 9171234567 (the trunk "0" dropped, as people write it after +63)
        if (strlen($digits) === self::DIGITS - 1 && Str::startsWith($digits, '9')) {
            return '0'.$digits;
        }

        return $digits;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail('Please enter your contact number as digits, for example '.self::EXAMPLE.'.');

            return;
        }

        $normalised = (string) self::normalise($value);
        $digits = (string) preg_replace('/\D+/', '', $normalised);

        if ($digits === '' || ! ctype_digit($normalised)) {
            $fail('Your contact number may contain digits only (spaces, dashes and a +63 prefix are fine). Please retype it as '.self::EXAMPLE.'.');

            return;
        }

        $length = strlen($digits);

        if ($length > self::DIGITS) {
            $extra = $length - self::DIGITS;

            $fail("You entered {$length} digits, but a mobile number is exactly ".self::DIGITS.' digits. Remove the '.($extra === 1 ? 'extra digit' : "extra {$extra} digits").' — write it as '.self::EXAMPLE.' (a +63 prefix is not needed).');

            return;
        }

        if ($length < self::DIGITS) {
            $missing = self::DIGITS - $length;

            $fail("You entered {$length} digits, but a mobile number is exactly ".self::DIGITS.' digits. Add the '.($missing === 1 ? 'missing digit' : "missing {$missing} digits").' — write it as '.self::EXAMPLE.'.');

            return;
        }

        if (! Str::startsWith($digits, '09')) {
            $fail('A Philippine mobile number starts with 09, for example '.self::EXAMPLE.'. Please check the first two digits of the number you entered.');
        }
    }
}
