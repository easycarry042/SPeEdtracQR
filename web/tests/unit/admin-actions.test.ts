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

const createUser = vi.fn();
const updateUser = vi.fn();
const toggleUser = vi.fn();
const archiveUser = vi.fn();
const restoreUser = vi.fn();
const deleteUser = vi.fn();
const bulkUsers = vi.fn();

vi.mock('@/lib/domain/admin-users', () => ({
  createUser,
  updateUser,
  toggleUser,
  archiveUser,
  restoreUser,
  deleteUser,
  bulkUsers,
}));

const {
  archiveUserAccount,
  bulkUserAction,
  deleteUserAccount,
  restoreUserAccount,
  saveDepartment,
  saveUser,
  toggleDepartmentActive,
  toggleUserActive,
} = await import('@/app/actions/admin');
const { ActionError } = await import('@/lib/domain/status-actions');

function actor(permissions: string[]): Actor {
  return {
    id: 1,
    name: 'Ava Admin',
    can: (permission: string) => permissions.includes(permission),
    hasRole: () => false,
  } as unknown as Actor;
}

const superAdmin = actor(['manage system', 'manage users']);
const supervisor = actor(['view reports', 'assign documents']);

const office = { name: 'Budget Office', code: 'BO', internalStepHours: null };

const account = {
  name: 'Sal Supervisor',
  email: 'sal@example.test',
  password: 'password123',
  passwordConfirmation: 'password123',
  role: 'Supervisor',
  departmentId: 3,
};

beforeEach(() => {
  vi.clearAllMocks();
  currentActor.mockResolvedValue(superAdmin);
  createDepartment.mockResolvedValue({ message: 'Department Budget Office created successfully.' });
  updateDepartment.mockResolvedValue({ message: 'Department Budget Office updated successfully.' });
  toggleDepartment.mockResolvedValue({ message: 'Department Budget Office has been deactivated.' });

  for (const write of [createUser, updateUser, toggleUser, archiveUser, restoreUser, deleteUser, bulkUsers]) {
    write.mockResolvedValue({ message: 'Done.' });
  }
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

describe('the account writes', () => {
  it('create when given no id and update when given one', async () => {
    await saveUser(null, account);

    expect(createUser).toHaveBeenCalledWith(account);

    await saveUser(5, account);

    expect(updateUser).toHaveBeenCalledWith(5, account);
  });

  it('pass the acting admin through, which is what stops self-lockout', async () => {
    await toggleUserActive(5);
    await archiveUserAccount(5);
    await deleteUserAccount(5);
    await bulkUserAction('archive', [5, 6]);

    expect(toggleUser).toHaveBeenCalledWith(5, superAdmin);
    expect(archiveUser).toHaveBeenCalledWith(5, superAdmin);
    expect(deleteUser).toHaveBeenCalledWith(5, superAdmin);
    expect(bulkUsers).toHaveBeenCalledWith('archive', [5, 6], superAdmin);
  });

  /**
   * `UserBulkActionTest::staff_without_permission_cannot_bulk_act`, at the
   * action rather than the route: every one of these is refused and nothing is
   * written.
   */
  it('are all refused without "manage users"', async () => {
    currentActor.mockResolvedValue(supervisor);

    const results = await Promise.all([
      saveUser(null, account),
      saveUser(5, account),
      toggleUserActive(5),
      archiveUserAccount(5),
      restoreUserAccount(5),
      deleteUserAccount(5),
      bulkUserAction('deactivate', [5, 6]),
    ]);

    expect(results.every((result) => result.ok === false)).toBe(true);

    for (const write of [createUser, updateUser, toggleUser, archiveUser, restoreUser, deleteUser, bulkUsers]) {
      expect(write).not.toHaveBeenCalled();
    }
  });

  /** Managing accounts and managing the catalog are separate grants. */
  it('are not unlocked by "manage system" on its own', async () => {
    currentActor.mockResolvedValue(actor(['manage system']));

    expect((await archiveUserAccount(5)).ok).toBe(false);
    expect(archiveUser).not.toHaveBeenCalled();
  });
});
