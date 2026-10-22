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
const resourceFindFirst = vi.fn();
const resourceFindMany = vi.fn();
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
    departments: { findFirst: departmentFindFirst, findMany: vi.fn() },
    resources: { findFirst: resourceFindFirst, findMany: resourceFindMany },
    route_templates: {},
    route_template_steps: {},
    bookings: { count: vi.fn() },
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
  deleteRequestType,
  requestTypeList,
  resourceOptions,
  toggleRequestType,
  updateRequestType,
} = await import('@/lib/domain/admin-catalog');

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
  resourceFindFirst.mockResolvedValue({ id: 7n, name: 'Monoblock Chairs', kind: 'equipment' });
  resourceFindMany.mockResolvedValue([]);
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
