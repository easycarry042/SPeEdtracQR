import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Actor } from '@/lib/auth/actor';

/**
 * Port of `DashboardCompletedTabTest`.
 *
 * The Completed tab is the whole history, so who sees which rows is the
 * property worth guarding: staff their own finished work, a supervisor their
 * office's, an org-wide admin everything — and internal requests never.
 */
const count = vi.fn();
const findMany = vi.fn();
const collection = vi.fn();

vi.mock('@/lib/db', () => ({
  db: { documents: { count, findMany } },
  toId: (value: bigint | number) => Number(value),
  id: (value: number | bigint) => BigInt(value),
}));
vi.mock('@/lib/domain/request-review', () => ({ REVIEW_SELECT: {}, collection }));

const { completedPage, completedScope, PER_PAGE } = await import('@/lib/domain/completed-history');

function actor(permissions: string[], overrides: Partial<Actor> = {}): Actor {
  return {
    id: 5,
    departmentId: 3,
    can: (permission: string) => permissions.includes(permission),
    hasRole: () => false,
    ...overrides,
  } as unknown as Actor;
}

/** JSON of a Prisma where clause (ids are bigint). */
const json = (value: unknown): string =>
  JSON.stringify(value, (_key, val) => (typeof val === 'bigint' ? Number(val) : val));

const staff = actor(['advance documents']);
const supervisor = actor(['assign documents'], { hasRole: (role: string) => role === 'department_admin' } as Partial<Actor>);
const admin = actor(['assign documents', 'manage system']);

beforeEach(() => {
  vi.clearAllMocks();
  count.mockResolvedValue(0);
  findMany.mockResolvedValue([]);
  collection.mockResolvedValue([]);
});

describe('completedScope', () => {
  it('gives staff only what they handled', () => {
    expect(completedScope(staff)).toMatchObject({ assigned_to: 5n, status: 'completed', deleted_at: null });
  });

  it('gives a supervisor their own office', () => {
    const scope = completedScope(supervisor) as { AND: unknown[] };

    expect(scope.AND).toContainEqual({ department_id: 3n });
  });

  it('gives an org-wide admin every office', () => {
    const scope = completedScope(admin) as { AND: unknown[] };

    expect(scope.AND).not.toContainEqual({ department_id: 3n });
  });

  it('never includes internal requests', () => {
    for (const viewer of [staff, supervisor, admin]) {
      expect(json(completedScope(viewer))).toContain('"origin":{"not":"internal"}');
    }
  });
});

describe('completedPage', () => {
  it('pages ten at a time, newest completion first with undated ones last', async () => {
    count.mockResolvedValue(34);

    const page = await completedPage(staff, 2);

    expect(page).toMatchObject({ total: 34, page: 2, lastPage: 4, perPage: PER_PAGE });
    expect(findMany.mock.calls.at(-1)?.[0]).toMatchObject({
      skip: 10,
      take: 10,
      orderBy: [{ completed_at: { sort: 'desc', nulls: 'last' } }, { id: 'desc' }],
    });
  });

  it('clamps a page past the end to the last one', async () => {
    count.mockResolvedValue(12);

    expect((await completedPage(staff, 9)).page).toBe(2);
    expect((await completedPage(staff, Number.NaN)).page).toBe(1);
  });

  it('searches tracking number, name and type, ignoring case', async () => {
    await completedPage(staff, 1, { search: ' juan ' });

    const where = json(count.mock.calls[0][0].where);
    for (const column of ['tracking_number', 'citizen_name', 'document_type']) {
      expect(where).toContain(`"${column}":{"contains":"juan","mode":"insensitive"}`);
    }
  });

  it('lists the types from the unfiltered scope, so the filter can be widened again', async () => {
    findMany.mockResolvedValueOnce([{ document_type: 'Cedula' }, { document_type: 'Permit' }]);

    const page = await completedPage(staff, 1, { documentType: 'Cedula' });

    expect(page.types).toEqual(['Cedula', 'Permit']);
    expect(json(findMany.mock.calls[0][0].where)).not.toContain('Cedula');
  });
});
