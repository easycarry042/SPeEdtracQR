import { db, id, toId } from '@/lib/db';
import { now } from '@/lib/time';
import { ActionError } from '@/lib/domain/status-actions';
import { ROLE_SUPERVISOR, usersWithRole } from '@/lib/auth/rbac';
import { DEFAULT_INTERNAL_STEP_HOURS } from '@/lib/domain/department';

/**
 * The office directory: who exists, who triages for them, and how long they
 * get on an internal request.
 *
 * Port of `Admin\DepartmentController`. Offices are deactivated, never deleted
 * — users, route templates and request chains all point at them.
 */
export type DepartmentRow = {
  id: number;
  name: string;
  code: string;
  isActive: boolean;
  internalStepHours: number;
  users: number;
  /** Citizen services this office handles; zero is a routing gap. */
  services: number;
  supervisors: string[];
};

/** An office's turnaround on internal requests: one hour to 30 days. */
const MIN_HOURS = 1;
const MAX_HOURS = 720;

export async function departmentList(): Promise<DepartmentRow[]> {
  const [departments, supervisors] = await Promise.all([
    db.departments.findMany({
      orderBy: { name: 'asc' },
      select: {
        id: true,
        name: true,
        code: true,
        is_active: true,
        internal_step_hours: true,
        _count: { select: { users: true } },
        request_types: { where: { is_active: true }, select: { id: true } },
      },
    }),
    usersWithRole(ROLE_SUPERVISOR),
  ]);

  return departments.map((department) => {
    const office = toId(department.id);

    return {
      id: office,
      name: department.name,
      code: department.code,
      isActive: department.is_active,
      internalStepHours: department.internal_step_hours ?? DEFAULT_INTERNAL_STEP_HOURS,
      users: department._count.users,
      services: department.request_types.length,
      supervisors: supervisors
        .filter((person) => person.department_id === office)
        .map((person) => person.name),
    };
  });
}

export async function departmentById(departmentId: number) {
  const department = await db.departments.findFirst({
    where: { id: id(departmentId) },
    select: { id: true, name: true, code: true, is_active: true, internal_step_hours: true },
  });

  return department === null
    ? null
    : {
        id: toId(department.id),
        name: department.name,
        code: department.code,
        isActive: department.is_active,
        internalStepHours: department.internal_step_hours ?? DEFAULT_INTERNAL_STEP_HOURS,
      };
}

export type DepartmentInput = {
  name: string;
  code: string;
  internalStepHours: number | null;
};

/**
 * The shared field rules. The code is upper-cased on the way in, because it is
 * read as an identifier everywhere it appears (office chips, the chain, the
 * printed slip) and `acc` beside `ACC` reads as two different offices.
 */
async function validate(
  input: DepartmentInput,
  ignoreId: number | null,
): Promise<{ name: string; code: string; internalStepHours: number | null }> {
  const name = input.name.trim();
  const code = input.code.trim().toUpperCase();

  if (name === '' || name.length > 255) {
    throw new ActionError('Give the office a name of up to 255 characters.');
  }

  if (code === '' || code.length > 10 || !/^[A-Z0-9]+$/.test(code)) {
    throw new ActionError('The code is up to 10 letters and numbers, with no spaces.');
  }

  if (
    input.internalStepHours !== null &&
    (!Number.isInteger(input.internalStepHours) ||
      input.internalStepHours < MIN_HOURS ||
      input.internalStepHours > MAX_HOURS)
  ) {
    throw new ActionError(
      `The turnaround is a whole number of hours between ${MIN_HOURS} and ${MAX_HOURS}.`,
    );
  }

  const clash = await db.departments.findFirst({
    where: {
      OR: [{ name }, { code }],
      ...(ignoreId === null ? {} : { NOT: { id: id(ignoreId) } }),
    },
    select: { name: true, code: true },
  });

  if (clash) {
    throw new ActionError(
      clash.name === name
        ? `An office called ${name} already exists.`
        : `The code ${code} is already taken.`,
    );
  }

  return { name, code, internalStepHours: input.internalStepHours };
}

export async function createDepartment(input: DepartmentInput): Promise<{ message: string }> {
  const { name, code, internalStepHours } = await validate(input, null);

  await db.departments.create({
    data: {
      name,
      code,
      is_active: true,
      internal_step_hours: internalStepHours ?? DEFAULT_INTERNAL_STEP_HOURS,
      created_at: now(),
      updated_at: now(),
    },
  });

  return { message: `Department ${name} created successfully.` };
}

export async function updateDepartment(
  departmentId: number,
  input: DepartmentInput,
): Promise<{ message: string }> {
  const existing = await departmentById(departmentId);

  if (!existing) {
    throw new ActionError('That office no longer exists.');
  }

  const { name, code, internalStepHours } = await validate(input, departmentId);

  await db.departments.update({
    where: { id: id(departmentId) },
    data: {
      name,
      code,
      internal_step_hours: internalStepHours ?? existing.internalStepHours,
      updated_at: now(),
    },
  });

  return { message: `Department ${name} updated successfully.` };
}

export async function toggleDepartment(departmentId: number): Promise<{ message: string }> {
  const existing = await departmentById(departmentId);

  if (!existing) {
    throw new ActionError('That office no longer exists.');
  }

  await db.departments.update({
    where: { id: id(departmentId) },
    data: { is_active: !existing.isActive, updated_at: now() },
  });

  return {
    message: `Department ${existing.name} has been ${existing.isActive ? 'deactivated' : 'activated'}.`,
  };
}
