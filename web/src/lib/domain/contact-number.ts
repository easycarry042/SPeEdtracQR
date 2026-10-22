/**
 * One definition of a citizen contact number, shared by every form that
 * collects one (public request, staff intake, staff edit).
 *
 * Port of `App\Rules\ContactNumber`. A Philippine mobile number is exactly 11
 * digits in national form (09XXXXXXXXX). Anything longer is almost always a typo
 * or a country code typed twice, and a number that is wrong by one digit is a
 * request nobody can follow up on — so the length is a hard constraint rather
 * than a suggestion.
 *
 * Every failure message names WHAT is wrong (including the count actually
 * entered) and HOW to fix it: Norman's "good error messages" — the system should
 * explain the slip, not just refuse the input.
 */

/** Digits in a Philippine mobile number in national form (09XXXXXXXXX). */
export const DIGITS = 11;

/** An example in the canonical shape, quoted back in error messages and hints. */
export const EXAMPLE = '09123456789';

/** Separators a citizen may reasonably type; they are stripped, not rejected. */
const SEPARATORS = /[\s()\-.]+/g;

const isDigits = (value: string): boolean => value !== '' && /^\d+$/.test(value);

/**
 * Reduce whatever was typed to the canonical 11-digit national form, so
 * "+63 917 123 4567", "(0917) 123-4567" and "9171234567" all store identically —
 * which is also what the citizen thread's contact check compares against.
 *
 * Input that cannot be read as a phone number at all (letters, or a non-string
 * smuggled in by a crafted request) is returned untouched so `validate` below can
 * describe it.
 */
export function normalise(value: unknown): string | null {
  // Anything that isn't a string cannot be a phone number; `validate` below
  // reports that case from the raw value, so there is nothing to preserve here.
  if (typeof value !== 'string') {
    return null;
  }

  const trimmed = value.trim();

  if (trimmed === '') {
    return null;
  }

  const compact = trimmed.replace(SEPARATORS, '');

  // Only strip a leading + when the rest is digits; otherwise leave the value
  // alone and let validation report it.
  const digits = compact.startsWith('+') ? compact.slice(1) : compact;

  if (!isDigits(digits)) {
    return trimmed;
  }

  // +63 917 123 4567 / 63 917 123 4567 -> 0917 123 4567
  if (digits.length === DIGITS + 1 && digits.startsWith('63')) {
    return `0${digits.slice(2)}`;
  }

  // 9171234567 (the trunk "0" dropped, as people write it after +63)
  if (digits.length === DIGITS - 1 && digits.startsWith('9')) {
    return `0${digits}`;
  }

  return digits;
}

/**
 * The validation failure for a contact number, or null when it is acceptable.
 * An empty value passes — the field is optional on every form that uses it.
 */
export function validate(value: unknown): string | null {
  if (value === null || value === undefined || value === '') {
    return null;
  }

  if (typeof value !== 'string') {
    return `Please enter your contact number as digits, for example ${EXAMPLE}.`;
  }

  const normalised = String(normalise(value) ?? '');
  const digits = normalised.replace(/\D+/g, '');

  if (digits === '' || !isDigits(normalised)) {
    return `Your contact number may contain digits only (spaces, dashes and a +63 prefix are fine). Please retype it as ${EXAMPLE}.`;
  }

  const { length } = digits;

  if (length > DIGITS) {
    const extra = length - DIGITS;

    return `You entered ${length} digits, but a mobile number is exactly ${DIGITS} digits. Remove the ${
      extra === 1 ? 'extra digit' : `extra ${extra} digits`
    } — write it as ${EXAMPLE} (a +63 prefix is not needed).`;
  }

  if (length < DIGITS) {
    const missing = DIGITS - length;

    return `You entered ${length} digits, but a mobile number is exactly ${DIGITS} digits. Add the ${
      missing === 1 ? 'missing digit' : `missing ${missing} digits`
    } — write it as ${EXAMPLE}.`;
  }

  if (!digits.startsWith('09')) {
    return `A Philippine mobile number starts with 09, for example ${EXAMPLE}. Please check the first two digits of the number you entered.`;
  }

  return null;
}
