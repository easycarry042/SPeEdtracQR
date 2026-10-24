import { DocumentStatus } from '@/lib/domain/document-status';

const bool = (value: string | undefined, fallback: boolean): boolean =>
  value === undefined || value === '' ? fallback : !['false', '0', 'off', 'no'].includes(value.toLowerCase());

const int = (value: string | undefined, fallback: number): number => {
  const parsed = Number.parseInt(value ?? '', 10);
  return Number.isFinite(parsed) ? parsed : fallback;
};

/** Port of `config/tracking.php` — same env var names, same defaults. */
export const tracking = {
  notifyCitizen: {
    enabled: bool(process.env.TRACKING_NOTIFY_CITIZEN, true),

    /**
     * Per-stage switch for the StatusUpdated email. Stages left false avoid
     * mailing the citizen on every intermediate move.
     */
    stages: {
      [DocumentStatus.Pending]: false,
      [DocumentStatus.InProgress]: true,
      [DocumentStatus.InReview]: false,
      [DocumentStatus.Approved]: true,
      [DocumentStatus.Completed]: true,
      [DocumentStatus.Returned]: true,
      // "Action needed" email when a hold is blocked on the citizen.
      [DocumentStatus.OnHold]: true,
      [DocumentStatus.Denied]: false,
      [DocumentStatus.Withdrawn]: false,
    } as Record<DocumentStatus, boolean>,

    bookings: bool(process.env.TRACKING_NOTIFY_BOOKINGS, true),
  },

  notifyStaffOnAssignment: bool(process.env.TRACKING_NOTIFY_STAFF, true),

  /** Email the office's supervisors when a request is accepted and finished (the bell always rings). */
  notifyOfficeProgress: bool(process.env.TRACKING_NOTIFY_OFFICE_PROGRESS, true),

  holds: {
    enabled: bool(process.env.TRACKING_HOLD_SWEEP, true),
    /** Days an open-ended hold may run before it counts as a stall. */
    staleAfterDays: int(process.env.TRACKING_HOLD_STALE_DAYS, 14),
    /** Re-nag cadence while a hold stays overdue. */
    remindEveryDays: int(process.env.TRACKING_HOLD_REMIND_DAYS, 7),
  },

  loans: {
    enabled: bool(process.env.TRACKING_LOAN_SWEEP, true),
    remindEveryDays: int(process.env.TRACKING_LOAN_REMIND_DAYS, 3),
  },

  publicInternalTracker: {
    /**
     * The client asked for handler names on the internal tracker, while the
     * citizen tracker keeps staff anonymous for legal reasons. False shows
     * offices only.
     */
    showNames: bool(process.env.TRACKING_PUBLIC_INTERNAL_NAMES, true),
  },
} as const;

export const app = {
  name: process.env.APP_NAME ?? 'SPeED TraQR',
  url: (process.env.APP_URL ?? 'http://localhost:3000').replace(/\/$/, ''),
  timezone: 'Asia/Manila',
} as const;
