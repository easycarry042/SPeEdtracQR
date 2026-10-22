import { db, id, toId } from '@/lib/db';
import { now } from '@/lib/time';
import { logActivity, SUBJECT } from '@/lib/activity';
import { ActionError } from '@/lib/domain/status-actions';
import { BOOKING_STATUS, KIND_EQUIPMENT, KIND_FACILITY } from '@/lib/domain/lending';
import type { Actor } from '@/lib/auth/actor';

/**
 * The setup catalog: request types, route templates and resources.
 *
 * Ports `Admin\RequestTypeController`, `Admin\RouteTemplateController` and
 * `Admin\ResourceController`. Three small CRUDs with one shared shape — a row,
 * a replace-the-children list, an activate/deactivate toggle and a delete that
 * is refused when something live points at the row.
 */

// ===== Request types =====

export const REQUEST_KINDS = ['document', 'booking', 'equipment', 'service'] as const;

export type RequestKind = (typeof REQUEST_KINDS)[number];

/** Kinds that reserve or lend a resource, and so must point at one. */
export const RESOURCE_KINDS: string[] = ['booking', 'equipment'];

export type RequirementInput = { label: string; isMandatory: boolean };

export type RequestTypeInput = {
  name: string;
  kind: string;
  departmentId: number | null;
  resourceId: number | null;
  description: string | null;
  requirements: RequirementInput[];
};

export type RequestTypeRow = {
  id: number;
  name: string;
  kind: string;
  office: string | null;
  officeCode: string | null;
  resource: string | null;
  description: string | null;
  isActive: boolean;
  requirements: number;
};

export async function requestTypeList(): Promise<RequestTypeRow[]> {
  const rows = await db.request_types.findMany({
    orderBy: [{ sort_order: 'asc' }, { name: 'asc' }],
    select: {
      id: true,
      name: true,
      kind: true,
      description: true,
      is_active: true,
      department: { select: { name: true, code: true } },
      resource: { select: { name: true } },
      _count: { select: { request_type_requirements: true } },
    },
  });

  return rows.map((row) => ({
    id: toId(row.id),
    name: row.name,
    kind: row.kind,
    office: row.department?.name ?? null,
    officeCode: row.department?.code ?? null,
    resource: row.resource?.name ?? null,
    description: row.description,
    isActive: row.is_active,
    requirements: row._count.request_type_requirements,
  }));
}

export async function requestTypeById(requestTypeId: number) {
  const row = await db.request_types.findFirst({
    where: { id: id(requestTypeId) },
    select: {
      id: true,
      name: true,
      kind: true,
      description: true,
      is_active: true,
      department_id: true,
      resource_id: true,
      request_type_requirements: {
        orderBy: { sort_order: 'asc' },
        select: { id: true, label: true, is_mandatory: true },
      },
    },
  });

  return row === null
    ? null
    : {
        id: toId(row.id),
        name: row.name,
        kind: row.kind,
        description: row.description,
        isActive: row.is_active,
        departmentId: row.department_id === null ? null : toId(row.department_id),
        resourceId: row.resource_id === null ? null : toId(row.resource_id),
        requirements: row.request_type_requirements.map((requirement) => ({
          label: requirement.label,
          isMandatory: requirement.is_mandatory,
        })),
      };
}

async function validateRequestType(
  input: RequestTypeInput,
  ignoreId: number | null,
): Promise<{
  name: string;
  kind: string;
  departmentId: number | null;
  resourceId: number | null;
  description: string | null;
  requirements: RequirementInput[];
}> {
  const name = input.name.trim();

  if (name === '' || name.length > 255) {
    throw new ActionError('Give the request type a name of up to 255 characters.');
  }

  if (!(REQUEST_KINDS as readonly string[]).includes(input.kind)) {
    throw new ActionError('Choose what kind of request this is.');
  }

  const clash = await db.request_types.findFirst({
    where: { name, ...(ignoreId === null ? {} : { NOT: { id: id(ignoreId) } }) },
    select: { id: true },
  });

  if (clash) {
    throw new ActionError(`A request type called ${name} already exists.`);
  }

  if (input.departmentId !== null) {
    const office = await db.departments.findFirst({
      where: { id: id(input.departmentId) },
      select: { id: true },
    });

    if (!office) {
      throw new ActionError('That office no longer exists.');
    }
  }

  let resourceId: number | null = null;

  if (RESOURCE_KINDS.includes(input.kind)) {
    if (input.resourceId === null) {
      throw new ActionError('Choose the facility or item this request reserves.');
    }

    const resource = await db.resources.findFirst({
      where: { id: id(input.resourceId) },
      select: { id: true, name: true, kind: true },
    });

    if (!resource) {
      throw new ActionError('That resource no longer exists.');
    }

    /**
     * A booking type reserves a facility by time; an equipment type lends from
     * a counted pile. Pointing one at the other would run the wrong stock
     * rules on every request it files.
     */
    if (input.kind === 'equipment' && resource.kind !== KIND_EQUIPMENT) {
      throw new ActionError(
        `${resource.name} is a facility. An equipment borrowing needs an equipment item.`,
      );
    }

    if (input.kind === 'booking' && resource.kind === KIND_EQUIPMENT) {
      throw new ActionError(`${resource.name} is equipment. A facility booking needs a facility.`);
    }

    resourceId = toId(resource.id);
  }

  const description = (input.description ?? '').trim();

  if (description.length > 500) {
    throw new ActionError('Keep the description to 500 characters.');
  }

  const requirements = input.requirements
    .map((requirement) => ({
      label: requirement.label.trim(),
      isMandatory: requirement.isMandatory,
    }))
    .filter((requirement) => requirement.label !== '');

  if (requirements.some((requirement) => requirement.label.length > 255)) {
    throw new ActionError('Keep each requirement label to 255 characters.');
  }

  return {
    name,
    kind: input.kind,
    departmentId: input.departmentId,
    resourceId,
    description: description === '' ? null : description,
    requirements,
  };
}

function requirementRows(requirements: RequirementInput[], requestTypeId: bigint) {
  return requirements.map((requirement, index) => ({
    request_type_id: requestTypeId,
    label: requirement.label,
    is_mandatory: requirement.isMandatory,
    sort_order: index,
    created_at: now(),
    updated_at: now(),
  }));
}

export async function createRequestType(input: RequestTypeInput): Promise<{ message: string }> {
  const valid = await validateRequestType(input, null);

  const highest = await db.request_types.aggregate({ _max: { sort_order: true } });

  const created = await db.request_types.create({
    data: {
      name: valid.name,
      kind: valid.kind,
      department_id: valid.departmentId === null ? null : id(valid.departmentId),
      resource_id: valid.resourceId === null ? null : id(valid.resourceId),
      description: valid.description,
      is_active: true,
      sort_order: (highest._max.sort_order ?? 0) + 1,
      created_at: now(),
      updated_at: now(),
    },
    select: { id: true },
  });

  if (valid.requirements.length > 0) {
    await db.request_type_requirements.createMany({
      data: requirementRows(valid.requirements, created.id),
    });
  }

  return { message: `Request type ${valid.name} created.` };
}

export async function updateRequestType(
  requestTypeId: number,
  input: RequestTypeInput,
): Promise<{ message: string }> {
  const existing = await requestTypeById(requestTypeId);

  if (!existing) {
    throw new ActionError('That request type no longer exists.');
  }

  const valid = await validateRequestType(input, requestTypeId);

  await db.request_types.update({
    where: { id: id(requestTypeId) },
    data: {
      name: valid.name,
      kind: valid.kind,
      department_id: valid.departmentId === null ? null : id(valid.departmentId),
      resource_id: valid.resourceId === null ? null : id(valid.resourceId),
      description: valid.description,
      updated_at: now(),
    },
  });

  /**
   * The checklist is replaced wholesale. Requirements already snapshotted onto
   * submitted requests are unaffected — `document_requirements` keep their own
   * label and mandatory copy, and only the foreign key nulls out.
   */
  await db.request_type_requirements.deleteMany({
    where: { request_type_id: id(requestTypeId) },
  });

  if (valid.requirements.length > 0) {
    await db.request_type_requirements.createMany({
      data: requirementRows(valid.requirements, id(requestTypeId)),
    });
  }

  return { message: `Request type ${valid.name} updated.` };
}

export async function toggleRequestType(requestTypeId: number): Promise<{ message: string }> {
  const existing = await requestTypeById(requestTypeId);

  if (!existing) {
    throw new ActionError('That request type no longer exists.');
  }

  await db.request_types.update({
    where: { id: id(requestTypeId) },
    data: { is_active: !existing.isActive, updated_at: now() },
  });

  return {
    message: `Request type ${existing.name} has been ${existing.isActive ? 'deactivated' : 'activated'}.`,
  };
}

export async function deleteRequestType(requestTypeId: number): Promise<{ message: string }> {
  const existing = await requestTypeById(requestTypeId);

  if (!existing) {
    throw new ActionError('That request type no longer exists.');
  }

  await db.request_type_requirements.deleteMany({
    where: { request_type_id: id(requestTypeId) },
  });
  await db.request_types.delete({ where: { id: id(requestTypeId) } });

  return { message: `Request type ${existing.name} has been deleted.` };
}

// ===== Route templates =====

export const STEP_CONDITIONS = ['always', 'if_approved', 'if_over_threshold'] as const;

export type TemplateStepInput = {
  stepOrder: number;
  departmentId: number;
  action: string;
  condition: string | null;
};

export type RouteTemplateInput = {
  name: string;
  description: string | null;
  steps: TemplateStepInput[];
};

export async function routeTemplateList() {
  const rows = await db.route_templates.findMany({
    orderBy: { name: 'asc' },
    select: {
      id: true,
      name: true,
      description: true,
      is_active: true,
      _count: { select: { steps: true } },
    },
  });

  return rows.map((row) => ({
    id: toId(row.id),
    name: row.name,
    description: row.description,
    isActive: row.is_active,
    steps: row._count.steps,
  }));
}

export async function routeTemplateById(templateId: number) {
  const row = await db.route_templates.findFirst({
    where: { id: id(templateId) },
    select: {
      id: true,
      name: true,
      description: true,
      is_active: true,
      steps: {
        orderBy: { step_order: 'asc' },
        select: { step_order: true, department_id: true, action: true, condition: true },
      },
    },
  });

  return row === null
    ? null
    : {
        id: toId(row.id),
        name: row.name,
        description: row.description,
        isActive: row.is_active,
        steps: row.steps.map((step) => ({
          stepOrder: step.step_order,
          departmentId: toId(step.department_id),
          action: step.action,
          condition: step.condition,
        })),
      };
}

async function validateTemplate(
  input: RouteTemplateInput,
  ignoreId: number | null,
): Promise<{ name: string; description: string | null; steps: TemplateStepInput[] }> {
  const name = input.name.trim();

  if (name === '' || name.length > 255) {
    throw new ActionError('Give the template a name of up to 255 characters.');
  }

  const clash = await db.route_templates.findFirst({
    where: { name, ...(ignoreId === null ? {} : { NOT: { id: id(ignoreId) } }) },
    select: { id: true },
  });

  if (clash) {
    throw new ActionError(`A template called ${name} already exists.`);
  }

  const description = (input.description ?? '').trim();

  if (description.length > 500) {
    throw new ActionError('Keep the description to 500 characters.');
  }

  const steps = input.steps.filter((step) => step.departmentId > 0);

  if (steps.length === 0) {
    throw new ActionError('A template needs at least one office in its chain.');
  }

  for (const step of steps) {
    if (!Number.isInteger(step.stepOrder) || step.stepOrder < 1 || step.stepOrder > 50) {
      throw new ActionError('Each step is numbered between 1 and 50.');
    }

    if (step.action.trim() === '' || step.action.length > 100) {
      throw new ActionError('Say what each office does, in up to 100 characters.');
    }

    if (step.condition !== null && !(STEP_CONDITIONS as readonly string[]).includes(step.condition)) {
      throw new ActionError('That step condition is not one this system knows.');
    }
  }

  const offices = await db.departments.findMany({
    where: { id: { in: steps.map((step) => id(step.departmentId)) } },
    select: { id: true },
  });

  if (offices.length !== new Set(steps.map((step) => step.departmentId)).size) {
    throw new ActionError('One of those offices no longer exists.');
  }

  return { name, description: description === '' ? null : description, steps };
}

function templateStepRows(steps: TemplateStepInput[], templateId: bigint) {
  return steps.map((step) => ({
    route_template_id: templateId,
    step_order: step.stepOrder,
    department_id: id(step.departmentId),
    action: step.action.trim(),
    condition: step.condition,
    created_at: now(),
    updated_at: now(),
  }));
}

export async function createRouteTemplate(
  input: RouteTemplateInput,
): Promise<{ message: string }> {
  const valid = await validateTemplate(input, null);

  const created = await db.route_templates.create({
    data: {
      name: valid.name,
      description: valid.description,
      is_active: true,
      created_at: now(),
      updated_at: now(),
    },
    select: { id: true },
  });

  await db.route_template_steps.createMany({
    data: templateStepRows(valid.steps, created.id),
  });

  return { message: `Route template ${valid.name} created successfully.` };
}

export async function updateRouteTemplate(
  templateId: number,
  input: RouteTemplateInput,
): Promise<{ message: string }> {
  const existing = await routeTemplateById(templateId);

  if (!existing) {
    throw new ActionError('That template no longer exists.');
  }

  const valid = await validateTemplate(input, templateId);

  await db.route_templates.update({
    where: { id: id(templateId) },
    data: { name: valid.name, description: valid.description, updated_at: now() },
  });

  // Replaced wholesale: templates only prefill new requests, so chains already
  // materialised onto request_steps are untouched.
  await db.route_template_steps.deleteMany({ where: { route_template_id: id(templateId) } });
  await db.route_template_steps.createMany({
    data: templateStepRows(valid.steps, id(templateId)),
  });

  return { message: `Route template ${valid.name} updated successfully.` };
}

export async function toggleRouteTemplate(templateId: number): Promise<{ message: string }> {
  const existing = await routeTemplateById(templateId);

  if (!existing) {
    throw new ActionError('That template no longer exists.');
  }

  await db.route_templates.update({
    where: { id: id(templateId) },
    data: { is_active: !existing.isActive, updated_at: now() },
  });

  return {
    message: `Route template ${existing.name} has been ${existing.isActive ? 'deactivated' : 'activated'}.`,
  };
}

export async function deleteRouteTemplate(templateId: number): Promise<{ message: string }> {
  const existing = await routeTemplateById(templateId);

  if (!existing) {
    throw new ActionError('That template no longer exists.');
  }

  await db.route_template_steps.deleteMany({ where: { route_template_id: id(templateId) } });
  await db.route_templates.delete({ where: { id: id(templateId) } });

  return { message: `Route template ${existing.name} has been deleted.` };
}

// ===== Resources =====

export type ResourceInput = {
  name: string;
  kind: string;
  description: string | null;
  location: string | null;
  totalQuantity: number | null;
  capacity: number | null;
};

export async function resourceById(resourceId: number) {
  const row = await db.resources.findFirst({
    where: { id: id(resourceId) },
    select: {
      id: true,
      name: true,
      kind: true,
      description: true,
      location: true,
      is_active: true,
      total_quantity: true,
      capacity: true,
      _count: { select: { bookings: true } },
    },
  });

  return row === null
    ? null
    : {
        id: toId(row.id),
        name: row.name,
        kind: row.kind,
        description: row.description,
        location: row.location,
        isActive: row.is_active,
        totalQuantity: row.total_quantity,
        capacity: row.capacity,
        // A facility and a counted pile are measured differently, so what an
        // item IS is fixed once anything has been booked against it.
        kindLocked: row._count.bookings > 0,
      };
}

async function validateResource(
  input: ResourceInput,
  ignoreId: number | null,
): Promise<ResourceInput> {
  const name = input.name.trim();

  if (name === '' || name.length > 255) {
    throw new ActionError('Give the item a name of up to 255 characters.');
  }

  if (input.kind !== KIND_FACILITY && input.kind !== KIND_EQUIPMENT) {
    throw new ActionError('Choose whether this is a facility or equipment.');
  }

  const clash = await db.resources.findFirst({
    where: { name, ...(ignoreId === null ? {} : { NOT: { id: id(ignoreId) } }) },
    select: { id: true },
  });

  if (clash) {
    throw new ActionError(`An item called ${name} already exists.`);
  }

  for (const [label, value] of [
    ['The number owned', input.totalQuantity],
    ['The capacity', input.capacity],
  ] as const) {
    if (value !== null && (!Number.isInteger(value) || value < 0 || value > 1_000_000)) {
      throw new ActionError(`${label} is a whole number.`);
    }
  }

  const description = (input.description ?? '').trim();
  const location = (input.location ?? '').trim();

  if (description.length > 500) {
    throw new ActionError('Keep the description to 500 characters.');
  }

  if (location.length > 255) {
    throw new ActionError('Keep the location to 255 characters.');
  }

  return {
    name,
    kind: input.kind,
    description: description === '' ? null : description,
    location: location === '' ? null : location,
    totalQuantity: input.totalQuantity,
    capacity: input.capacity,
  };
}

export async function createResource(
  input: ResourceInput,
  actor: Actor,
): Promise<{ message: string }> {
  const valid = await validateResource(input, null);
  const isEquipment = valid.kind === KIND_EQUIPMENT;

  const highest = await db.resources.aggregate({ _max: { sort_order: true } });

  const created = await db.resources.create({
    data: {
      name: valid.name,
      kind: valid.kind,
      description: valid.description,
      location: valid.location,
      total_quantity: isEquipment ? valid.totalQuantity : null,
      capacity: isEquipment ? null : valid.capacity,
      is_active: true,
      sort_order: (highest._max.sort_order ?? 0) + 1,
      created_at: now(),
      updated_at: now(),
    },
    select: { id: true },
  });

  await logActivity({
    description: `Added ${valid.name} to the catalog.`,
    subjectType: SUBJECT.Resource,
    subjectId: toId(created.id),
    causerId: actor.id,
  });

  return { message: `${valid.name} added.` };
}

export async function updateResource(
  resourceId: number,
  input: ResourceInput,
  actor: Actor,
): Promise<{ message: string }> {
  const existing = await resourceById(resourceId);

  if (!existing) {
    throw new ActionError('That item no longer exists.');
  }

  const valid = await validateResource(input, resourceId);

  if (valid.kind !== existing.kind && existing.kindLocked) {
    throw new ActionError(
      `${existing.name} already has bookings, so it can no longer change between facility and equipment. Add a new item instead.`,
    );
  }

  await db.resources.update({
    where: { id: id(resourceId) },
    data: {
      name: valid.name,
      kind: valid.kind,
      description: valid.description,
      location: valid.location,
      updated_at: now(),
    },
  });

  await logActivity({
    description: `Edited ${valid.name}'s details.`,
    subjectType: SUBJECT.Resource,
    subjectId: resourceId,
    causerId: actor.id,
  });

  return { message: `${valid.name} updated.` };
}

export async function toggleResource(
  resourceId: number,
  actor: Actor,
): Promise<{ message: string }> {
  const existing = await resourceById(resourceId);

  if (!existing) {
    throw new ActionError('That item no longer exists.');
  }

  const action = existing.isActive ? 'deactivated' : 'activated';

  await db.resources.update({
    where: { id: id(resourceId) },
    data: { is_active: !existing.isActive, updated_at: now() },
  });

  await logActivity({
    description: `${action.charAt(0).toUpperCase()}${action.slice(1)} ${existing.name}.`,
    subjectType: SUBJECT.Resource,
    subjectId: resourceId,
    causerId: actor.id,
  });

  return { message: `${existing.name} has been ${action}.` };
}

/**
 * Deleting takes the item's bookings with it, so it is refused while any of
 * them is still live — waiting, promised or out on loan. Deactivating keeps the
 * history and stops new requests.
 */
export async function deleteResource(resourceId: number): Promise<{ message: string }> {
  const existing = await resourceById(resourceId);

  if (!existing) {
    throw new ActionError('That item no longer exists.');
  }

  const live = await db.bookings.count({
    where: {
      resource_id: id(resourceId),
      status: { in: [BOOKING_STATUS.Pending, BOOKING_STATUS.Approved] },
      returned_at: null,
      OR: [{ released_at: { not: null } }, { ends_at: { gt: now() } }],
    },
  });

  if (live > 0) {
    throw new ActionError(
      `${existing.name} has ${live} live ${live === 1 ? 'booking' : 'bookings'} (waiting, promised or out on loan), so it cannot be deleted. Deactivate it instead, or finish those first.`,
    );
  }

  await db.resources.delete({ where: { id: id(resourceId) } });

  return { message: `${existing.name} has been deleted.` };
}
