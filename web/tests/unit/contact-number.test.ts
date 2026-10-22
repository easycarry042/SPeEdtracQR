import { describe, expect, it } from 'vitest';
import { DIGITS, EXAMPLE, normalise, validate } from '@/lib/domain/contact-number';

/**
 * Port of `tests/Feature/ContactNumberTest.php`.
 *
 * The normalisation table is the part that matters beyond cosmetics: the citizen
 * thread's contact check compares the number on file against what the citizen
 * types to prove the request is theirs, so "+63 912 345 6789" and "0912-345-6789"
 * must reduce to one stored shape or that check refuses the rightful owner.
 */
describe('ContactNumber.normalise', () => {
  const CASES: Array<[string, string, string | null]> = [
    ['spaces', '0912 345 6789', '09123456789'],
    ['dashes', '0912-345-6789', '09123456789'],
    ['dots', '0912.345.6789', '09123456789'],
    ['parentheses', '(0912) 345-6789', '09123456789'],
    ['country code', '+63 912 345 6789', '09123456789'],
    ['country code, no plus', '639123456789', '09123456789'],
    ['trunk zero dropped', '9123456789', '09123456789'],
    ['already canonical', '09123456789', '09123456789'],
    ['empty', '', null],
    ['whitespace only', '   ', null],
  ];

  for (const [name, typed, stored] of CASES) {
    it(`normalises ${name}`, () => {
      expect(normalise(typed)).toBe(stored);
    });
  }

  it('leaves something unreadable as a number alone, so validate can describe it', () => {
    expect(normalise('0912-CALL-ME')).toBe('0912-CALL-ME');
  });

  it('treats a non-string as absent rather than throwing', () => {
    expect(normalise(null)).toBeNull();
    expect(normalise(undefined)).toBeNull();
    expect(normalise(42)).toBeNull();
  });
});

describe('ContactNumber.validate', () => {
  it('accepts an eleven digit number', () => {
    expect(validate('09123456789')).toBeNull();
  });

  it('accepts an empty value — the field is optional', () => {
    expect(validate('')).toBeNull();
    expect(validate(null)).toBeNull();
    expect(validate(undefined)).toBeNull();
  });

  it('refuses more than eleven digits with the count and the fix', () => {
    const message = validate('091234567890123');

    // Norman's "good error messages": name what is wrong, including the count
    // actually entered, and how to fix it.
    expect(message).toContain('15 digits');
    expect(message).toContain(`${DIGITS} digits`);
    expect(message).toContain('Remove the extra 4 digits');
    expect(message).toContain(EXAMPLE);
  });

  it('says "extra digit" in the singular when exactly one too many', () => {
    expect(validate('091234567891')).toContain('Remove the extra digit');
  });

  it('refuses fewer than eleven digits with how many are missing', () => {
    const message = validate('0912345');

    expect(message).toContain('7 digits');
    expect(message).toContain('Add the missing 4 digits');
  });

  it('says "missing digit" in the singular when exactly one short', () => {
    expect(validate('0912345678')).toContain('Add the missing digit');
  });

  it('refuses a number that does not start with 09', () => {
    const message = validate('08123456789');

    expect(message).toContain('starts with 09');
    expect(message).toContain(EXAMPLE);
  });

  it('refuses letters with a digits-only explanation', () => {
    const message = validate('0912-CALL-ME');

    expect(message).toContain('digits only');
    expect(message).toContain(EXAMPLE);
  });

  it('accepts the normalised forms it promises to normalise', () => {
    for (const typed of ['0912 345 6789', '+63 912 345 6789', '9123456789']) {
      expect(validate(normalise(typed))).toBeNull();
    }
  });
});
