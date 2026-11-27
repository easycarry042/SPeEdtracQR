import { describe, expect, it } from 'vitest';
import {
  validateCitizenRequestClient,
  validateContactNumberClient,
  type ClientRequestTypeMeta,
} from '@/lib/client/citizen-request-validation';

const TODAY = '2026-11-27';
const NOW_MANILA = { date: TODAY, time: '10:30' };

const DOCUMENT_TYPE: ClientRequestTypeMeta = {
  name: 'Certificate of Residency',
  kind: 'document',
  resource: null,
  serviceableUnits: null,
  detailsLabel: null,
};

const BOOKING_TYPE: ClientRequestTypeMeta = {
  name: 'City Plaza Reservation',
  kind: 'booking',
  resource: 'City Plaza',
  serviceableUnits: null,
  detailsLabel: null,
};

const EQUIPMENT_TYPE: ClientRequestTypeMeta = {
  name: 'Spider Banner Borrowing',
  kind: 'equipment',
  resource: 'Spider Banner',
  serviceableUnits: 10,
  detailsLabel: null,
};

const SERVICE_TYPE: ClientRequestTypeMeta = {
  name: 'Events Set-up',
  kind: 'service',
  resource: null,
  serviceableUnits: null,
  detailsLabel: 'Venue of the event',
};

function validIdentityForm(docType = DOCUMENT_TYPE.name): FormData {
  const form = new FormData();
  form.set('document_type', docType);
  form.set('citizen_name', 'Juan Dela Cruz');
  form.set('citizen_email', 'juan@example.com');
  form.set('citizen_contact', '09171234567');
  form.set('consent', '1');
  return form;
}

describe('citizen-request-validation', () => {
  it('flags missing identity fields and consent together in one pass', () => {
    const form = new FormData();
    form.set('document_type', '');
    form.set('citizen_name', '   ');
    form.set('citizen_email', '');

    const errors = validateCitizenRequestClient(form, null, TODAY, NOW_MANILA);

    expect(errors.document_type).toBeDefined();
    expect(errors.citizen_name).toBeDefined();
    expect(errors.citizen_email).toBeDefined();
    expect(errors.consent).toBeDefined();
  });

  it('validates email format and Philippine contact numbers across formats and edge cases', () => {
    expect(validateContactNumberClient('')).toBeNull();
    expect(validateContactNumberClient('09171234567')).toBeNull();
    expect(validateContactNumberClient('+63 917 123 4567')).toBeNull();
    expect(validateContactNumberClient('0917123456')).toContain('10 digits');
    expect(validateContactNumberClient('091712345678')).toContain('12 digits');
    expect(validateContactNumberClient('08171234567')).toContain('starts with 09');
    expect(validateContactNumberClient('0917-abc-567')).toContain('digits only');

    const form = validIdentityForm();
    form.set('citizen_email', 'not-an-email');
    form.set('citizen_contact', '08171234567');

    const errors = validateCitizenRequestClient(form, DOCUMENT_TYPE, TODAY, NOW_MANILA);
    expect(errors.citizen_email).toMatch(/valid email/i);
    expect(errors.citizen_contact).toMatch(/starts with 09/i);
  });

  it('enforces required detailsLabel and field max-length limits', () => {
    const form = validIdentityForm(SERVICE_TYPE.name);
    form.set('needed_by', '2026-11-28');
    form.set('requester_office', 'x'.repeat(256));
    form.set('purpose', 'p'.repeat(256));
    form.set('description', 'd'.repeat(5001));
    form.set('details', '   ');

    const errors = validateCitizenRequestClient(form, SERVICE_TYPE, TODAY, NOW_MANILA);

    expect(errors.details).toContain('Venue of the event');
    expect(errors.requester_office).toContain('255');
    expect(errors.purpose).toContain('255');
    expect(errors.description).toContain('5,000');

    const longDetailsForm = validIdentityForm(SERVICE_TYPE.name);
    longDetailsForm.set('needed_by', '2026-11-28');
    longDetailsForm.set('details', 'a'.repeat(1001));
    expect(
      validateCitizenRequestClient(longDetailsForm, SERVICE_TYPE, TODAY, NOW_MANILA).details,
    ).toContain('1,000');
  });

  it('validates booking dates, end time order, and past start times on today', () => {
    const missingDate = validIdentityForm(BOOKING_TYPE.name);
    missingDate.set('start_time', '09:00');
    missingDate.set('end_time', '10:00');
    expect(
      validateCitizenRequestClient(missingDate, BOOKING_TYPE, TODAY, NOW_MANILA).booking_date,
    ).toBeDefined();

    const pastDate = validIdentityForm(BOOKING_TYPE.name);
    pastDate.set('booking_date', '2026-11-26');
    pastDate.set('start_time', '09:00');
    pastDate.set('end_time', '10:00');
    expect(
      validateCitizenRequestClient(pastDate, BOOKING_TYPE, TODAY, NOW_MANILA).booking_date,
    ).toMatch(/today or a future/i);

    const badEnd = validIdentityForm(BOOKING_TYPE.name);
    badEnd.set('booking_date', '2026-11-28');
    badEnd.set('start_time', '14:00');
    badEnd.set('end_time', '13:00');
    expect(
      validateCitizenRequestClient(badEnd, BOOKING_TYPE, TODAY, NOW_MANILA).end_time,
    ).toMatch(/after the start time/i);

    const pastTodayTime = validIdentityForm(BOOKING_TYPE.name);
    pastTodayTime.set('booking_date', TODAY);
    pastTodayTime.set('start_time', '09:30');
    pastTodayTime.set('end_time', '10:30');
    expect(
      validateCitizenRequestClient(pastTodayTime, BOOKING_TYPE, TODAY, NOW_MANILA).start_time,
    ).toMatch(/already passed/i);
  });

  it('validates equipment quantity limits and pickup/return date ordering', () => {
    const overStock = validIdentityForm(EQUIPMENT_TYPE.name);
    overStock.set('quantity', '12');
    overStock.set('needed_date', '2026-11-26');
    overStock.set('return_date', '2026-11-25');

    const errors = validateCitizenRequestClient(overStock, EQUIPMENT_TYPE, TODAY, NOW_MANILA);
    expect(errors.quantity).toContain('10 Spider Banner');
    expect(errors.needed_date).toMatch(/today or a future/i);
    expect(errors.return_date).toMatch(/before the pickup date/i);

    const emptyQty = validIdentityForm(EQUIPMENT_TYPE.name);
    emptyQty.set('needed_date', '2026-11-28');
    emptyQty.set('return_date', '2026-11-29');
    expect(
      validateCitizenRequestClient(emptyQty, EQUIPMENT_TYPE, TODAY, NOW_MANILA).quantity,
    ).toMatch(/at least 1/i);
  });

  it('validates service optional quantity and due date', () => {
    const okForm = validIdentityForm(SERVICE_TYPE.name);
    okForm.set('details', 'City Hall Lobby');
    okForm.set('needed_by', '2026-11-28');
    expect(validateCitizenRequestClient(okForm, SERVICE_TYPE, TODAY, NOW_MANILA)).toEqual({});

    const badForm = validIdentityForm(SERVICE_TYPE.name);
    badForm.set('details', 'City Hall Lobby');
    badForm.set('quantity', '0');
    badForm.set('needed_by', '2026-11-26');

    const errors = validateCitizenRequestClient(badForm, SERVICE_TYPE, TODAY, NOW_MANILA);
    expect(errors.quantity).toMatch(/whole number/i);
    expect(errors.needed_by).toMatch(/today or a future/i);
  });
});
