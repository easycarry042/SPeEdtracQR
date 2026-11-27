/**
 * Client-safe validation rules for the public citizen request form.
 *
 * Mirrors `request-submission.ts` and `contact-number.ts` so citizens receive
 * immediate per-field feedback and highlights before any server round-trip.
 */

export type ClientRequestTypeMeta = {
  name: string;
  kind: string;
  resource: string | null;
  serviceableUnits: number | null;
  detailsLabel: string | null;
};

const CONTACT_DIGITS = 11;
const CONTACT_EXAMPLE = '09123456789';
const CONTACT_SEPARATORS = /[\s()\-.]+/g;
const DATE_RE = /^\d{4}-\d{2}-\d{2}$/;
const TIME_RE = /^\d{2}:\d{2}$/;
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const isDigits = (value: string): boolean => value !== '' && /^\d+$/.test(value);

const str = (form: FormData, key: string): string => {
  const raw = form.get(key);
  return typeof raw === 'string' ? raw.trim() : '';
};

/** Returns current Manila date (`YYYY-MM-DD`) and 24h time (`HH:MM`). */
export function currentManilaTime(): { date: string; time: string } {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Manila',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).formatToParts(new Date());

  const get = (partType: Intl.DateTimeFormatPartTypes): string =>
    parts.find((p) => p.type === partType)?.value ?? '00';

  const rawHour = get('hour');
  const hour = rawHour === '24' ? '00' : rawHour;

  return {
    date: `${get('year')}-${get('month')}-${get('day')}`,
    time: `${hour}:${get('minute')}`,
  };
}

function normaliseContactClient(raw: string): string {
  const trimmed = raw.trim();
  if (trimmed === '') {
    return '';
  }

  const compact = trimmed.replace(CONTACT_SEPARATORS, '');
  const digits = compact.startsWith('+') ? compact.slice(1) : compact;

  if (!isDigits(digits)) {
    return trimmed;
  }

  if (digits.length === CONTACT_DIGITS + 1 && digits.startsWith('63')) {
    return `0${digits.slice(2)}`;
  }

  if (digits.length === CONTACT_DIGITS - 1 && digits.startsWith('9')) {
    return `0${digits}`;
  }

  return digits;
}

/** Client-side mirror of `contact-number.validate()`. Empty value passes. */
export function validateContactNumberClient(raw: string): string | null {
  if (raw.trim() === '') {
    return null;
  }

  const normalised = normaliseContactClient(raw);
  const digits = normalised.replace(/\D+/g, '');

  if (digits === '' || !isDigits(normalised)) {
    return `Your contact number may contain digits only (spaces, dashes and a +63 prefix are fine). Please retype it as ${CONTACT_EXAMPLE}.`;
  }

  const { length } = digits;

  if (length > CONTACT_DIGITS) {
    const extra = length - CONTACT_DIGITS;
    return `You entered ${length} digits, but a mobile number is exactly ${CONTACT_DIGITS} digits. Remove the ${
      extra === 1 ? 'extra digit' : `extra ${extra} digits`
    } — write it as ${CONTACT_EXAMPLE} (a +63 prefix is not needed).`;
  }

  if (length < CONTACT_DIGITS) {
    const missing = CONTACT_DIGITS - length;
    return `You entered ${length} digits, but a mobile number is exactly ${CONTACT_DIGITS} digits. Add the ${
      missing === 1 ? 'missing digit' : `missing ${missing} digits`
    } — write it as ${CONTACT_EXAMPLE}.`;
  }

  if (!digits.startsWith('09')) {
    return `A Philippine mobile number starts with 09, for example ${CONTACT_EXAMPLE}. Please check the first two digits of the number you entered.`;
  }

  return null;
}

function parseWholeQuantity(raw: string): number | null {
  if (!/^\d+$/.test(raw)) {
    return null;
  }
  const value = Number.parseInt(raw, 10);
  return Number.isInteger(value) && value >= 1 && value <= 100000 ? value : null;
}

/**
 * Validates all citizen identity, consent, length, and kind-specific schedule/stock
 * fields in one pass so every invalid control can be highlighted together.
 */
export function validateCitizenRequestClient(
  form: FormData,
  type: ClientRequestTypeMeta | null,
  today: string,
  nowManila?: { date: string; time: string },
): Record<string, string> {
  const errors: Record<string, string> = {};

  const documentType = str(form, 'document_type');
  if (documentType === '') {
    errors.document_type = 'Please choose what you are requesting.';
  }

  const citizenName = str(form, 'citizen_name');
  if (citizenName === '') {
    errors.citizen_name = 'Please enter your name.';
  } else if (citizenName.length > 255) {
    errors.citizen_name = 'Keep your name to 255 characters.';
  }

  const requesterOffice = str(form, 'requester_office');
  if (requesterOffice.length > 255) {
    errors.requester_office = 'Keep the office or organization to 255 characters.';
  }

  const email = str(form, 'citizen_email');
  if (email === '') {
    errors.citizen_email = 'Please enter your email address.';
  } else if (!EMAIL_RE.test(email)) {
    errors.citizen_email = 'Please enter a valid email address.';
  } else if (email.length > 255) {
    errors.citizen_email = 'Keep your email address to 255 characters.';
  }

  const contactProblem = validateContactNumberClient(str(form, 'citizen_contact'));
  if (contactProblem) {
    errors.citizen_contact = contactProblem;
  }

  if (form.get('consent') === null) {
    errors.consent = 'You must agree to the data privacy notice to submit a request.';
  }

  const purpose = str(form, 'purpose');
  if (purpose.length > 255) {
    errors.purpose = 'Keep the specifications or purpose to 255 characters.';
  }

  const description = str(form, 'description');
  if (description.length > 5000) {
    errors.description = 'Keep the description to 5,000 characters.';
  }

  const details = str(form, 'details');
  if (type?.detailsLabel && details === '') {
    errors.details = `Please answer: ${type.detailsLabel}.`;
  } else if (details.length > 1000) {
    errors.details = 'Keep this answer to 1,000 characters.';
  }

  if (type?.kind === 'booking') {
    const bookingDate = str(form, 'booking_date');
    const startTime = str(form, 'start_time');
    const endTime = str(form, 'end_time');

    if (bookingDate === '') {
      errors.booking_date = 'Please choose a date.';
    } else if (!DATE_RE.test(bookingDate) || bookingDate < today) {
      errors.booking_date = 'Please choose today or a future date.';
    }

    const startValid = TIME_RE.test(startTime);
    const endValid = TIME_RE.test(endTime);

    if (!startValid || !endValid) {
      errors.start_time = 'Please give a start and end time.';
    } else if (endTime <= startTime) {
      errors.end_time = 'The end time must be after the start time.';
    } else {
      const current = nowManila ?? currentManilaTime();
      if (bookingDate === current.date && startTime < current.time) {
        errors.start_time = 'That start time has already passed. Please choose a later time.';
      }
    }
  } else if (type?.kind === 'equipment') {
    const quantityRaw = str(form, 'quantity');
    const asked = parseWholeQuantity(quantityRaw);

    if (asked === null) {
      errors.quantity = 'Please enter how many you need (at least 1).';
    } else if (
      type.serviceableUnits !== null &&
      type.serviceableUnits > 0 &&
      asked > type.serviceableUnits
    ) {
      errors.quantity = `The office has ${type.serviceableUnits} ${type.resource ?? 'units'} in total. Please ask for ${type.serviceableUnits} or fewer.`;
    }

    const neededDate = str(form, 'needed_date');
    if (neededDate === '' || !DATE_RE.test(neededDate) || neededDate < today) {
      errors.needed_date = 'Please choose today or a future date.';
    }

    const returnDate = str(form, 'return_date');
    const minReturn = DATE_RE.test(neededDate) && neededDate >= today ? neededDate : today;
    if (returnDate === '' || !DATE_RE.test(returnDate) || returnDate < minReturn) {
      errors.return_date = 'The return date cannot be before the pickup date.';
    }
  } else if (type?.kind === 'service') {
    const quantityRaw = str(form, 'quantity');
    if (quantityRaw !== '' && parseWholeQuantity(quantityRaw) === null) {
      errors.quantity = 'Please enter a whole number (at least 1), or leave it blank.';
    }

    const neededBy = str(form, 'needed_by');
    if (neededBy === '' || !DATE_RE.test(neededBy) || neededBy < today) {
      errors.needed_by = 'Please choose today or a future date.';
    }
  }

  return errors;
}
