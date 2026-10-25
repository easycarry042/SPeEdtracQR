import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Actor } from '@/lib/auth/actor';

/**
 * On Duty / On Leave. Going On Leave hands that person's not-yet-accepted
 * requests on at once (another on-duty usual handler, else the office pool),
 * so a request never waits on someone who is not in.
 */
const findFirst = vi.fn();
const update = vi.fn();
const reclaimUnacceptedRequests = vi.fn();

vi.mock('@/lib/db', () => ({
  db: { users: { findFirst, update } },
  toId: (value: bigint | number) => Number(value),
  id: (value: number | bigint) => BigInt(value),
}));
vi.mock('@/lib/sweeps', () => ({ reclaimUnacceptedRequests }));

const { setUserAvailability } = await import('@/lib/domain/availability');

const ana = {
  id: 41,
  name: 'Ana Cruz',
  departmentId: 8,
  isAvailable: true,
  can: () => false,
  hasRole: () => false,
  isSuperAdmin: () => false,
  isSupervisor: () => false,
} as unknown as Actor;

beforeEach(() => {
  vi.clearAllMocks();
  findFirst.mockResolvedValue({ id: 41n, name: 'Ana Cruz', department_id: 8n, is_available: true });
  reclaimUnacceptedRequests.mockResolvedValue({ job: 'x', summary: '', counts: { reassigned: 1, reclaimedToPool: 1 } });
});

describe('setUserAvailability', () => {
  it('going On Leave passes on their requests still waiting for acceptance', async () => {
    const result = await setUserAvailability(41, false, ana);

    expect(update).toHaveBeenCalledWith(expect.objectContaining({ data: expect.objectContaining({ is_available: false }) }));
    expect(reclaimUnacceptedRequests).toHaveBeenCalledWith(0, { assigneeId: 41, why: 'is on leave' });
    expect(result.message).toBe('You are now marked as On Leave. 2 requests waiting for acceptance were passed on.');
  });

  it('coming back On Duty moves nothing', async () => {
    findFirst.mockResolvedValue({ id: 41n, name: 'Ana Cruz', department_id: 8n, is_available: false });

    const result = await setUserAvailability(41, true, ana);

    expect(reclaimUnacceptedRequests).not.toHaveBeenCalled();
    expect(result.message).toBe('You are now marked as On Duty.');
  });

  it("refuses to change someone else's status from another office", async () => {
    findFirst.mockResolvedValue({ id: 50n, name: 'Leo Pascual', department_id: 3n, is_available: true });

    await expect(setUserAvailability(50, false, ana)).rejects.toThrow('duty status');
    expect(update).not.toHaveBeenCalled();
  });
});
