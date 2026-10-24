import * as contactNumber from '@/lib/domain/contact-number';
import { parseNaive, toDateString } from '@/lib/time';

/**
 * The rules behind the public request form, kept separate from the server action
 * so they can be tested without a request, a database or a cookie jar.
 *
 * Everything here is a refusal decision: what the citizen must supply, and
 * whether the schedule they asked for is one the office could possibly honour.
 * The two checks that need the database — a facility already booked for that
 * window, and the request type's own existence — stay in the action.
 */
export const KIND_DOCUMENT = 'document';
export const KIND_BOOKING = 'booking';
export const KIND_EQUIPMENT = 'equipment';
export const KIND_SERVICE = 'service';

/** Field name → message, mirroring Laravel's `withErrors` bag. */
export type Errors = Record<string, string>;

export type SubmissionInput = {
  document_type: string;
  citizen_name: string;
  citizen_email: string;
  /** Already run through `contactNumber.normalise`. */
  citizen_contact: string | null;
  consent: boolean;
  booking_date?: string;
  start_time?: string;
  end_time?: string;
  quantity?: string;
  needed_date?: string;
  return_date?: string;
  needed_by?: string;
};

/** What a reservation request resolves to once its schedule is accepted. */
export type Schedule = {
  /** Equipment: how many units. Service: how many to produce. Null otherwise. */
  quantity: number | null;
  /** `Y-m-d` the citizen needs it by, or null. */
  neededBy: string | null;
  /** The booking window, for facility and equipment requests only. */
  reservation: { starts_at: Date; ends_at: Date } | null;
};

export type ResourceStock = {
  name: string;
  total_quantity: number | null;
  damaged_quantity: number;
};

/** Who is asking, and whether they agreed to the privacy notice. */
export function validateIdentity(input: SubmissionInput): Errors {
  const errors: Errors = {};

  if (input.document_type.trim() === '') {
    errors.document_type = 'Please choose what you are requesting.';
  }

  if (input.citizen_name.trim() === '') {
    errors.citizen_name = 'Please enter your name.';
  }

  const email = input.citizen_email.trim();

  if (email === '') {
    errors.citizen_email = 'Please enter your email address.';
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    errors.citizen_email = 'Please enter a valid email address.';
  }

  const contactProblem = contactNumber.validate(input.citizen_contact);

  if (contactProblem) {
    errors.citizen_contact = contactProblem;
  }

  // RA 10173: the office may not process the request without consent, so this is
  // a hard refusal rather than a nudge.
  if (!input.consent) {
    errors.consent = 'You must agree to the data privacy notice to submit a request.';
  }

  return errors;
}

/**
 * The hard ceiling on a borrowing: the office cannot lend what it does not own.
 * Whether enough is free on those particular dates is settled when staff
 * approve — a pending request holds no stock, so two people may both ask for the
 * last fifty chairs and staff decide.
 */
export function serviceableUnits(resource: ResourceStock | null): number {
  return resource ? Math.max(0, (resource.total_quantity ?? 0) - resource.damaged_quantity) : 0;
}

/**
 * Resolve the date/quantity side of a request for its kind.
 *
 * A document request has no schedule at all, so it resolves to all-nulls — the
 * reason the return type is uniform rather than branching at the call site.
 */
export function resolveSchedule(
  kind: string,
  input: SubmissionInput,
  resource: ResourceStock | null,
  at: Date,
): { errors: Errors } | { schedule: Schedule } {
  const empty: Schedule = { quantity: null, neededBy: null, reservation: null };

  if (kind === KIND_BOOKING) {
    // A facility is reserved for a specific window on a single day.
    const date = (input.booking_date ?? '').trim();

    if (date === '') {
      return { errors: { booking_date: 'Please choose a date.' } };
    }

    const startTime = (input.start_time ?? '').trim();
    const endTime = (input.end_time ?? '').trim();

    if (!/^\d{2}:\d{2}$/.test(startTime) || !/^\d{2}:\d{2}$/.test(endTime)) {
      return { errors: { start_time: 'Please give a start and end time.' } };
    }

    const starts = parseNaive(`${date} ${startTime}`);
    const ends = parseNaive(`${date} ${endTime}`);

    if (!starts || !ends) {
      return { errors: { booking_date: 'That date or time could not be read.' } };
    }

    if (ends <= starts) {
      return { errors: { end_time: 'The end time must be after the start time.' } };
    }

    if (starts.getTime() < at.getTime()) {
      return {
        errors: { start_time: 'That start time has already passed. Please choose a later time.' },
      };
    }

    return { schedule: { ...empty, reservation: { starts_at: starts, ends_at: ends } } };
  }

  if (kind === KIND_EQUIPMENT) {
    // Equipment is borrowed in a quantity from a pickup date to a return date.
    // Items are shared stock, so there is no exclusive conflict — staff confirm
    // availability when approving.
    const asked = parseQuantity(input.quantity);

    if (asked === null) {
      return { errors: { quantity: 'Please enter how many you need (at least 1).' } };
    }

    const starts = parseNaive((input.needed_date ?? '').trim());

    if (!starts || isBeforeToday(starts, at)) {
      return { errors: { needed_date: 'Please choose today or a future date.' } };
    }

    const ends = parseNaive((input.return_date ?? '').trim());

    if (!ends || ends < starts) {
      return { errors: { return_date: 'The return date cannot be before the pickup date.' } };
    }

    const stock = serviceableUnits(resource);

    if (stock > 0 && asked > stock) {
      return {
        errors: {
          quantity: `The office has ${stock} ${resource!.name} in total. Please ask for ${stock} or fewer.`,
        },
      };
    }

    // Pickup opens at 08:00, return closes at 17:00 — the counter's hours, which
    // is what the lending desk shows and what overdue is measured against.
    return {
      schedule: {
        quantity: asked,
        neededBy: toDateString(starts),
        reservation: {
          starts_at: atHour(starts, 8),
          ends_at: atHour(ends, 17),
        },
      },
    };
  }

  if (kind === KIND_SERVICE) {
    // Service/production request (lei making, a layout, a social media post):
    // done by a due date. A quantity is optional — the paper form's QTY/SIZE is
    // blank for most CTCAO services — but when given it must be a real count.
    // No resource is reserved; it runs the normal workflow.
    const raw = (input.quantity ?? '').trim();
    const asked = raw === '' ? null : parseQuantity(raw);

    if (raw !== '' && asked === null) {
      return { errors: { quantity: 'Please enter a whole number (at least 1), or leave it blank.' } };
    }

    const due = parseNaive((input.needed_by ?? '').trim());

    if (!due || isBeforeToday(due, at)) {
      return { errors: { needed_by: 'Please choose today or a future date.' } };
    }

    return { schedule: { quantity: asked, neededBy: toDateString(due), reservation: null } };
  }

  return { schedule: empty };
}

function parseQuantity(raw: string | undefined): number | null {
  const asked = Number.parseInt((raw ?? '').trim(), 10);

  return Number.isInteger(asked) && asked >= 1 && asked <= 100000 ? asked : null;
}

/** Date-only comparison, so "today" is never rejected for being earlier than now. */
function isBeforeToday(date: Date, at: Date): boolean {
  return toDateString(date) < toDateString(at);
}

function atHour(date: Date, hour: number): Date {
  const shifted = new Date(date.getTime());
  shifted.setUTCHours(hour, 0, 0, 0);

  return shifted;
}
