import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * Port of `RequestTypeAdminTest` (and the request-type half of
 * `ResourceCatalogParityTest`).
 *
 * The catalog is what the public form offers, so its rules decide whether a
 * citizen's request can be filed at all and whether it reaches an office: a
 * checklist is replaced wholesale without touching the copies already
 * snapshotted onto filed requests, and a borrowing type must point at the right
 * kind of resource or every request it files runs the wrong stock rules.
 */
const typeFindMany = vi.fn();
const typeFindFirst = vi.fn();
const typeCreate = vi.fn();
const typeUpdate = vi.fn();
const typeDelete = vi.fn();
const typeAggregate = vi.fn();
const requirementCreateMany = vi.fn();
const requirementDeleteMany = vi.fn();
const departmentFindFirst = vi.fn();
const departmentFindMany = vi.fn();
const resourceFindFirst = vi.fn();
const resourceFindMany = vi.fn();
const resourceCreate = vi.fn();
const resourceUpdate = vi.fn();
const resourceDelete = vi.fn();
const resourceAggregate = vi.fn();
const bookingCount = vi.fn();
const templateFindMany = vi.fn();
const templateFindFirst = vi.fn();
const templateCreate = vi.fn();
const templateUpdate = vi.fn();
const templateDelete = vi.fn();
const templateStepCreateMany = vi.fn();
const templateStepDeleteMany = vi.fn();
const logActivity = vi.fn();

vi.mock('@/lib/db', () => ({
  db: {
    request_types: {
      findMany: typeFindMany,
      findFirst: typeFindFirst,
      create: typeCreate,
      update: typeUpdate,
      delete: typeDelete,
      aggregate: typeAggregate,
    },
    request_type_requirements: {
      createMany: requirementCreateMany,
      deleteMany: requirementDeleteMany,
    },
    departments: { findFirst: departmentFindFirst, findMany: departmentFindMany },
    resources: {
      findFirst: resourceFindFirst,
      findMany: resourceFindMany,
      create: resourceCreate,
      update: resourceUpdate,
      delete: resourceDelete,
      aggregate: resourceAggregate,
    },
    route_templates: {
      findMany: templateFindMany,
      findFirst: templateFindFirst,
      create: templateCreate,
      update: templateUpdate,
      delete: templateDelete,
    },
    route_template_steps: {
      createMany: templateStepCreateMany,
      deleteMany: templateStepDeleteMany,
    },
    bookings: { count: bookingCount },
  },
  toId: (value: bigint | number) => Number(value),
  id: (value: number) => BigInt(value),
}));

vi.mock('@/lib/activity', () => ({
  logActivity,
  SUBJECT: { Resource: 'App\\Models\\Resource' },
}));

const {
  createRequestType,
  createResource,
  createRouteTemplate,
  deleteRequestType,
  deleteResource,
  deleteRouteTemplate,
  requestTypeList,
  resourceOptions,
  toggleRequestType,
  toggleResource,
  updateRequestType,
  updateResource,
  updateRouteTemplate,
} = await import('@/lib/domain/admin-catalog');

/** Catalog writes are logged against the item, so they take the actor. */
const actor = { id: 1, name: 'Ava Admin' } as unknown as import('@/lib/auth/actor').Actor;

const permit = {
  name: 'Business Permit',
  kind: 'document',
  departmentId: 3,
  resourceId: null,
  description: null,
  requirements: [
    { label: 'Barangay Business Clearance', isMandatory: true },
    { label: 'Cedula', isMandatory: false },
  ],
};

beforeEach(() => {
  vi.clearAllMocks();
  typeFindFirst.mockResolvedValue(null);
  typeFindMany.mockResolvedValue([]);
  typeCreate.mockResolvedValue({ id: 11n });
  typeAggregate.mockResolvedValue({ _max: { sort_order: 4 } });
  departmentFindFirst.mockResolvedValue({ id: 3n });
  departmentFindMany.mockResolvedValue([{ id: 3n }]);
  resourceFindFirst.mockResolvedValue({ id: 7n, name: 'Monoblock Chairs', kind: 'equipment' });
  resourceFindMany.mockResolvedValue([]);
  templateFindFirst.mockResolvedValue(null);
  templateFindMany.mockResolvedValue([]);
  templateCreate.mockResolvedValue({ id: 21n });
});

describe('createRequestType', () => {
  it('saves the checklist in order, keeping which entries are required', async () => {
    await createRequestType(permit);

    expect(typeCreate).toHaveBeenCalledWith(
      expect.objectContaining({
        data: expect.objectContaining({
          name: 'Business Permit',
          kind: 'document',
          department_id: 3n,
          resource_id: null,
          is_active: true,
          // Appended to the end of the public form's list.
          sort_order: 5,
        }),
      }),
    );

    expect(requirementCreateMany).toHaveBeenCalledWith({
      data: [
        expect.objectContaining({
          label: 'Barangay Business Clearance',
          is_mandatory: true,
          sort_order: 0,
          request_type_id: 11n,
        }),
        expect.objectContaining({ label: 'Cedula', is_mandatory: false, sort_order: 1 }),
      ],
    });
  });

  it('drops blank requirement rows the form left behind', async () => {
    await createRequestType({
      ...permit,
      requirements: [
        { label: '  Cedula  ', isMandatory: true },
        { label: '   ', isMandatory: true },
      ],
    });

    expect(requirementCreateMany).toHaveBeenCalledWith({
      data: [expect.objectContaining({ label: 'Cedula' })],
    });
  });

  it('writes no checklist at all when none was given', async () => {
    await createRequestType({ ...permit, requirements: [] });

    expect(typeCreate).toHaveBeenCalled();
    expect(requirementCreateMany).not.toHaveBeenCalled();
  });

  it('refuses a duplicate name and an unknown kind', async () => {
    typeFindFirst.mockResolvedValue({ id: 9n });

    await expect(createRequestType(permit)).rejects.toThrow(
      'A request type called Business Permit already exists.',
    );

    typeFindFirst.mockResolvedValue(null);

    await expect(createRequestType({ ...permit, kind: 'wish' })).rejects.toThrow(
      'Choose what kind of request this is.',
    );

    expect(typeCreate).not.toHaveBeenCalled();
  });

  it('refuses a borrowing type with no resource named', async () => {
    await expect(
      createRequestType({ ...permit, kind: 'equipment', resourceId: null }),
    ).rejects.toThrow('Choose the facility or item this request reserves.');
  });

  it('refuses an equipment type pointed at a facility, and the reverse', async () => {
    resourceFindFirst.mockResolvedValue({ id: 7n, name: 'Covered Court', kind: 'facility' });

    await expect(
      createRequestType({ ...permit, kind: 'equipment', resourceId: 7 }),
    ).rejects.toThrow('Covered Court is a facility.');

    resourceFindFirst.mockResolvedValue({ id: 8n, name: 'Monoblock Chairs', kind: 'equipment' });

    await expect(createRequestType({ ...permit, kind: 'booking', resourceId: 8 })).rejects.toThrow(
      'Monoblock Chairs is equipment.',
    );

    expect(typeCreate).not.toHaveBeenCalled();
  });

  it('accepts an equipment type pointed at equipment', async () => {
    await createRequestType({ ...permit, kind: 'equipment', resourceId: 7 });

    expect(typeCreate).toHaveBeenCalledWith(
      expect.objectContaining({
        data: expect.objectContaining({ kind: 'equipment', resource_id: 7n }),
      }),
    );
  });

  it('ignores a resource on a kind that does not use one', async () => {
    await createRequestType({ ...permit, kind: 'document', resourceId: 7 });

    expect(typeCreate).toHaveBeenCalledWith(
      expect.objectContaining({ data: expect.objectContaining({ resource_id: null }) }),
    );
  });

  it('refuses an office that no longer exists, and an over-long description', async () => {
    departmentFindFirst.mockResolvedValue(null);

    await expect(createRequestType(permit)).rejects.toThrow('That office no longer exists.');

    departmentFindFirst.mockResolvedValue({ id: 3n });

    await expect(createRequestType({ ...permit, description: 'x'.repeat(501) })).rejects.toThrow(
      'Keep the description to 500 characters.',
    );
  });
});

describe('updateRequestType', () => {
  const existing = {
    id: 11n,
    name: 'Barangay Clearance',
    kind: 'document',
    description: null,
    is_active: true,
    department_id: 3n,
    resource_id: null,
    request_type_requirements: [{ id: 1n, label: 'Old requirement', is_mandatory: true }],
  };

  it('replaces the checklist wholesale', async () => {
    typeFindFirst.mockResolvedValueOnce(existing).mockResolvedValueOnce(null);

    await updateRequestType(11, {
      ...permit,
      name: 'Barangay Clearance',
      requirements: [{ label: 'Valid Government ID', isMandatory: true }],
    });

    // Delete then re-create: filed requests keep their own snapshot, so this
    // edits the menu rather than rewriting history.
    expect(requirementDeleteMany).toHaveBeenCalledWith({ where: { request_type_id: 11n } });
    expect(requirementCreateMany).toHaveBeenCalledWith({
      data: [expect.objectContaining({ label: 'Valid Government ID', sort_order: 0 })],
    });
  });

  it('empties the checklist when every row was removed', async () => {
    typeFindFirst.mockResolvedValueOnce(existing).mockResolvedValueOnce(null);

    await updateRequestType(11, { ...permit, name: 'Barangay Clearance', requirements: [] });

    expect(requirementDeleteMany).toHaveBeenCalled();
    expect(requirementCreateMany).not.toHaveBeenCalled();
  });

  it('refuses a type that no longer exists', async () => {
    typeFindFirst.mockResolvedValue(null);

    await expect(updateRequestType(99, permit)).rejects.toThrow('no longer exists');
  });
});

describe('toggleRequestType / deleteRequestType', () => {
  const existing = {
    id: 11n,
    name: 'Business Permit',
    kind: 'document',
    description: null,
    is_active: true,
    department_id: 3n,
    resource_id: null,
    request_type_requirements: [],
  };

  it('deactivates a type, which is what hides it from the public form', async () => {
    typeFindFirst.mockResolvedValue(existing);

    const result = await toggleRequestType(11);

    expect(typeUpdate).toHaveBeenCalledWith(
      expect.objectContaining({ data: expect.objectContaining({ is_active: false }) }),
    );
    expect(result.message).toBe('Request type Business Permit has been deactivated.');
  });

  it('takes the checklist with it on delete', async () => {
    typeFindFirst.mockResolvedValue(existing);

    await deleteRequestType(11);

    expect(requirementDeleteMany).toHaveBeenCalledWith({ where: { request_type_id: 11n } });
    expect(typeDelete).toHaveBeenCalledWith({ where: { id: 11n } });
  });
});

describe('requestTypeList', () => {
  it('reports the office gap as a missing name rather than a blank', async () => {
    typeFindMany.mockResolvedValue([
      {
        id: 11n,
        name: 'Business Permit',
        kind: 'document',
        description: 'For new businesses',
        is_active: true,
        department: null,
        resource: null,
        _count: { request_type_requirements: 2 },
      },
    ]);

    const [row] = await requestTypeList();

    expect(row).toMatchObject({ office: null, officeCode: null, requirements: 2 });
  });
});

describe('route templates', () => {
  /**
   * Port of `RouteTemplateTest::super_admin_can_create_a_template_with_branching_steps`,
   * `template_requires_at_least_one_valid_step` and
   * `updating_a_template_replaces_its_steps`.
   */
  const procurement = {
    name: 'Procurement Request',
    description: 'RA 12009 flow',
    steps: [
      { stepOrder: 1, departmentId: 3, action: 'Approve request', condition: null },
      { stepOrder: 2, departmentId: 4, action: 'Small Value Procurement', condition: 'below_threshold' },
      { stepOrder: 2, departmentId: 4, action: 'Public Bidding', condition: 'at_least_threshold' },
    ],
  };

  it('keeps two steps that share a step number — that is how a route branches', async () => {
    departmentFindMany.mockResolvedValue([{ id: 3n }, { id: 4n }]);

    await createRouteTemplate(procurement);

    expect(templateStepCreateMany).toHaveBeenCalledWith({
      data: [
        expect.objectContaining({ step_order: 1, condition: null, route_template_id: 21n }),
        expect.objectContaining({ step_order: 2, condition: 'below_threshold' }),
        expect.objectContaining({ step_order: 2, condition: 'at_least_threshold' }),
      ],
    });
  });

  it('accepts the condition values the seeded chains already use', async () => {
    departmentFindMany.mockResolvedValue([{ id: 3n }]);

    await createRouteTemplate({
      ...procurement,
      steps: [
        { stepOrder: 1, departmentId: 3, action: 'Certify fund availability', condition: 'has_amount' },
      ],
    });

    expect(templateStepCreateMany).toHaveBeenCalled();
  });

  it('refuses a condition this system does not know', async () => {
    await expect(
      createRouteTemplate({
        ...procurement,
        steps: [{ stepOrder: 1, departmentId: 3, action: 'Approve', condition: 'if_approved' }],
      }),
    ).rejects.toThrow('That step condition is not one this system knows.');

    expect(templateCreate).not.toHaveBeenCalled();
  });

  it('refuses a chain with no office in it', async () => {
    await expect(createRouteTemplate({ ...procurement, steps: [] })).rejects.toThrow(
      'A template needs at least one office in its chain.',
    );

    // A row the form left on screen with no office picked is not a step.
    await expect(
      createRouteTemplate({
        ...procurement,
        steps: [{ stepOrder: 1, departmentId: 0, action: 'Approve', condition: null }],
      }),
    ).rejects.toThrow('at least one office');
  });

  it('refuses an office that no longer exists', async () => {
    departmentFindMany.mockResolvedValue([]);

    await expect(
      createRouteTemplate({
        ...procurement,
        steps: [{ stepOrder: 1, departmentId: 999999, action: 'Approve', condition: null }],
      }),
    ).rejects.toThrow('One of those offices no longer exists.');
  });

  it('refuses a step number outside 1–50 and an action with no words', async () => {
    await expect(
      createRouteTemplate({
        ...procurement,
        steps: [{ stepOrder: 0, departmentId: 3, action: 'Approve', condition: null }],
      }),
    ).rejects.toThrow('numbered between 1 and 50');

    await expect(
      createRouteTemplate({
        ...procurement,
        steps: [{ stepOrder: 1, departmentId: 3, action: '  ', condition: null }],
      }),
    ).rejects.toThrow('Say what each office does');
  });

  it('refuses a duplicate template name', async () => {
    templateFindFirst.mockResolvedValue({ id: 9n });

    await expect(createRouteTemplate(procurement)).rejects.toThrow(
      'A template called Procurement Request already exists.',
    );
  });

  it('replaces the chain wholesale on update', async () => {
    templateFindFirst
      .mockResolvedValueOnce({
        id: 21n,
        name: 'Procurement Request',
        description: null,
        is_active: true,
        steps: [{ step_order: 1, department_id: 3n, action: 'Old hop', condition: null }],
      })
      .mockResolvedValueOnce(null);

    await updateRouteTemplate(21, {
      ...procurement,
      steps: [{ stepOrder: 1, departmentId: 3, action: 'Approve request', condition: null }],
    });

    // Templates only prefill new requests, so chains already materialised onto
    // request_steps are untouched by this.
    expect(templateStepDeleteMany).toHaveBeenCalledWith({ where: { route_template_id: 21n } });
    expect(templateStepCreateMany).toHaveBeenCalledWith({
      data: [expect.objectContaining({ action: 'Approve request' })],
    });
  });

  it('takes the chain with it on delete', async () => {
    templateFindFirst.mockResolvedValue({
      id: 21n,
      name: 'Procurement Request',
      description: null,
      is_active: true,
      steps: [],
    });

    await deleteRouteTemplate(21);

    expect(templateStepDeleteMany).toHaveBeenCalledWith({ where: { route_template_id: 21n } });
    expect(templateDelete).toHaveBeenCalledWith({ where: { id: 21n } });
  });
});

describe('the resource catalog', () => {
  /**
   * Port of `ResourceAdminTest` and the catalog half of
   * `ResourceCatalogParityTest`.
   */
  const chairs = {
    id: 7n,
    name: 'Monoblock Chairs',
    kind: 'equipment',
    description: null,
    location: 'GSO stockroom',
    is_active: true,
    total_quantity: 100,
    capacity: null,
    _count: { bookings: 0 },
  };

  const asEquipment = {
    name: 'Monoblock Chairs',
    kind: 'equipment',
    description: null,
    location: 'GSO stockroom',
    totalQuantity: 100,
    capacity: null,
  };

  beforeEach(() => {
    resourceFindFirst.mockResolvedValue(null);
    resourceCreate.mockResolvedValue({ id: 7n });
    resourceAggregate.mockResolvedValue({ _max: { sort_order: 2 } });
    bookingCount.mockResolvedValue(0);
  });

  it('adds equipment with its count, and leaves capacity off it', async () => {
    await createResource(asEquipment, actor);

    expect(resourceCreate).toHaveBeenCalledWith(
      expect.objectContaining({
        data: expect.objectContaining({
          name: 'Monoblock Chairs',
          kind: 'equipment',
          total_quantity: 100,
          // A pile is counted; only a venue has a capacity.
          capacity: null,
          is_active: true,
          sort_order: 3,
        }),
      }),
    );
    expect(logActivity).toHaveBeenCalled();
  });

  it('adds a facility with its capacity, and leaves the count off it', async () => {
    await createResource(
      { ...asEquipment, name: 'Covered Court', kind: 'facility', capacity: 500, totalQuantity: 40 },
      actor,
    );

    expect(resourceCreate).toHaveBeenCalledWith(
      expect.objectContaining({
        data: expect.objectContaining({ kind: 'facility', capacity: 500, total_quantity: null }),
      }),
    );
  });

  it('refuses a kind that is neither', async () => {
    await expect(createResource({ ...asEquipment, kind: 'vehicle' }, actor)).rejects.toThrow(
      'Choose whether this is a facility or equipment.',
    );

    expect(resourceCreate).not.toHaveBeenCalled();
  });

  it('refuses a duplicate name and a count that is not a whole number', async () => {
    resourceFindFirst.mockResolvedValue({ id: 9n });

    await expect(createResource(asEquipment, actor)).rejects.toThrow(
      'An item called Monoblock Chairs already exists.',
    );

    resourceFindFirst.mockResolvedValue(null);

    await expect(
      createResource({ ...asEquipment, totalQuantity: -5 }, actor),
    ).rejects.toThrow('The number owned is a whole number.');
  });

  it('refuses to flip the kind of an item that already has bookings', async () => {
    resourceFindFirst
      .mockResolvedValueOnce({ ...chairs, _count: { bookings: 1 } })
      .mockResolvedValueOnce(null);

    await expect(
      updateResource(7, { ...asEquipment, kind: 'facility' }, actor),
    ).rejects.toThrow('can no longer change between facility and equipment');

    expect(resourceUpdate).not.toHaveBeenCalled();
  });

  it('still lets that item be renamed', async () => {
    resourceFindFirst
      .mockResolvedValueOnce({ ...chairs, _count: { bookings: 1 } })
      .mockResolvedValueOnce(null);

    await updateResource(7, { ...asEquipment, name: 'Plastic Chairs' }, actor);

    expect(resourceUpdate).toHaveBeenCalledWith(
      expect.objectContaining({ data: expect.objectContaining({ name: 'Plastic Chairs' }) }),
    );
  });

  it('does not let an edit rewrite the counts', async () => {
    resourceFindFirst.mockResolvedValueOnce(chairs).mockResolvedValueOnce(null);

    await updateResource(7, { ...asEquipment, totalQuantity: 9999 }, actor);

    const data = resourceUpdate.mock.calls[0][0].data;

    // The counts are kept on the Resources page, where the checks against what
    // is out on loan live.
    expect(data.total_quantity).toBeUndefined();
    expect(data.capacity).toBeUndefined();
  });

  it('deactivates an item, which is what hides it from the public form', async () => {
    resourceFindFirst.mockResolvedValue(chairs);

    const result = await toggleResource(7, actor);

    expect(resourceUpdate).toHaveBeenCalledWith(
      expect.objectContaining({ data: expect.objectContaining({ is_active: false }) }),
    );
    expect(result.message).toBe('Monoblock Chairs has been deactivated.');
    expect(logActivity).toHaveBeenCalled();
  });

  it('refuses to delete an item with a live booking', async () => {
    resourceFindFirst.mockResolvedValue(chairs);
    bookingCount.mockResolvedValue(2);

    await expect(deleteResource(7)).rejects.toThrow(
      'Monoblock Chairs has 2 live bookings (waiting, promised or out on loan)',
    );

    expect(resourceDelete).not.toHaveBeenCalled();
  });

  it('deletes it once nothing live points at it', async () => {
    resourceFindFirst.mockResolvedValue(chairs);

    await deleteResource(7);

    expect(resourceDelete).toHaveBeenCalledWith({ where: { id: 7n } });
  });
});

describe('resourceOptions', () => {
  it('offers only items that are still active', async () => {
    await resourceOptions();

    // A type aimed at a deactivated item is hidden from the public form anyway,
    // so offering one here would only build a dead end.
    expect(resourceFindMany).toHaveBeenCalledWith(
      expect.objectContaining({ where: { is_active: true } }),
    );
  });
});
