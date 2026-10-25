import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Actor } from '@/lib/auth/actor';
import { DocumentStatus } from '@/lib/domain/document-status';

/**
 * The citizen-request flow: the request reaches a staff member (auto-assigned
 * or claimed from the office pool), they accept it ("notify the admin, change
 * status to ongoing"), do the work and submit it for supervisor review. Only
 * the supervisor's confirmation makes it Ready for Claim (clearance-roadmap).
 */
const findFirst = vi.fn();
const update = vi.fn();
const validate = vi.fn();
const postComment = vi.fn();
const notifyOfficeOfProgress = vi.fn();
const queueMail = vi.fn();
const notifyMany = vi.fn();
const usersWithRole = vi.fn();
const unverifiedMandatoryRequirements = vi.fn();

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
vi.mock('@/lib/domain/notifications', () => ({ notifyMany }));
vi.mock('@/lib/auth/rbac', async () => ({
  ...(await vi.importActual<typeof import('@/lib/auth/rbac')>('@/lib/auth/rbac')),
  usersWithRole,
}));
vi.mock('@/lib/domain/document', async () => ({
  ...(await vi.importActual<typeof import('@/lib/domain/document')>('@/lib/domain/document')),
  unverifiedMandatoryRequirements,
}));

const { acceptAssignment, submitForClearance } = await import('@/lib/domain/review-actions');

const ana = {
  id: 41,
  name: 'Ana Cruz',
  can: (permission: string) => permission === 'advance documents',
  hasRole: () => false,
} as unknown as Actor;

const supervisor = {
  id: 41,
  name: 'Elena Marquez',
  can: (permission: string) => permission === 'advance documents' || permission === 'assign documents',
  hasRole: (role: string) => role === 'Supervisor',
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
  unverifiedMandatoryRequirements.mockResolvedValue([]);
  usersWithRole.mockResolvedValue([
    { id: 2, name: 'Maria Santos', email: 'maria.santos@speedtraqr.com', department_id: 8 },
  ]);
});

describe('Submit for supervisor review (Ongoing → Pending Approval)', () => {
  it('sends the finished work to the office supervisors, keeping the note', async () => {
    findFirst.mockResolvedValue(request(DocumentStatus.InProgress, { supervisor_notes: 'Fix the date' }));

    const result = await submitForClearance(30, ana, ' 50 leis delivered to the lobby ');

    expect(validate).toHaveBeenCalledWith({ id: 30 }, DocumentStatus.InReview, '50 leis delivered to the lobby');
    expect(postComment).toHaveBeenCalledWith(
      expect.objectContaining({ body: '50 leis delivered to the lobby', visibility: 'internal' }),
    );
    // Moves to Pending Approval, and answers the supervisor's earlier correction note.
    expect(update).toHaveBeenCalledWith(
      expect.objectContaining({
        data: expect.objectContaining({ status: DocumentStatus.InReview, supervisor_notes: null }),
      }),
    );
    expect(notifyMany).toHaveBeenCalledWith(
      [2],
      expect.objectContaining({ event: 'clearance_requested', title: 'Clearance requested: SPD-20261024-LEI7MK' }),
    );
    expect(result.message).toBe('Submitted SPD-20261024-LEI7MK for supervisor clearance.');
  });

  it('waits for the required files to be checked', async () => {
    findFirst.mockResolvedValue(request(DocumentStatus.InProgress));
    unverifiedMandatoryRequirements.mockResolvedValue([{ id: 1, label: 'Letter of Request' }]);

    await expect(submitForClearance(30, ana)).rejects.toThrow('still to check: Letter of Request');
    expect(update).not.toHaveBeenCalled();
    expect(notifyMany).not.toHaveBeenCalled();
  });

  it('refuses a request nobody has accepted yet', async () => {
    findFirst.mockResolvedValue(request(DocumentStatus.Pending));

    await expect(submitForClearance(30, ana)).rejects.toThrow('cannot be submitted for clearance');
    expect(update).not.toHaveBeenCalled();
  });

  it("is only for the assignee or an admin", async () => {
    findFirst.mockResolvedValue(request(DocumentStatus.InProgress, { assigned_to: 99n }));

    await expect(submitForClearance(30, ana)).rejects.toThrow(
      'Only the assigned staff member or an admin can act on this request.',
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
