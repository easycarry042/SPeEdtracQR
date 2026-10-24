import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Actor } from '@/lib/auth/actor';

/**
 * Port of `DocumentNudgeTest` and the Notify half of `AdminRequestsPageTest`.
 *
 * The properties worth guarding are who a nudge reaches and who may send one.
 * It used to ring every supervisor in the city, so one reminder about a GSO
 * request rang ten offices' bells — and a nudge must never read as, or become,
 * a reassignment.
 */
const findFirst = vi.fn();
const notifyMany = vi.fn();
const logOnDocument = vi.fn();
const usersWithRole = vi.fn();

vi.mock('@/lib/db', () => ({
  db: { documents: { findFirst } },
  toId: (value: bigint | number) => Number(value),
  id: (value: number) => BigInt(value),
}));

vi.mock('@/lib/domain/notifications', () => ({ notifyMany }));
vi.mock('@/lib/activity', () => ({ logOnDocument, logActivity: vi.fn() }));
vi.mock('@/lib/auth/rbac', () => ({
  ROLE_SUPERVISOR: 'Supervisor',
  ROLE_SUPER_ADMIN: 'super_admin',
  usersWithRole,
}));

const { canNudge, nudge } = await import('@/lib/domain/nudge');
const { ActionError } = await import('@/lib/domain/status-actions');

function actor(overrides: Partial<Actor> = {}): Actor {
  return {
    id: 1,
    name: 'Ava Admin',
    email: 'ava@example.test',
    departmentId: null,
    departmentName: null,
    can: () => true,
    hasRole: () => true,
    ...overrides,
  } as unknown as Actor;
}

const superAdmin = actor();

const supervisorOf = (departmentId: number, id = 9): Actor =>
  actor({ id, name: 'Sal Supervisor', departmentId, can: () => false });

function documentRow(overrides: Record<string, unknown> = {}) {
  return {
    id: 7n,
    tracking_number: 'SPD-20261022-ABC123',
    document_type: 'Business Permit',
    department_id: 3n,
    assigned_to: null,
    assignee: null,
    department: { name: 'Accounting Office' },
    ...overrides,
  };
}

beforeEach(() => {
  vi.clearAllMocks();
  findFirst.mockResolvedValue(documentRow());
  usersWithRole.mockResolvedValue([]);
});

describe('canNudge', () => {
  it('lets a super admin nudge about any office', () => {
    expect(canNudge(superAdmin, { department_id: 3 })).toBe(true);
    expect(canNudge(superAdmin, { department_id: null })).toBe(true);
  });

  it('limits a supervisor to their own office', () => {
    expect(canNudge(supervisorOf(3), { department_id: 3 })).toBe(true);
    expect(canNudge(supervisorOf(3), { department_id: 4 })).toBe(false);
    // Unrouted work belongs to no office, so no supervisor owns it either.
    expect(canNudge(supervisorOf(3), { department_id: null })).toBe(false);
  });

  it('refuses a supervisor with no office of their own', () => {
    expect(canNudge(actor({ departmentId: null, can: () => false }), { department_id: 3 })).toBe(
      false,
    );
  });

  it('refuses a signed-out visitor', () => {
    expect(canNudge(null, { department_id: 3 })).toBe(false);
  });
});

describe('nudge', () => {
  it('notifies the assignee when the request has one, and nobody else', async () => {
    findFirst.mockResolvedValue(
      documentRow({ assigned_to: 22n, assignee: { id: 22n, name: 'Rico Staff' } }),
    );

    const result = await nudge(7, superAdmin, 'Please prioritise this.');

    expect(notifyMany).toHaveBeenCalledTimes(1);
    expect(notifyMany.mock.calls[0][0]).toEqual([22]);
    expect(usersWithRole).not.toHaveBeenCalled();
    expect(result.message).toBe('Notified Rico Staff about SPD-20261022-ABC123.');
  });

  it('carries the note and the tracking number into the bell payload', async () => {
    findFirst.mockResolvedValue(
      documentRow({ assigned_to: 22n, assignee: { id: 22n, name: 'Rico Staff' } }),
    );

    await nudge(7, superAdmin, '  Please prioritise this.  ');

    const payload = notifyMany.mock.calls[0][1];

    expect(payload.event).toBe('nudge');
    expect(payload.tracking_number).toBe('SPD-20261022-ABC123');
    expect(payload.body).toContain('SPD-20261022-ABC123');
    expect(payload.body).toContain('Ava Admin asked you to follow up.');
    expect(payload.body).toContain('Note: Please prioritise this.');
    // The bell has to land on the request, not on a dashboard.
    expect(payload.url).toContain('SPD-20261022-ABC123');
    expect(logOnDocument).toHaveBeenCalledWith(
      7,
      'Nudged the responsible staff about an overdue document',
      1,
      { note: 'Please prioritise this.' },
    );
  });

  it('falls back to the office supervisors when nobody is assigned, minus the sender', async () => {
    usersWithRole.mockResolvedValue([
      { id: 9, name: 'Sal Supervisor', email: 's@example.test', department_id: 3 },
      { id: 1, name: 'Ava Admin', email: 'ava@example.test', department_id: 3 },
    ]);

    const result = await nudge(7, superAdmin);

    expect(usersWithRole).toHaveBeenCalledWith('Supervisor', 3);
    // The sender is excluded — belling yourself about your own reminder is noise.
    expect(notifyMany.mock.calls[0][0]).toEqual([9]);
    expect(result.message).toBe(
      'Notified the Accounting Office supervisors about SPD-20261022-ABC123.',
    );
  });

  it('sends nothing when the office has nobody to notify yet', async () => {
    const result = await nudge(7, superAdmin);

    expect(notifyMany).not.toHaveBeenCalled();
    expect(logOnDocument).not.toHaveBeenCalled();
    expect(result.message).toBe('No one is available to notify for this document yet.');
  });

  it('refuses a supervisor nudging another office, and sends nothing', async () => {
    await expect(nudge(7, supervisorOf(4))).rejects.toThrow(ActionError);

    expect(notifyMany).not.toHaveBeenCalled();
    expect(logOnDocument).not.toHaveBeenCalled();
  });

  it('refuses a signed-out caller before it even loads the request', async () => {
    await expect(nudge(7, null)).rejects.toThrow(ActionError);

    expect(findFirst).not.toHaveBeenCalled();
  });

  it('refuses a note longer than the field allows', async () => {
    findFirst.mockResolvedValue(
      documentRow({ assigned_to: 22n, assignee: { id: 22n, name: 'Rico Staff' } }),
    );

    await expect(nudge(7, superAdmin, 'x'.repeat(281))).rejects.toThrow(
      'Keep the note to 280 characters.',
    );

    expect(notifyMany).not.toHaveBeenCalled();
  });

  it('refuses a request that no longer exists', async () => {
    findFirst.mockResolvedValue(null);

    await expect(nudge(7, superAdmin)).rejects.toThrow('That request no longer exists.');
  });

  it('names the supervisors generically when the request reached no office', async () => {
    findFirst.mockResolvedValue(documentRow({ department_id: null, department: null }));
    usersWithRole.mockResolvedValue([
      { id: 9, name: 'Sal Supervisor', email: 's@example.test', department_id: 3 },
    ]);

    const result = await nudge(7, superAdmin);

    // No office id at all: every supervisor is a candidate triager.
    expect(usersWithRole).toHaveBeenCalledWith('Supervisor');
    expect(result.message).toBe('Notified the supervisors about SPD-20261022-ABC123.');
  });
});
