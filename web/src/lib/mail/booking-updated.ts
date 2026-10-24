import { formatNaive, isSameDay } from '@/lib/time';
import { absoluteUrl, e, layout, type Mailable } from '@/lib/mail/send';
import {
  type BookingCore,
  daysOverdue as bookingDaysOverdue,
  isEquipment,
  units as bookingUnits,
  unitsOutstanding,
} from '@/lib/domain/lending';
import { citizenTrackingUrl } from '@/lib/domain/document';

/**
 * Sent to the citizen when staff act on their booking — approved, rescheduled,
 * cancelled, its return date changed, its items received back, or a reminder
 * that they are overdue. Gated by `config/tracking` plus the per-ticket
 * `notify_citizen` flag (see `notifyCitizen` in the lending rules).
 */
export const OUTCOME = {
  Approved: 'approved',
  Rescheduled: 'rescheduled',
  Cancelled: 'cancelled',
  /** Everything borrowed is back — the loan is closed. */
  Returned: 'returned',
  /** The return date moved — usually the borrower keeping it longer. */
  Extended: 'extended',
  /** Past the return date and not yet brought back. */
  Overdue: 'overdue',
} as const;

export type BookingOutcome = (typeof OUTCOME)[keyof typeof OUTCOME];

const HEADINGS: Record<BookingOutcome, string> = {
  [OUTCOME.Approved]: 'Booking confirmed',
  [OUTCOME.Rescheduled]: 'Booking rescheduled',
  [OUTCOME.Cancelled]: 'Booking cancelled',
  [OUTCOME.Returned]: 'Items received — thank you',
  [OUTCOME.Overdue]: 'Please return what you borrowed',
  [OUTCOME.Extended]: 'New return date',
};

const TONES: Record<BookingOutcome, 'green' | 'warn' | 'danger'> = {
  [OUTCOME.Approved]: 'green',
  [OUTCOME.Rescheduled]: 'warn',
  [OUTCOME.Cancelled]: 'danger',
  [OUTCOME.Returned]: 'green',
  [OUTCOME.Overdue]: 'danger',
  [OUTCOME.Extended]: 'green',
};

/** "Mon, Oct 25, 2026 · 9:00 AM – 5:00 PM", collapsing a same-day window. */
function slot(starts: Date, ends: Date): string {
  const head = formatNaive(starts, 'D, M j, Y · g:i A');

  return isSameDay(starts, ends)
    ? `${head} – ${formatNaive(ends, 'g:i A')}`
    : `${head} – ${formatNaive(ends, 'M j, g:i A')}`;
}

export function bookingUpdatedMail(args: {
  to: string;
  outcome: BookingOutcome;
  booking: BookingCore;
  resource: { name: string; kind: string } | null;
  document: { tracking_number: string; citizen_name: string | null };
}): Mailable {
  const { outcome, booking, resource, document } = args;
  const resourceName = resource?.name ?? 'Reservation';
  const tracking = document.tracking_number;

  // Equipment only: how many were lent, and how many are still out.
  const units = resource !== null && isEquipment(resource) ? bookingUnits(booking) : null;
  const outstanding = unitsOutstanding(booking);
  const overdue = bookingDaysOverdue(booking);

  // The Blade templates said "it" for a single unit or a facility, "them" otherwise.
  const it = units === 1 || !units ? 'it' : 'them';
  const was = units === 1 || !units ? 'was' : 'were';
  const count = units ? `${units.toLocaleString('en-US')} ` : '';
  const outstandingCount = units ? `${outstanding.toLocaleString('en-US')} ` : '';

  const subject = {
    [OUTCOME.Approved]: `Your booking for ${resourceName} is confirmed — ${tracking}`,
    [OUTCOME.Rescheduled]: `Your booking for ${resourceName} was rescheduled — ${tracking}`,
    [OUTCOME.Cancelled]: `Your booking for ${resourceName} was cancelled — ${tracking}`,
    [OUTCOME.Returned]: `We received ${resourceName} back — ${tracking}`,
    [OUTCOME.Extended]: `New return date for ${resourceName}: ${formatNaive(booking.ends_at, 'M j')} — ${tracking}`,
    [OUTCOME.Overdue]: `Please return ${resourceName} — overdue — ${tracking}`,
  }[outcome];

  const greeting = `    <p>Hello ${e(document.citizen_name ?? 'there')},</p>`;
  let body: string;

  switch (outcome) {
    case OUTCOME.Approved:
      body = `${greeting}
    <p>Good news — your reservation for <strong>${e(resourceName)}</strong> has been approved for:</p>
    <div class="slot">${e(slot(booking.starts_at, booking.ends_at))}</div>`;
      break;

    case OUTCOME.Rescheduled:
      body = `${greeting}
    <p>Your reservation for <strong>${e(resourceName)}</strong> has been moved to a new time:</p>
    <div class="slot">${e(slot(booking.starts_at, booking.ends_at))}</div>`;
      break;

    case OUTCOME.Cancelled:
      body = `${greeting}
    <p>Your reservation for <strong>${e(resourceName)}</strong> scheduled on ${e(formatNaive(booking.starts_at, 'D, M j, Y · g:i A'))} has been <span class="cancelled-note">cancelled</span>.</p>
    <p>If this was unexpected, please contact the office or submit a new request.</p>`;
      break;

    case OUTCOME.Returned:
      body = `${greeting}
    <p>We have received ${e(count)}<strong>${e(resourceName)}</strong> back. Your borrowing is now closed — thank you for returning ${it}.</p>`;
      break;

    case OUTCOME.Extended:
      body = `${greeting}
    <p>The return date for ${e(outstandingCount)}<strong>${e(resourceName)}</strong> has been changed. Please bring ${it} back by:</p>
    <div class="slot">${e(formatNaive(booking.ends_at, 'D, M j, Y · g:i A'))}</div>`;
      break;

    case OUTCOME.Overdue:
      body = `${greeting}
    <p>The <strong>${e(outstandingCount)}${e(resourceName)}</strong> you borrowed ${was} due back on:</p>
    <div class="slot">${e(formatNaive(booking.ends_at, 'D, M j, Y · g:i A'))}</div>
    <p class="cancelled-note">${overdue} ${overdue === 1 ? 'day' : 'days'} overdue.</p>
    <p>Please bring ${it} back to the office as soon as you can, or contact the office if you need more time. Other residents are waiting to borrow ${it}.</p>`;
      break;

    default:
      body = greeting;
  }

  return {
    to: args.to,
    subject,
    html: layout({
      heading: HEADINGS[outcome],
      tone: TONES[outcome],
      body,
      cta: { label: 'View details', url: absoluteUrl(citizenTrackingUrl(tracking)) },
    }),
  };
}
