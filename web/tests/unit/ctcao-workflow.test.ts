import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Actor } from '@/lib/auth/actor';
import { DocumentStatus } from '@/lib/domain/document-status';

/**
 * CTCAO's REQUEST ASSIGNMENT workflow: the S/Admin assigns, the staff member
 * accepts ("notify the admin, change status to ongoing"), does the request,
 * and finishes it ("notify the admin"). Finish request is the one way from
 * Ongoing to Done, and it keeps the checks Approved used to make.
 */
const findFirst = vi.fn();
const update = vi.fn();
const validate = vi.fn();
const postComment = vi.fn();
const notifyOfficeOfProgress = vi.fn();
const queueMail = vi.fn();

vi.mock('@/lib/db', () => ({
  db: { documents: { findFirst, update } },
  toId: (value: bigint | number) => Number(value),
  id: (value: number | bigint) => BigInt(value),
}));
vi.mock('@/lib/domain/status-gate', () => ({
  GateError: class GateError extends Error {},
  assertExpectedStatus: vi.fn(),
  validate,
}));
vi.mock('@/lib/domain/lending-desk', () => ({ followClaimDate: vi.fn(), cancel: vi.fn() }));
vi.mock('@/lib/domain/office-progress', () => ({ notifyOfficeOfProgress }));
vi.mock('@/lib/realtime', () => ({ broadcastStatusUpdated: vi.fn() }));
vi.mock('@/lib/activity', () => ({ logOnDocument: vi.fn() }));
vi.mock('@/lib/mail/send', () => ({ queueMail }));
vi.mock('@/lib/domain/comments', () => ({
  AUTHOR: { Staff: 'staff' },
  VISIBILITY: { Internal: 'internal' },
  logSystemComment: vi.fn(),
  postComment,
}));
vi.mock('@/lib/domain/request-review', () => ({ needsTriage: () => true }));

const { finish } = await import('@/lib/domain/status-actions');
const { acceptAssignment } = await import('@/lib/domain/review-actions');

const ana = {
  id: 41,
  name: 'Ana Cruz',
  can: (permission: string) => permission === 'advance documents',
  hasRole: () => false,
} as unknown as Actor;

function request(status: DocumentStatus, extra: Record<string, unknown> = {}) {
  return {
    id: 30n,
    tracking_number: 'SPD-20261024-LEI7MK',
    document_type: 'Lei Making',
    status,
    status_before_hold: null,
    assigned_to: 41n,
    accepted_at: new Date(),
    department_id: 8n,
    citizen_name: 'Juan Dela Cruz',
    citizen_email: null,
    notify_citizen: false,
    claim_date: null,
    ...extra,
  };
}

beforeEach(() => {
  vi.clearAllMocks();
  validate.mockResolvedValue(undefined);
});

describe('Finish request (Ongoing → Done)', () => {
  it('marks an Ongoing request Done, keeps the note and tells the office', async () => {
    findFirst.mockResolvedValue(request(DocumentStatus.InProgress));

    const result = await finish(30, ana, {
      expectedStatus: DocumentStatus.InProgress,
      note: ' 50 leis delivered to the lobby ',
    });

    expect(validate).toHaveBeenCalledWith({ id: 30 }, DocumentStatus.Completed, '50 leis delivered to the lobby');
    expect(postComment).toHaveBeenCalledWith(
      expect.objectContaining({ body: '50 leis delivered to the lobby', authorType: 'staff', visibility: 'internal' }),
    );
    expect(update).toHaveBeenCalledWith(
      expect.objectContaining({ data: expect.objectContaining({ status: DocumentStatus.Completed }) }),
    );
    expect(notifyOfficeOfProgress).toHaveBeenCalledWith(
      expect.objectContaining({ tracking_number: 'SPD-20261024-LEI7MK', department_id: 8n }),
      ana,
      'finished',
    );
    expect(result).toMatchObject({ status: DocumentStatus.Completed, statusLabel: 'Ready for Claim' });
  });

  it('needs no note', async () => {
    findFirst.mockResolvedValue(request(DocumentStatus.InProgress));

    await finish(30, ana, { expectedStatus: DocumentStatus.InProgress });

    expect(postComment).not.toHaveBeenCalled();
    expect(update).toHaveBeenCalled();
  });

  it('finishes a request left on a retired stage from before the change', async () => {
    findFirst.mockResolvedValue(request(DocumentStatus.Approved));

    await finish(30, ana, { expectedStatus: DocumentStatus.Approved });

    expect(update).toHaveBeenCalledWith(
      expect.objectContaining({ data: expect.objectContaining({ status: DocumentStatus.Completed }) }),
    );
  });

  it('refuses a request nobody has accepted yet', async () => {
    findFirst.mockResolvedValue(request(DocumentStatus.Pending));

    await expect(finish(30, ana, { expectedStatus: DocumentStatus.Pending })).rejects.toThrow(
      'This request has not been accepted yet. It can be finished once it is Ongoing.',
    );
    expect(update).not.toHaveBeenCalled();
  });

  it('stops on the gate (unchecked files, reservation not approved) with nothing written or sent', async () => {
    findFirst.mockResolvedValue(request(DocumentStatus.InProgress));
    validate.mockRejectedValue(new Error('Requirement not met: All required documents checked.'));

    await expect(finish(30, ana, { expectedStatus: DocumentStatus.InProgress, note: 'done' })).rejects.toThrow(
      'Requirement not met',
    );
    expect(postComment).not.toHaveBeenCalled();
    expect(update).not.toHaveBeenCalled();
    expect(notifyOfficeOfProgress).not.toHaveBeenCalled();
  });

  it('is only for the assignee or an admin', async () => {
    findFirst.mockResolvedValue(request(DocumentStatus.InProgress, { assigned_to: 99n }));

    await expect(finish(30, ana, { expectedStatus: DocumentStatus.InProgress })).rejects.toThrow(
      "Only the assigned staff member or an admin can change this document's status.",
    );
  });
});

describe('Accepting an assignment', () => {
  it('moves it to Ongoing and tells the office', async () => {
    findFirst.mockResolvedValue(request(DocumentStatus.Pending, { accepted_at: null }));

    const result = await acceptAssignment(30, ana);

    expect(update).toHaveBeenCalledWith(
      expect.objectContaining({ data: expect.objectContaining({ status: DocumentStatus.InProgress }) }),
    );
    expect(notifyOfficeOfProgress).toHaveBeenCalledWith(
      expect.objectContaining({ tracking_number: 'SPD-20261024-LEI7MK' }),
      ana,
      'accepted',
    );
    expect(result.message).toBe('Accepted SPD-20261024-LEI7MK. It is now Ongoing.');
  });
});
