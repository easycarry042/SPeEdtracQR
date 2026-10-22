import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * Port of `DepartmentManagementTest` (the department half; the user half rides
 * with the Users page).
 *
 * The properties worth guarding are the ones a second office would quietly
 * break: a code is an identifier, so it is normalised and unique; a name is
 * unique too; and an office is deactivated rather than deleted, because users,
 * route templates and filed requests all point at it.
 */
const departmentFindMany = vi.fn();
const departmentFindFirst = vi.fn();
const departmentCreate = vi.fn();
const departmentUpdate = vi.fn();
const usersWithRole = vi.fn();

vi.mock('@/lib/db', () => ({
  db: {
    departments: {
      findMany: departmentFindMany,
      findFirst: departmentFindFirst,
      create: departmentCreate,
      update: departmentUpdate,
    },
  },
  toId: (value: bigint | number) => Number(value),
  id: (value: number) => BigInt(value),
}));

vi.mock('@/lib/auth/rbac', () => ({
  ROLE_SUPERVISOR: 'Supervisor',
  ROLE_SUPER_ADMIN: 'super_admin',
  usersWithRole,
}));

const { createDepartment, departmentList, toggleDepartment, updateDepartment } = await import(
  '@/lib/domain/admin-departments'
);

function departmentRow(overrides: Record<string, unknown> = {}) {
  return {
    id: 3n,
    name: 'Tourism Office',
    code: 'TRSM',
    is_active: true,
    internal_step_hours: 72,
    _count: { users: 4 },
    request_types: [{ id: 1n }, { id: 2n }],
    ...overrides,
  };
}

beforeEach(() => {
  vi.clearAllMocks();
  departmentFindFirst.mockResolvedValue(null);
  departmentFindMany.mockResolvedValue([departmentRow()]);
  usersWithRole.mockResolvedValue([]);
});

describe('createDepartment', () => {
  it('upper-cases the code, because it is read as an identifier', async () => {
    const result = await createDepartment({
      name: 'Municipal Budget Office',
      code: 'bo',
      internalStepHours: null,
    });

    expect(departmentCreate).toHaveBeenCalledWith(
      expect.objectContaining({
        data: expect.objectContaining({
          name: 'Municipal Budget Office',
          code: 'BO',
          is_active: true,
          internal_step_hours: 72,
        }),
      }),
    );
    expect(result.message).toContain('Municipal Budget Office');
  });

  it('refuses a name that is already taken', async () => {
    departmentFindFirst.mockResolvedValue({ name: 'Tourism Office', code: 'OTHER' });

    await expect(
      createDepartment({ name: 'Tourism Office', code: 'TRSM2', internalStepHours: null }),
    ).rejects.toThrow('An office called Tourism Office already exists.');

    expect(departmentCreate).not.toHaveBeenCalled();
  });

  it('refuses a code that is already taken', async () => {
    departmentFindFirst.mockResolvedValue({ name: 'Another Office', code: 'TRSM' });

    await expect(
      createDepartment({ name: 'New Office', code: 'trsm', internalStepHours: null }),
    ).rejects.toThrow('The code TRSM is already taken.');
  });

  it('refuses a code with spaces or punctuation', async () => {
    await expect(
      createDepartment({ name: 'Budget Office', code: 'B O', internalStepHours: null }),
    ).rejects.toThrow('letters and numbers');

    expect(departmentCreate).not.toHaveBeenCalled();
  });

  it('refuses a blank name', async () => {
    await expect(
      createDepartment({ name: '   ', code: 'BO', internalStepHours: null }),
    ).rejects.toThrow('name of up to 255 characters');
  });

  it('refuses a turnaround outside one hour to thirty days', async () => {
    await expect(
      createDepartment({ name: 'Budget Office', code: 'BO', internalStepHours: 0 }),
    ).rejects.toThrow('between 1 and 720');

    await expect(
      createDepartment({ name: 'Budget Office', code: 'BO', internalStepHours: 721 }),
    ).rejects.toThrow('between 1 and 720');

    await expect(
      createDepartment({ name: 'Budget Office', code: 'BO', internalStepHours: 4.5 }),
    ).rejects.toThrow('whole number');
  });
});

describe('updateDepartment', () => {
  it('renames an office and keeps its turnaround when the field is left empty', async () => {
    departmentFindFirst
      .mockResolvedValueOnce({
        id: 3n,
        name: 'Old Name',
        code: 'OLD',
        is_active: true,
        internal_step_hours: 24,
      })
      .mockResolvedValueOnce(null);

    await updateDepartment(3, { name: 'New Name', code: 'new', internalStepHours: null });

    expect(departmentUpdate).toHaveBeenCalledWith(
      expect.objectContaining({
        data: expect.objectContaining({ name: 'New Name', code: 'NEW', internal_step_hours: 24 }),
      }),
    );
  });

  it('does not count the office itself as a name clash', async () => {
    departmentFindFirst
      .mockResolvedValueOnce({
        id: 3n,
        name: 'Tourism Office',
        code: 'TRSM',
        is_active: true,
        internal_step_hours: 72,
      })
      .mockResolvedValueOnce(null);

    await updateDepartment(3, { name: 'Tourism Office', code: 'TRSM', internalStepHours: 48 });

    // The uniqueness query has to exclude this row, or saving a form with the
    // name untouched would refuse itself.
    expect(departmentFindFirst).toHaveBeenLastCalledWith(
      expect.objectContaining({ where: expect.objectContaining({ NOT: { id: 3n } }) }),
    );
    expect(departmentUpdate).toHaveBeenCalled();
  });

  it('refuses an office that no longer exists', async () => {
    departmentFindFirst.mockResolvedValue(null);

    await expect(
      updateDepartment(99, { name: 'Ghost', code: 'GH', internalStepHours: null }),
    ).rejects.toThrow('no longer exists');
  });
});

describe('toggleDepartment', () => {
  it('deactivates an active office and says so', async () => {
    departmentFindFirst.mockResolvedValue({
      id: 3n,
      name: 'Tourism Office',
      code: 'TRSM',
      is_active: true,
      internal_step_hours: 72,
    });

    const result = await toggleDepartment(3);

    expect(departmentUpdate).toHaveBeenCalledWith(
      expect.objectContaining({ data: expect.objectContaining({ is_active: false }) }),
    );
    expect(result.message).toBe('Department Tourism Office has been deactivated.');
  });

  it('activates an inactive one', async () => {
    departmentFindFirst.mockResolvedValue({
      id: 3n,
      name: 'Tourism Office',
      code: 'TRSM',
      is_active: false,
      internal_step_hours: 72,
    });

    const result = await toggleDepartment(3);

    expect(departmentUpdate).toHaveBeenCalledWith(
      expect.objectContaining({ data: expect.objectContaining({ is_active: true }) }),
    );
    expect(result.message).toBe('Department Tourism Office has been activated.');
  });
});

describe('departmentList', () => {
  it('counts members and active services, and names the office supervisors', async () => {
    usersWithRole.mockResolvedValue([
      { id: 9, name: 'Sal Supervisor', email: 's@example.test', department_id: 3 },
      { id: 10, name: 'Other Office', email: 'o@example.test', department_id: 4 },
    ]);

    const [office] = await departmentList();

    expect(office).toMatchObject({
      id: 3,
      name: 'Tourism Office',
      code: 'TRSM',
      users: 4,
      services: 2,
      internalStepHours: 72,
      supervisors: ['Sal Supervisor'],
    });
  });

  it('leaves the supervisor column empty for an office with none', async () => {
    const [office] = await departmentList();

    // The page turns this into a "No supervisor" warning: requests routed here
    // bell nobody but the super admins.
    expect(office.supervisors).toEqual([]);
  });

  it('falls back to the default turnaround when the column is null', async () => {
    departmentFindMany.mockResolvedValue([departmentRow({ internal_step_hours: null })]);

    const [office] = await departmentList();

    expect(office.internalStepHours).toBe(72);
  });
});
