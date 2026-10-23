import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Actor } from '@/lib/auth/actor';

/**
 * Port of `UserArchiveDeleteTest`, `UserBulkActionTest` and the user half of
 * `DepartmentManagementTest`.
 *
 * The properties worth guarding are the ones that would otherwise lose an
 * account or an audit trail: an admin cannot lock themselves out, a person with
 * document activity cannot be erased, archiving is reversible, and a bulk sweep
 * never touches the person running it.
 */
const userFindFirst = vi.fn();
const userFindMany = vi.fn();
const userCount = vi.fn();
const userCreate = vi.fn();
const userUpdate = vi.fn();
const userUpdateMany = vi.fn();
const userDelete = vi.fn();
const roleFindFirst = vi.fn();
const roleFindMany = vi.fn();
const departmentFindFirst = vi.fn();
const rolesFindMany = vi.fn();
const roleLinkCreate = vi.fn();
const roleLinkDeleteMany = vi.fn();
const documentCount = vi.fn();
const custodyCount = vi.fn();
const stepCount = vi.fn();
const hashPassword = vi.fn();
const staffLoad = vi.fn();

vi.mock('@/lib/db', () => ({
  db: {
    users: {
      findFirst: userFindFirst,
      findMany: userFindMany,
      count: userCount,
      create: userCreate,
      update: userUpdate,
      updateMany: userUpdateMany,
      delete: userDelete,
    },
    roles: { findFirst: roleFindFirst, findMany: roleFindMany },
    departments: { findFirst: departmentFindFirst },
    model_has_roles: {
      findMany: rolesFindMany,
      findFirst: vi.fn().mockResolvedValue(null),
      create: roleLinkCreate,
      deleteMany: roleLinkDeleteMany,
    },
    documents: { count: documentCount },
    document_custody_events: { count: custodyCount },
    request_steps: { count: stepCount },
  },
  toId: (value: bigint | number) => Number(value),
  id: (value: number) => BigInt(value),
}));

vi.mock('@/lib/auth/password', () => ({ hashPassword }));
vi.mock('@/lib/auth/rbac', () => ({ MODEL_TYPE: 'App\\Models\\User' }));
vi.mock('@/lib/domain/staff-load', () => ({ forUsers: staffLoad }));

const {
  archiveUser,
  bulkUsers,
  createUser,
  deleteUser,
  restoreUser,
  toggleUser,
  updateUser,
  userList,
} = await import('@/lib/domain/admin-users');

function actor(overrides: Partial<Actor> = {}): Actor {
  return { id: 1, name: 'Ava Admin', can: () => true, hasRole: () => true, ...overrides } as unknown as Actor;
}

const admin = actor();

const input = {
  name: 'Dept Supervisor',
  email: 'Dept.Supervisor@example.com',
  password: 'password123',
  passwordConfirmation: 'password123',
  role: 'Supervisor',
  departmentId: 3,
};

beforeEach(() => {
  vi.clearAllMocks();
  userFindFirst.mockResolvedValue(null);
  userFindMany.mockResolvedValue([]);
  userCount.mockResolvedValue(0);
  userCreate.mockResolvedValue({ id: 42n });
  roleFindFirst.mockResolvedValue({ id: 2n, name: 'Supervisor' });
  roleFindMany.mockResolvedValue([{ name: 'Supervisor' }, { name: 'staff' }]);
  departmentFindFirst.mockResolvedValue({ id: 3n });
  rolesFindMany.mockResolvedValue([]);
  documentCount.mockResolvedValue(0);
  custodyCount.mockResolvedValue(0);
  stepCount.mockResolvedValue(0);
  hashPassword.mockResolvedValue('$2y$12$hashed');
  staffLoad.mockResolvedValue(new Map());
});

describe('createUser', () => {
  it('lower-cases the email, hashes the password and attaches the office and role', async () => {
    await createUser(input);

    expect(userCreate).toHaveBeenCalledWith(
      expect.objectContaining({
        data: expect.objectContaining({
          name: 'Dept Supervisor',
          email: 'dept.supervisor@example.com',
          password: '$2y$12$hashed',
          is_active: true,
          department_id: 3n,
        }),
      }),
    );

    // One role per account, so the link set is replaced rather than added to.
    expect(roleLinkDeleteMany).toHaveBeenCalled();
    expect(roleLinkCreate).toHaveBeenCalledWith(
      expect.objectContaining({ data: expect.objectContaining({ role_id: 2n, model_id: 42n }) }),
    );
  });

  it('refuses an email an active account already uses', async () => {
    userFindFirst.mockResolvedValue({ id: 8n });

    await expect(createUser(input)).rejects.toThrow('An account already uses that email address.');
    expect(userCreate).not.toHaveBeenCalled();
  });

  it('revives an archived account rather than colliding with its address', async () => {
    // The unique index still holds the address, so a second row is impossible.
    userFindFirst.mockResolvedValueOnce(null).mockResolvedValueOnce({ id: 8n });

    const result = await createUser(input);

    expect(userUpdate).toHaveBeenCalledWith(
      expect.objectContaining({
        where: { id: 8n },
        data: expect.objectContaining({ deleted_at: null, is_active: true }),
      }),
    );
    expect(userCreate).not.toHaveBeenCalled();
    expect(result.message).toContain('restored');
  });

  it('refuses a short password and a mismatched confirmation', async () => {
    await expect(
      createUser({ ...input, password: 'short', passwordConfirmation: 'short' }),
    ).rejects.toThrow('at least 8 characters');

    await expect(
      createUser({ ...input, passwordConfirmation: 'something-else' }),
    ).rejects.toThrow('do not match');

    expect(userCreate).not.toHaveBeenCalled();
  });

  it('refuses an address that is not an address, and a role that does not exist', async () => {
    await expect(createUser({ ...input, email: 'not-an-email' })).rejects.toThrow(
      'does not look right',
    );

    roleFindFirst.mockResolvedValue(null);

    await expect(createUser(input)).rejects.toThrow('Choose a role');
  });

  it('refuses an office that no longer exists', async () => {
    departmentFindFirst.mockResolvedValue(null);

    await expect(createUser(input)).rejects.toThrow('That office no longer exists.');
  });
});

describe('updateUser', () => {
  const existing = {
    id: 5n,
    name: 'Sal Supervisor',
    email: 'sal@example.test',
    is_active: true,
    department_id: 3n,
  };

  it('leaves the password alone when the field is blank', async () => {
    userFindFirst.mockResolvedValueOnce(existing).mockResolvedValueOnce(null);

    await updateUser(5, { ...input, password: null, passwordConfirmation: null });

    const data = userUpdate.mock.calls[0][0].data;

    expect(data.password).toBeUndefined();
    expect(hashPassword).not.toHaveBeenCalled();
  });

  it('clearing the office select detaches the account from any office', async () => {
    userFindFirst.mockResolvedValueOnce(existing).mockResolvedValueOnce(null);

    await updateUser(5, { ...input, password: null, passwordConfirmation: null, departmentId: null });

    expect(userUpdate).toHaveBeenCalledWith(
      expect.objectContaining({ data: expect.objectContaining({ department_id: null }) }),
    );
  });

  it('refuses an account that no longer exists', async () => {
    userFindFirst.mockResolvedValue(null);

    await expect(updateUser(99, input)).rejects.toThrow('no longer exists');
  });
});

describe('the account an admin is signed in as', () => {
  const self = { id: 1n, name: 'Ava Admin', email: 'ava@example.test', is_active: true, department_id: null };

  it('cannot be deactivated, archived or deleted', async () => {
    userFindFirst.mockResolvedValue(self);

    await expect(toggleUser(1, admin)).rejects.toThrow('cannot deactivate your own account');
    await expect(archiveUser(1, admin)).rejects.toThrow('cannot archive your own account');
    await expect(deleteUser(1, admin)).rejects.toThrow('cannot delete your own account');

    expect(userUpdate).not.toHaveBeenCalled();
    expect(userDelete).not.toHaveBeenCalled();
  });
});

describe('archiveUser / restoreUser', () => {
  const target = { id: 5n, name: 'Sal Supervisor', email: 's@example.test', is_active: true, department_id: 3n };

  it('archives as a soft delete, so the row stays and can come back', async () => {
    userFindFirst.mockResolvedValue(target);

    await archiveUser(5, admin);

    const data = userUpdate.mock.calls[0][0].data;

    expect(data.deleted_at).toBeInstanceOf(Date);

    vi.clearAllMocks();
    userFindFirst.mockResolvedValue(target);

    await restoreUser(5);

    expect(userUpdate).toHaveBeenCalledWith(
      expect.objectContaining({ data: expect.objectContaining({ deleted_at: null }) }),
    );
  });
});

describe('deleteUser', () => {
  const target = { id: 5n, name: 'Sal Supervisor', email: 's@example.test', is_active: true, department_id: 3n };

  it('deletes an account with no history at all', async () => {
    userFindFirst.mockResolvedValue(target);

    await deleteUser(5, admin);

    expect(roleLinkDeleteMany).toHaveBeenCalled();
    expect(userDelete).toHaveBeenCalledWith({ where: { id: 5n } });
  });

  it('refuses an account that filed or carried a request', async () => {
    userFindFirst.mockResolvedValue(target);
    documentCount.mockResolvedValue(4);

    await expect(deleteUser(5, admin)).rejects.toThrow('has document activity');
    expect(userDelete).not.toHaveBeenCalled();
  });

  it('refuses one that only ever scanned a folder or decided a step', async () => {
    userFindFirst.mockResolvedValue(target);
    custodyCount.mockResolvedValue(1);

    await expect(deleteUser(5, admin)).rejects.toThrow('Archive the account instead.');

    vi.clearAllMocks();
    userFindFirst.mockResolvedValue(target);
    documentCount.mockResolvedValue(0);
    custodyCount.mockResolvedValue(0);
    stepCount.mockResolvedValue(2);

    await expect(deleteUser(5, admin)).rejects.toThrow('has document activity');
    expect(userDelete).not.toHaveBeenCalled();
  });
});

describe('bulkUsers', () => {
  beforeEach(() => {
    userUpdateMany.mockResolvedValue({ count: 2 });
  });

  it('deactivates several accounts at once', async () => {
    const result = await bulkUsers('deactivate', [5, 6], admin);

    expect(userUpdateMany).toHaveBeenCalledWith(
      expect.objectContaining({
        where: expect.objectContaining({ id: { in: [5n, 6n] }, is_active: true }),
        data: expect.objectContaining({ is_active: false }),
      }),
    );
    expect(result.message).toBe('2 accounts deactivated.');
  });

  it('archives several accounts at once, skipping ones already archived', async () => {
    await bulkUsers('archive', [5, 6], admin);

    expect(userUpdateMany).toHaveBeenCalledWith(
      expect.objectContaining({ where: expect.objectContaining({ deleted_at: null }) }),
    );
  });

  it('drops the acting admin from the selection instead of refusing the sweep', async () => {
    userUpdateMany.mockResolvedValue({ count: 1 });

    await bulkUsers('deactivate', [1, 6], admin);

    expect(userUpdateMany).toHaveBeenCalledWith(
      expect.objectContaining({ where: expect.objectContaining({ id: { in: [6n] } }) }),
    );
  });

  it('refuses a selection of nothing but the acting admin', async () => {
    await expect(bulkUsers('deactivate', [1], admin)).rejects.toThrow('No eligible accounts');
    expect(userUpdateMany).not.toHaveBeenCalled();
  });

  it('says so when the accounts were already in that state', async () => {
    userUpdateMany.mockResolvedValue({ count: 0 });

    await expect(bulkUsers('activate', [5, 6], admin)).rejects.toThrow('Nothing changed');
  });

  it('refuses an action it does not recognise', async () => {
    // The union type is a compile-time promise only: this arrives over the wire
    // from the browser, and falling through to deactivate would lock people out.
    await expect(
      bulkUsers('delete' as unknown as 'archive', [5, 6], admin),
    ).rejects.toThrow('not an action that can be applied');

    expect(userUpdateMany).not.toHaveBeenCalled();
  });
});

describe('userList', () => {
  it('keeps archived accounts listed, marked as archived', async () => {
    userCount.mockResolvedValue(1);
    userFindMany.mockResolvedValue([
      {
        id: 5n,
        name: 'Sal Supervisor',
        email: 's@example.test',
        is_active: false,
        deleted_at: new Date('2026-10-01T00:00:00Z'),
        department: { name: 'Accounting Office' },
      },
    ]);

    const listing = await userList({});

    expect(listing.rows[0]).toMatchObject({
      id: 5,
      isArchived: true,
      isActive: false,
      office: 'Accounting Office',
    });
    expect(listing.total).toBe(1);
  });

  it('searches name and email together', async () => {
    await userList({ search: ' sal ' });

    expect(userFindMany).toHaveBeenCalledWith(
      expect.objectContaining({
        where: expect.objectContaining({
          OR: [{ name: { contains: 'sal' } }, { email: { contains: 'sal' } }],
        }),
      }),
    );
  });

  it('resolves a role filter through Spatie’s pivot', async () => {
    rolesFindMany.mockResolvedValue([{ model_id: 5n }, { model_id: 6n }]);

    await userList({ role: 'Supervisor' });

    expect(userFindMany).toHaveBeenCalledWith(
      expect.objectContaining({ where: expect.objectContaining({ id: { in: [5n, 6n] } }) }),
    );
  });
});
