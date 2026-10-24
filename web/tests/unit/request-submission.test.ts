import { describe, expect, it } from 'vitest';
import {
  KIND_BOOKING,
  KIND_DOCUMENT,
  KIND_EQUIPMENT,
  KIND_SERVICE,
  type ResourceStock,
  type SubmissionInput,
  resolveSchedule,
  serviceableUnits,
  validateIdentity,
} from '@/lib/domain/request-submission';
import { parseNaive } from '@/lib/time';

/**
 * Port of `PublicRequestRequirementsTest`, `BookingRequestTest` and the
 * schedule-validation half of `RequestRoutingTest`.
 *
 * These are the rules that decide whether a citizen's request is filed at all,
 * so each refusal is pinned to the field it lands on — the form shows them
 * inline, and an error on the wrong field is an error the citizen cannot act on.
 */
const AT = parseNaive('2026-10-22 10:00')!;

function input(overrides: Partial<SubmissionInput> = {}): SubmissionInput {
  return {
    document_type: 'Barangay Clearance',
    citizen_name: 'Juan Cruz',
    citizen_email: 'juan@example.com',
    citizen_contact: '09123456789',
    consent: true,
    ...overrides,
  };
}

describe('validateIdentity', () => {
  it('accepts a complete filing', () => {
    expect(validateIdentity(input())).toEqual({});
  });

  it('accepts no contact number — the field is optional', () => {
    expect(validateIdentity(input({ citizen_contact: null }))).toEqual({});
  });

  it('requires a request type', () => {
    expect(validateIdentity(input({ document_type: '  ' }))).toHaveProperty('document_type');
  });

  it('requires a name', () => {
    expect(validateIdentity(input({ citizen_name: '' }))).toHaveProperty('citizen_name');
  });

  it('requires an email, and one that looks like an address', () => {
    expect(validateIdentity(input({ citizen_email: '' }))).toHaveProperty('citizen_email');
    expect(validateIdentity(input({ citizen_email: 'not-an-email' }))).toHaveProperty('citizen_email');
    expect(validateIdentity(input({ citizen_email: 'juan@example' }))).toHaveProperty('citizen_email');
  });

  it('refuses a filing without privacy consent — RA 10173 is not optional', () => {
    const errors = validateIdentity(input({ consent: false }));

    expect(errors.consent).toContain('data privacy notice');
  });

  it('reports a bad contact number on its own field', () => {
    const errors = validateIdentity(input({ citizen_contact: '0912345' }));

    expect(errors).toHaveProperty('citizen_contact');
    expect(errors).not.toHaveProperty('citizen_name');
  });

  it('reports every problem at once, so the citizen fixes one form rather than four', () => {
    const errors = validateIdentity(
      input({ document_type: '', citizen_name: '', citizen_email: '', consent: false }),
    );

    expect(Object.keys(errors).sort()).toEqual([
      'citizen_email', 'citizen_name', 'consent', 'document_type',
    ]);
  });
});

describe('resolveSchedule — document requests', () => {
  it('has no schedule at all', () => {
    const result = resolveSchedule(KIND_DOCUMENT, input(), null, AT);

    expect(result).toEqual({ schedule: { quantity: null, neededBy: null, reservation: null } });
  });

  it('treats an unknown kind as scheduleless rather than refusing', () => {
    expect(resolveSchedule('', input(), null, AT)).toHaveProperty('schedule');
  });
});

describe('resolveSchedule — facility reservations', () => {
  const facility = (overrides: Partial<SubmissionInput> = {}) =>
    resolveSchedule(
      KIND_BOOKING,
      input({ booking_date: '2026-10-25', start_time: '09:00', end_time: '12:00', ...overrides }),
      { name: 'Covered Court', total_quantity: null, damaged_quantity: 0 },
      AT,
    );

  it('reserves the window asked for', () => {
    const result = facility();

    expect(result).toHaveProperty('schedule');
    if (!('schedule' in result)) return;

    expect(result.schedule.reservation?.starts_at.toISOString()).toBe('2026-10-25T09:00:00.000Z');
    expect(result.schedule.reservation?.ends_at.toISOString()).toBe('2026-10-25T12:00:00.000Z');
    // A facility is booked, not counted out of stock.
    expect(result.schedule.quantity).toBeNull();
  });

  it('requires a date', () => {
    expect(facility({ booking_date: '' })).toEqual({
      errors: { booking_date: 'Please choose a date.' },
    });
  });

  it('requires both times in HH:MM', () => {
    expect(facility({ start_time: '9am' })).toHaveProperty('errors.start_time');
    expect(facility({ end_time: '' })).toHaveProperty('errors.start_time');
  });

  it('refuses an end time that is not after the start', () => {
    expect(facility({ end_time: '09:00' })).toHaveProperty('errors.end_time');
    expect(facility({ end_time: '08:00' })).toHaveProperty('errors.end_time');
  });

  it('refuses a window that has already passed', () => {
    const result = facility({ booking_date: '2026-10-22', start_time: '08:00', end_time: '09:00' });

    expect(result).toHaveProperty('errors.start_time');
    if ('errors' in result) {
      expect(result.errors.start_time).toContain('already passed');
    }
  });

  it('allows a window later the same day', () => {
    expect(
      facility({ booking_date: '2026-10-22', start_time: '14:00', end_time: '16:00' }),
    ).toHaveProperty('schedule');
  });
});

describe('resolveSchedule — equipment borrowing', () => {
  const chairs = { name: 'Chairs', total_quantity: 50, damaged_quantity: 5 };

  const borrow = (overrides: Partial<SubmissionInput> = {}, resource: ResourceStock = chairs) =>
    resolveSchedule(
      KIND_EQUIPMENT,
      input({ quantity: '10', needed_date: '2026-10-25', return_date: '2026-10-27', ...overrides }),
      resource,
      AT,
    );

  it('records the quantity, the pickup date and the counter-hours window', () => {
    const result = borrow();

    expect(result).toHaveProperty('schedule');
    if (!('schedule' in result)) return;

    expect(result.schedule.quantity).toBe(10);
    expect(result.schedule.neededBy).toBe('2026-10-25');
    // Pickup opens at 08:00, return closes at 17:00.
    expect(result.schedule.reservation?.starts_at.toISOString()).toBe('2026-10-25T08:00:00.000Z');
    expect(result.schedule.reservation?.ends_at.toISOString()).toBe('2026-10-27T17:00:00.000Z');
  });

  it('requires a sane quantity', () => {
    for (const quantity of ['', '0', '-1', 'ten', '100001']) {
      expect(borrow({ quantity }), quantity).toHaveProperty('errors.quantity');
    }
  });

  it('refuses more than the office owns, naming the real figure', () => {
    const result = borrow({ quantity: '46' });

    expect(result).toHaveProperty('errors.quantity');
    if ('errors' in result) {
      // 50 owned less 5 damaged — the citizen is told 45, not 50.
      expect(result.errors.quantity).toContain('45 Chairs');
      expect(result.errors.quantity).toContain('45 or fewer');
    }
  });

  it('allows exactly what is serviceable', () => {
    expect(borrow({ quantity: '45' })).toHaveProperty('schedule');
  });

  it('does not cap the ask when the office records no stock figure', () => {
    // total_quantity null means "not counted", not "none available".
    expect(
      borrow({ quantity: '9999' }, { name: 'Chairs', total_quantity: null, damaged_quantity: 0 }),
    ).toHaveProperty('schedule');
  });

  it('refuses a pickup date in the past but allows today', () => {
    expect(borrow({ needed_date: '2026-10-21' })).toHaveProperty('errors.needed_date');
    expect(borrow({ needed_date: '2026-10-22', return_date: '2026-10-23' })).toHaveProperty('schedule');
  });

  it('refuses a return date before the pickup date, and allows a same-day return', () => {
    expect(borrow({ return_date: '2026-10-24' })).toHaveProperty('errors.return_date');
    expect(borrow({ needed_date: '2026-10-25', return_date: '2026-10-25' })).toHaveProperty('schedule');
  });
});

describe('resolveSchedule — service requests', () => {
  const service = (overrides: Partial<SubmissionInput> = {}) =>
    resolveSchedule(KIND_SERVICE, input({ quantity: '20', needed_by: '2026-10-30', ...overrides }), null, AT);

  it('records how many by when, and reserves nothing', () => {
    const result = service();

    expect(result).toHaveProperty('schedule');
    if (!('schedule' in result)) return;

    expect(result.schedule.quantity).toBe(20);
    expect(result.schedule.neededBy).toBe('2026-10-30');
    // No resource is held for a service request.
    expect(result.schedule.reservation).toBeNull();
  });

  it('requires a due date', () => {
    expect(service({ needed_by: '' })).toHaveProperty('errors.needed_by');
  });

  it('takes the quantity as optional (a layout or a post has none), but a real count when given', () => {
    expect(service({ quantity: '' })).toHaveProperty('schedule.quantity', null);
    expect(service({ quantity: '0' })).toHaveProperty('errors.quantity');
    expect(service({ quantity: 'lots' })).toHaveProperty('errors.quantity');
    expect(service({ quantity: '12' })).toHaveProperty('schedule.quantity', 12);
  });

  it('refuses a due date in the past but allows today', () => {
    expect(service({ needed_by: '2026-10-21' })).toHaveProperty('errors.needed_by');
    expect(service({ needed_by: '2026-10-22' })).toHaveProperty('schedule');
  });
});

describe('serviceableUnits', () => {
  it('is what is owned less what is out of service', () => {
    expect(serviceableUnits({ name: 'Chairs', total_quantity: 50, damaged_quantity: 5 })).toBe(45);
  });

  it('never goes negative, however the figures were edited', () => {
    expect(serviceableUnits({ name: 'Chairs', total_quantity: 3, damaged_quantity: 10 })).toBe(0);
  });

  it('is zero for an uncounted or absent resource', () => {
    expect(serviceableUnits({ name: 'Hall', total_quantity: null, damaged_quantity: 0 })).toBe(0);
    expect(serviceableUnits(null)).toBe(0);
  });
});
