import { beforeEach, describe, expect, it, vi } from 'vitest';
import { DocumentStatus } from '@/lib/domain/document-status';

/**
 * Port of `CheckDocumentSlaTest`, `CheckDocumentHoldsTest` and
 * `CheckOverdueLoansTest`.
 *
 * The properties worth guarding are the dedupe columns and the On Hold
 * exclusion. A sweep that re-mails every hour is worse than one that never
 * runs — the office mutes it — and an On Hold row charged for waiting is the
 * bug the hold sweep exists to avoid double-counting.
 */
const findMany = vi.fn();
const update = vi.fn();
const bookingFindMany = vi.fn();
const bookingUpdate = vi.fn();
const sendMail = vi.fn();
const usersWithRole = vi.fn();
const notifyMany = vi.fn();
const logOnDocument = vi.fn();
const remind = vi.fn();

vi.mock('@/lib/db', () => ({
  db: {
    documents: { findMany, update },
    bookings: { findMany: bookingFindMany, update: bookingUpdate },
  },
  toId: (value: bigint | number) => Number(value),
  id: (value: number) => BigInt(value),
}));

vi.mock('@/lib/mail/send', () => ({
  sendMail,
  queueMail: sendMail,
  e: (v: unknown) => String(v ?? ''),
  layout: () => '<html></html>',
  absoluteUrl: (p: string) => `http://localhost:3000${p}`,
}));

vi.mock('@/lib/auth/rbac', () => ({
  ROLE_SUPERVISOR: 'Supervisor',
  ROLE_SUPER_ADMIN: 'super_admin',
  usersWithRole,
}));

vi.mock('@/lib/domain/notifications', () => ({ notifyMany }));
vi.mock('@/lib/activity', () => ({ logOnDocument, logOnResource: vi.fn(), logActivity: vi.fn() }));
vi.mock('@/lib/domain/lending-desk', () => ({ remind }));

const { checkDocumentSla, checkDocumentHolds, checkOverdueLoans } = await import('@/lib/sweeps');

const hoursAgo = (hours: number) => new Date(Date.now() - hours * 3600_000);
const daysAgo = (days: number) => new Date(Date.now() - days * 86_400_000);

function slaRow(overrides: Record<string, unknown> = {}) {
  return {
    id: 1n,
    tracking_number: 'SPD-20261022-ABC123',
    document_type: 'Business Permit',
    citizen_name: 'Juan Cruz',
    status: DocumentStatus.InProgress, // 72h budget
    status_changed_at: hoursAgo(80),
    sla_warning_notified_at: null,
    sla_breach_notified_at: null,
    sla_breached_at: null,
    assignee: { email: 'staff@example.com' },
    ...overrides,
  };
}

beforeEach(() => {
  for (const fn of [findMany, update, bookingFindMany, bookingUpdate, sendMail, usersWithRole, notifyMany, logOnDocument, remind]) {
    fn.mockReset();
  }
  usersWithRole.mockResolvedValue([]);
  remind.mockResolvedValue(true);
});

describe('checkDocumentSla', () => {
  it('mails a breach once the stage budget is spent, and stamps both columns', async () => {
    findMany.mockResolvedValue([slaRow()]);

    const result = await checkDocumentSla();

    expect(result.counts.breached).toBe(1);
    expect(sendMail).toHaveBeenCalledTimes(1);
    expect(update).toHaveBeenCalledWith(
      expect.objectContaining({
        data: expect.objectContaining({
          sla_breach_notified_at: expect.any(Date),
          sla_breached_at: expect.any(Date),
        }),
      }),
    );
  });

  it('mails a warning at 75% of the budget, not a breach', async () => {
    // 72h budget, 60h elapsed = 83% — past the 75% warning line, under breach.
    findMany.mockResolvedValue([slaRow({ status_changed_at: hoursAgo(60) })]);

    const result = await checkDocumentSla();

    expect(result.counts).toMatchObject({ warned: 1, breached: 0 });
    expect(update).toHaveBeenCalledWith(
      expect.objectContaining({
        data: expect.objectContaining({ sla_warning_notified_at: expect.any(Date) }),
      }),
    );
  });

  it('says nothing below the warning line', async () => {
    findMany.mockResolvedValue([slaRow({ status_changed_at: hoursAgo(10) })]);

    const result = await checkDocumentSla();

    expect(result.counts).toMatchObject({ warned: 0, breached: 0 });
    expect(sendMail).not.toHaveBeenCalled();
  });

  it('does not re-mail a breach already notified — the hourly sweep is idempotent', async () => {
    findMany.mockResolvedValue([slaRow({ sla_breach_notified_at: hoursAgo(2) })]);

    const result = await checkDocumentSla();

    expect(result.counts.breached).toBe(0);
    expect(sendMail).not.toHaveBeenCalled();
  });

  it('does not send a warning after a breach has already gone out', async () => {
    findMany.mockResolvedValue([
      slaRow({ status_changed_at: hoursAgo(60), sla_breach_notified_at: hoursAgo(2) }),
    ]);

    expect((await checkDocumentSla()).counts).toMatchObject({ warned: 0, breached: 0 });
  });

  it('keeps the original sla_breached_at when one is already set', async () => {
    const first = daysAgo(5);
    findMany.mockResolvedValue([slaRow({ sla_breached_at: first })]);

    await checkDocumentSla();

    expect(update).toHaveBeenCalledWith(
      expect.objectContaining({ data: expect.objectContaining({ sla_breached_at: first }) }),
    );
  });

  it('skips On Hold — the hold pauses the clock by design', async () => {
    findMany.mockResolvedValue([
      slaRow({ status: DocumentStatus.OnHold, status_changed_at: hoursAgo(1000) }),
    ]);

    expect((await checkDocumentSla()).counts).toMatchObject({ warned: 0, breached: 0 });
    expect(sendMail).not.toHaveBeenCalled();
  });

  it('skips terminal stages, however long they have sat', async () => {
    for (const status of [
      DocumentStatus.Completed,
      DocumentStatus.Denied,
      DocumentStatus.Withdrawn,
    ]) {
      findMany.mockResolvedValue([slaRow({ status, status_changed_at: hoursAgo(5000) })]);
      expect((await checkDocumentSla()).counts.breached, status).toBe(0);
    }
  });

  it('skips an assignee with no mailbox rather than crashing the sweep', async () => {
    findMany.mockResolvedValue([slaRow({ assignee: null })]);

    expect((await checkDocumentSla()).counts.breached).toBe(0);
  });
});

describe('checkDocumentHolds', () => {
  function holdRow(overrides: Record<string, unknown> = {}) {
    return {
      id: 7n,
      tracking_number: 'SPD-20261022-HOLD01',
      document_type: 'Building Permit',
      citizen_name: 'Ana Cruz',
      status: DocumentStatus.OnHold,
      hold_until: daysAgo(3),
      held_at: daysAgo(10),
      hold_reminder_sent_at: null,
      blocked_by: 'citizen',
      department_id: 2n,
      assigned_to: 5n,
      assignee: { email: 'staff@example.com', department_id: 2n },
      ...overrides,
    };
  }

  it('chases a hold past its own hold date, and stamps the reminder column', async () => {
    findMany.mockResolvedValue([holdRow()]);

    const result = await checkDocumentHolds();

    expect(result.counts).toMatchObject({ stalled: 1, chased: 1 });
    expect(sendMail).toHaveBeenCalled();
    expect(update).toHaveBeenCalledWith(
      expect.objectContaining({
        data: expect.objectContaining({ hold_reminder_sent_at: expect.any(Date) }),
      }),
    );
  });

  it('leaves a hold still inside its date alone', async () => {
    findMany.mockResolvedValue([
      holdRow({ hold_until: new Date(Date.now() + 5 * 86_400_000) }),
    ]);

    expect((await checkDocumentHolds()).counts).toMatchObject({ stalled: 0, chased: 0 });
  });

  it('waits out the re-nag window instead of mailing daily', async () => {
    // Default remind cadence is 7 days; chased 2 days ago.
    findMany.mockResolvedValue([holdRow({ hold_reminder_sent_at: daysAgo(2) })]);

    const result = await checkDocumentHolds();

    // Still counted as stalled — it just is not chased again yet.
    expect(result.counts).toMatchObject({ stalled: 1, chased: 0 });
    expect(sendMail).not.toHaveBeenCalled();
  });

  it('escalates to the office supervisor as well as the assignee', async () => {
    usersWithRole.mockResolvedValue([
      { id: 9, name: 'Sup', email: 'sup@example.com', department_id: 2 },
    ]);
    findMany.mockResolvedValue([holdRow()]);

    await checkDocumentHolds();

    const recipients = sendMail.mock.calls.map(([mail]) => mail.to);
    expect(recipients).toContain('staff@example.com');
    expect(recipients).toContain('sup@example.com');
  });

  it('never escalates to the assignee twice', async () => {
    usersWithRole.mockResolvedValue([
      { id: 5, name: 'Self', email: 'staff@example.com', department_id: 2 },
    ]);
    findMany.mockResolvedValue([holdRow()]);

    await checkDocumentHolds();

    expect(sendMail.mock.calls.filter(([m]) => m.to === 'staff@example.com')).toHaveLength(1);
  });

  it('logs the stall when there is nobody at all to tell', async () => {
    findMany.mockResolvedValue([holdRow({ assignee: null, assigned_to: null })]);

    const result = await checkDocumentHolds();

    expect(result.counts.chased).toBe(0);
    expect(sendMail).not.toHaveBeenCalled();
    // The audit trail must still show the stall was detected.
    expect(logOnDocument).toHaveBeenCalledWith(
      7,
      expect.stringContaining('no assignee or supervisor mailbox'),
    );
  });

  it('treats an open-ended hold as overdue only after the stale window', async () => {
    // staleAfterDays defaults to 14.
    findMany.mockResolvedValue([holdRow({ hold_until: null, held_at: daysAgo(20) })]);
    expect((await checkDocumentHolds()).counts.stalled).toBe(1);

    findMany.mockResolvedValue([holdRow({ hold_until: null, held_at: daysAgo(3) })]);
    expect((await checkDocumentHolds()).counts.stalled).toBe(0);
  });
});

describe('checkOverdueLoans', () => {
  function loanRow(overrides: Record<string, unknown> = {}) {
    return {
      id: 3n,
      ends_at: daysAgo(5),
      overdue_reminded_at: null,
      resource: { name: 'Chairs' },
      document: { department_id: 2n, assigned_to: 5n, tracking_number: 'SPD-20261022-LOAN01' },
      ...overrides,
    };
  }

  it('reminds the borrower and bells the staff', async () => {
    bookingFindMany.mockResolvedValue([loanRow()]);

    const result = await checkOverdueLoans();

    expect(result.counts).toMatchObject({ overdue: 1, chased: 1 });
    // `remind` owns the citizen email and the overdue_reminded_at stamp.
    expect(remind).toHaveBeenCalledWith(3);
    expect(notifyMany).toHaveBeenCalledWith([5], expect.objectContaining({ event: 'loan_overdue' }));
  });

  it('waits out the re-remind cadence', async () => {
    // Default is every 3 days; reminded yesterday.
    bookingFindMany.mockResolvedValue([loanRow({ overdue_reminded_at: daysAgo(1) })]);

    const result = await checkOverdueLoans();

    expect(result.counts).toMatchObject({ overdue: 1, chased: 0 });
    expect(remind).not.toHaveBeenCalled();
  });

  it('re-reminds once the cadence has elapsed', async () => {
    bookingFindMany.mockResolvedValue([loanRow({ overdue_reminded_at: daysAgo(4) })]);

    expect((await checkOverdueLoans()).counts.chased).toBe(1);
  });

  it('bells the office supervisors alongside the assignee', async () => {
    usersWithRole.mockResolvedValue([
      { id: 9, name: 'Sup', email: 'sup@example.com', department_id: 2 },
    ]);
    bookingFindMany.mockResolvedValue([loanRow()]);

    await checkOverdueLoans();

    expect(notifyMany).toHaveBeenCalledWith([5, 9], expect.anything());
  });

  it('reports nothing to do on an empty shelf', async () => {
    bookingFindMany.mockResolvedValue([]);

    expect((await checkOverdueLoans()).counts).toMatchObject({ overdue: 0, chased: 0 });
  });
});
