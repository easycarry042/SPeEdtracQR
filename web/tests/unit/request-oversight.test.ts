import { describe, expect, it } from 'vitest';
import {
  OVERSIGHT_QUEUES,
  type OversightQueue,
  inQueue,
  isClosed,
  matchesSearch,
} from '@/lib/domain/request-oversight';
import { overdueBy } from '@/lib/domain/document';
import { DocumentStatus } from '@/lib/domain/document-status';
import { now } from '@/lib/time';

/**
 * Ports the queue half of `AdminRequestsPageTest` — the five tabs on the super
 * admin's Requests page, and the "+… late" figure beside a row.
 *
 * The tab counts are what the page is read for, so the thing worth guarding is
 * that a request lands in every queue it belongs to and in none it does not: a
 * denied request counted as Open, or an overdue one missing from Overdue, sends
 * someone to chase the wrong work.
 */
type Row = Parameters<typeof inQueue>[0];

function row(overrides: Partial<Row> = {}): Row {
  return {
    status: DocumentStatus.Pending,
    assignedTo: null,
    overSla: null,
    ...overrides,
  };
}

/**
 * Anchors have to be built off the app's own clock, not `Date.now()`: stored
 * timestamps are Manila wall-clock in a naive encoding, so mixing the two
 * shifts every span by eight hours.
 */
const hoursAgo = (hours: number): Date => new Date(now().getTime() - hours * 3_600_000);

const queuesFor = (candidate: Row): OversightQueue[] =>
  OVERSIGHT_QUEUES.filter((queue) => inQueue(candidate, queue.key)).map((queue) => queue.key);

describe('oversight queues', () => {
  it('counts an open unassigned request in both Open and Unassigned', () => {
    expect(queuesFor(row())).toEqual(['open', 'unassigned']);
  });

  it('drops it from Unassigned once somebody owns it', () => {
    expect(queuesFor(row({ status: DocumentStatus.InProgress, assignedTo: 12 }))).toEqual(['open']);
  });

  it('puts a completed request in Completed only, never Open', () => {
    expect(queuesFor(row({ status: DocumentStatus.Completed, assignedTo: 12 }))).toEqual([
      'completed',
    ]);
  });

  it('puts a denied request in Denied only', () => {
    expect(queuesFor(row({ status: DocumentStatus.Denied }))).toEqual(['denied']);
  });

  it('lists an overdue open request in Overdue as well as Open', () => {
    expect(queuesFor(row({ overSla: '3d 2h' }))).toEqual(['open', 'unassigned', 'overdue']);
  });

  it('treats Returned and On Hold as open work, not as closed', () => {
    for (const status of [DocumentStatus.Returned, DocumentStatus.OnHold]) {
      expect(isClosed({ status })).toBe(false);
      expect(inQueue(row({ status, assignedTo: 12 }), 'open')).toBe(true);
    }
  });

  it('counts a withdrawn request as closed, so it never shows as Open', () => {
    // Withdrawn is internal-only and so should not reach this page at all —
    // but if one ever does, "Open" is the one place it must not appear.
    expect(isClosed({ status: DocumentStatus.Withdrawn })).toBe(true);
    expect(queuesFor(row({ status: DocumentStatus.Withdrawn }))).toEqual([]);
  });
});

describe('oversight search', () => {
  const subject = {
    citizenName: 'Juan Dela Cruz',
    trackingNumber: 'SPD-20261022-ABC123',
    documentType: 'Business Permit',
    department: 'General Services Office',
    assignedStaff: 'Rico Staff',
  };

  it('matches every column the table shows, case-insensitively', () => {
    for (const needle of ['juan', 'ABC123', 'business', 'general services', 'rico']) {
      expect(matchesSearch(subject, needle)).toBe(true);
    }
  });

  it('matches everything on an empty or whitespace query', () => {
    expect(matchesSearch(subject, '')).toBe(true);
    expect(matchesSearch(subject, '   ')).toBe(true);
  });

  it('does not match a term that is on no column', () => {
    expect(matchesSearch(subject, 'cedula')).toBe(false);
  });

  it('survives a request with no citizen, office or assignee', () => {
    const bare = {
      citizenName: null,
      trackingNumber: 'SPD-20261022-ABC123',
      documentType: 'Cedula',
      department: null,
      assignedStaff: null,
    };

    expect(matchesSearch(bare, 'abc123')).toBe(true);
    expect(matchesSearch(bare, 'juan')).toBe(false);
  });
});

describe('overdueBy', () => {
  it('reads out how far past the stage budget an open request is', () => {
    // Pending allows 24h.
    expect(overdueBy({ status: DocumentStatus.Pending, status_changed_at: hoursAgo(27) })).toBe(
      '3h',
    );
  });

  it('is null while the request is still inside its budget', () => {
    expect(overdueBy({ status: DocumentStatus.Pending, status_changed_at: hoursAgo(5) })).toBeNull();
  });

  it('never reports a terminal stage as late', () => {
    for (const status of [
      DocumentStatus.Completed,
      DocumentStatus.Denied,
      DocumentStatus.Withdrawn,
    ]) {
      expect(overdueBy({ status, status_changed_at: hoursAgo(900) })).toBeNull();
    }
  });

  it('never reports a held request as late — a hold pauses the clock', () => {
    expect(overdueBy({ status: DocumentStatus.OnHold, status_changed_at: hoursAgo(900) })).toBeNull();
  });

  it('anchors to the stage change, falling back to updated_at then created_at', () => {
    expect(
      overdueBy({
        status: DocumentStatus.Pending,
        status_changed_at: hoursAgo(2),
        updated_at: hoursAgo(99),
        created_at: hoursAgo(99),
      }),
    ).toBeNull();

    expect(
      overdueBy({
        status: DocumentStatus.Pending,
        status_changed_at: null,
        updated_at: hoursAgo(30),
      }),
    ).toBe('6h');

    expect(
      overdueBy({
        status: DocumentStatus.Pending,
        status_changed_at: null,
        updated_at: null,
        created_at: hoursAgo(30),
      }),
    ).toBe('6h');
  });

  it('is null when the request carries no date at all', () => {
    expect(overdueBy({ status: DocumentStatus.Pending })).toBeNull();
  });

  it('agrees with the per-stage budgets, not one global number', () => {
    // In Progress allows 72h, so 30h in is late at Pending but fine here.
    expect(overdueBy({ status: DocumentStatus.Pending, status_changed_at: hoursAgo(30) })).toBe('6h');
    expect(
      overdueBy({ status: DocumentStatus.InProgress, status_changed_at: hoursAgo(30) }),
    ).toBeNull();
  });
});
