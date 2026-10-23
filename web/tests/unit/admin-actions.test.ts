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

const createRequestType = vi.fn();
const updateRequestType = vi.fn();
const toggleRequestType = vi.fn();
const deleteRequestType = vi.fn();
const createRouteTemplate = vi.fn();
const updateRouteTemplate = vi.fn();
const toggleRouteTemplate = vi.fn();
const deleteRouteTemplate = vi.fn();
const createResource = vi.fn();
const updateResource = vi.fn();
const toggleResource = vi.fn();
const deleteResource = vi.fn();

vi.mock('@/lib/domain/admin-catalog', () => ({
  createRequestType,
  updateRequestType,
  toggleRequestType,
  deleteRequestType,
  createRouteTemplate,
  updateRouteTemplate,
  toggleRouteTemplate,
  deleteRouteTemplate,
  createResource,
  updateResource,
  toggleResource,
  deleteResource,
}));

const {
  archiveUserAccount,
  bulkUserAction,
  deleteUserAccount,
  removeRequestType,
  removeResource,
  removeRouteTemplate,
  restoreUserAccount,
  saveDepartment,
  saveRequestType,
  saveResource,
  saveRouteTemplate,
  saveUser,
  toggleDepartmentActive,
  toggleRequestTypeActive,
  toggleResourceActive,
  toggleRouteTemplateActive,
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

  for (const write of [
    createUser,
    updateUser,
    toggleUser,
    archiveUser,
    restoreUser,
    deleteUser,
    bulkUsers,
    createRequestType,
    updateRequestType,
    toggleRequestType,
    deleteRequestType,
    createRouteTemplate,
    updateRouteTemplate,
    toggleRouteTemplate,
    deleteRouteTemplate,
    createResource,
    updateResource,
    toggleResource,
    deleteResource,
  ]) {
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

describe('the catalog writes', () => {
  const requestType = {
    name: 'Chairs Borrowing',
    kind: 'equipment',
    departmentId: 3,
    resourceId: 7,
    description: null,
    requirements: [],
  };

  const template = {
    name: 'Procurement Request',
    description: null,
    steps: [{ stepOrder: 1, departmentId: 3, action: 'Approve request', condition: null }],
  };

  const item = {
    name: 'Monoblock Chairs',
    kind: 'equipment',
    description: null,
    location: null,
    totalQuantity: 100,
    capacity: null,
  };

  it('create when given no id and update when given one', async () => {
    await saveRequestType(null, requestType);
    await saveRequestType(11, requestType);
    await saveRouteTemplate(null, template);
    await saveRouteTemplate(21, template);
    await saveResource(null, item);
    await saveResource(7, item);

    expect(createRequestType).toHaveBeenCalledWith(requestType);
    expect(updateRequestType).toHaveBeenCalledWith(11, requestType);
    expect(createRouteTemplate).toHaveBeenCalledWith(template);
    expect(updateRouteTemplate).toHaveBeenCalledWith(21, template);
    // Resource writes are logged against the item, so they carry the actor.
    expect(createResource).toHaveBeenCalledWith(item, superAdmin);
    expect(updateResource).toHaveBeenCalledWith(7, item, superAdmin);
  });

  /**
   * `RequestTypeAdminTest::staff_cannot_manage_request_types`,
   * `RouteTemplateTest::supervisor_cannot_manage_route_templates` and
   * `ResourceAdminTest::staff_cannot_manage_resources`, at the action.
   *
   * A supervisor runs the Resources page every day — they keep the counts —
   * so the catalog half has to be refused on its own.
   */
  it('are all refused without "manage system"', async () => {
    currentActor.mockResolvedValue(supervisor);

    const results = await Promise.all([
      saveRequestType(null, requestType),
      toggleRequestTypeActive(11),
      removeRequestType(11),
      saveRouteTemplate(null, template),
      toggleRouteTemplateActive(21),
      removeRouteTemplate(21),
      saveResource(null, item),
      toggleResourceActive(7),
      removeResource(7),
    ]);

    expect(results.every((result) => result.ok === false)).toBe(true);

    for (const write of [
      createRequestType,
      toggleRequestType,
      deleteRequestType,
      createRouteTemplate,
      toggleRouteTemplate,
      deleteRouteTemplate,
      createResource,
      toggleResource,
      deleteResource,
    ]) {
      expect(write).not.toHaveBeenCalled();
    }
  });

  it('refuse a delete the domain will not allow, as a sentence', async () => {
    deleteResource.mockRejectedValue(
      new ActionError('Monoblock Chairs has 2 live bookings, so it cannot be deleted.'),
    );

    expect(await removeResource(7)).toEqual({
      ok: false,
      message: 'Monoblock Chairs has 2 live bookings, so it cannot be deleted.',
    });
  });
});
