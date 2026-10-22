import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Actor } from '@/lib/auth/actor';

/**
 * The Setup group's permission gate, checked at the action rather than only at
 * the page.
 *
 * This is the half of `DepartmentManagementTest::supervisor_cannot_manage_departments`
 * that a page-level check cannot cover: a server action is a POST endpoint of
 * its own, so a supervisor who never sees the page can still call it. Nothing
 * may be written in that case — the Laravel version answered 403 and left the
 * table untouched.
 */
const currentActor = vi.fn();
const createDepartment = vi.fn();
const updateDepartment = vi.fn();
const toggleDepartment = vi.fn();

vi.mock('next/cache', () => ({ revalidatePath: vi.fn() }));

vi.mock('@/lib/auth/actor', () => ({
  currentActor,
  AuthError: class AuthError extends Error {},
}));

vi.mock('@/lib/domain/admin-departments', () => ({
  createDepartment,
  updateDepartment,
  toggleDepartment,
}));

const { saveDepartment, toggleDepartmentActive } = await import('@/app/actions/admin');
const { ActionError } = await import('@/lib/domain/status-actions');

function actor(permissions: string[]): Actor {
  return {
    id: 1,
    name: 'Ava Admin',
    can: (permission: string) => permissions.includes(permission),
    hasRole: () => false,
  } as unknown as Actor;
}

const superAdmin = actor(['manage system']);
const supervisor = actor(['view reports', 'assign documents']);

const office = { name: 'Budget Office', code: 'BO', internalStepHours: null };

beforeEach(() => {
  vi.clearAllMocks();
  currentActor.mockResolvedValue(superAdmin);
  createDepartment.mockResolvedValue({ message: 'Department Budget Office created successfully.' });
  updateDepartment.mockResolvedValue({ message: 'Department Budget Office updated successfully.' });
  toggleDepartment.mockResolvedValue({ message: 'Department Budget Office has been deactivated.' });
});

describe('saveDepartment', () => {
  it('creates when given no id and updates when given one', async () => {
    await saveDepartment(null, office);

    expect(createDepartment).toHaveBeenCalledWith(office);
    expect(updateDepartment).not.toHaveBeenCalled();

    await saveDepartment(3, office);

    expect(updateDepartment).toHaveBeenCalledWith(3, office);
  });

  it('writes nothing for a supervisor', async () => {
    currentActor.mockResolvedValue(supervisor);

    const result = await saveDepartment(null, office);

    expect(result).toEqual({ ok: false, message: 'This action is unauthorized.' });
    expect(createDepartment).not.toHaveBeenCalled();
  });

  it('writes nothing for a guest', async () => {
    currentActor.mockResolvedValue(null);

    const result = await saveDepartment(null, office);

    expect(result.ok).toBe(false);
    expect(createDepartment).not.toHaveBeenCalled();
  });

  it('returns a refusal as a sentence rather than throwing', async () => {
    createDepartment.mockRejectedValue(new ActionError('The code BO is already taken.'));

    expect(await saveDepartment(null, office)).toEqual({
      ok: false,
      message: 'The code BO is already taken.',
    });
  });

  it('does not leak an unexpected failure to the page', async () => {
    const logged = vi.spyOn(console, 'error').mockImplementation(() => {});
    createDepartment.mockRejectedValue(new Error('P2002 unique constraint on departments.code'));

    const result = await saveDepartment(null, office);

    expect(result).toEqual({
      ok: false,
      message: 'Something went wrong. Nothing was saved.',
    });
    expect(logged).toHaveBeenCalled();

    logged.mockRestore();
  });
});

describe('toggleDepartmentActive', () => {
  it('toggles for a super admin', async () => {
    const result = await toggleDepartmentActive(3);

    expect(toggleDepartment).toHaveBeenCalledWith(3);
    expect(result.ok).toBe(true);
  });

  it('is refused for a supervisor', async () => {
    currentActor.mockResolvedValue(supervisor);

    expect((await toggleDepartmentActive(3)).ok).toBe(false);
    expect(toggleDepartment).not.toHaveBeenCalled();
  });
});
