import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Actor } from '@/lib/auth/actor';
import { STEP_STATUS } from '@/lib/domain/request-step';
import { ROUTE_DIRECT, ROUTE_THROUGH, isDestination, planRoute } from '@/lib/domain/internal-route-plan';

/**
 * An internal request goes one of two ways. Through Action Mamamayan and the
 * Mayor (the default): AM validates it, then the Office of the Mayor approves
 * it and chooses the office it goes to. Straight to an office: just that one.
 * The queued route is created when the request is filed.
 */
const AM = { id: 11, name: 'Action Mamamayan', code: 'AM' };
const OM = { id: 1, name: 'Office of the Mayor', code: 'OM' };
const GSO = { id: 5, name: 'General Services Office', code: 'GSO' };
const CTCAO = { id: 8, name: 'City Tourism, Culture and Arts Office', code: 'CTCAO' };

describe('planRoute', () => {
  const codes = (route: Array<{ code: string }>) => route.map((office) => office.code);

  it('sends a request through Action Mamamayan and the Office of the Mayor, with no destination yet', () => {
    expect(codes(planRoute([AM, OM], CTCAO.id, ROUTE_THROUGH, null))).toEqual(['AM', 'OM']);
  });

  it('ignores a destination when going through the Mayor (the Mayor chooses it)', () => {
    expect(codes(planRoute([AM, OM], CTCAO.id, ROUTE_THROUGH, GSO))).toEqual(['AM', 'OM']);
  });

  it('sends a request straight to the chosen office, the Office of the Mayor included', () => {
    expect(codes(planRoute([AM, OM], CTCAO.id, ROUTE_DIRECT, GSO))).toEqual(['GSO']);
    expect(codes(planRoute([AM, OM], CTCAO.id, ROUTE_DIRECT, OM))).toEqual(['OM']);
    expect(planRoute([AM, OM], CTCAO.id, ROUTE_DIRECT, null)).toEqual([]);
  });

  it("skips Action Mamamayan's own place, but the Mayor's office always keeps its hop", () => {
    expect(codes(planRoute([AM, OM], AM.id, ROUTE_THROUGH, null))).toEqual(['OM']);
    expect(codes(planRoute([AM, OM], OM.id, ROUTE_THROUGH, null))).toEqual(['AM', 'OM']);
  });

  it('leaves out a mandatory office that does not exist rather than block filing', () => {
    expect(codes(planRoute([OM], CTCAO.id, ROUTE_THROUGH, null))).toEqual(['OM']);
  });

  it('never offers Action Mamamayan as a destination', () => {
    expect(isDestination(AM)).toBe(false);
    expect(isDestination(OM)).toBe(true);
  });
});

// ── Filing queues the route ─────────────────────────────────────────────────
const departmentFindFirst = vi.fn();
const documentCreate = vi.fn();
const documentUpdate = vi.fn();
const stepCreate = vi.fn();
const custodyCreate = vi.fn();
const attachmentCreate = vi.fn();
const userFindFirst = vi.fn();
const isRecipientAt = vi.fn();
const mandatoryRouteOffices = vi.fn();
const tellTheHop = vi.fn();
const logSystemComment = vi.fn();

vi.mock('@/lib/db', () => ({
  db: {
    departments: { findFirst: departmentFindFirst },
    documents: { create: documentCreate, update: documentUpdate, delete: vi.fn() },
    request_steps: { create: stepCreate, deleteMany: vi.fn() },
    document_custody_events: { create: custodyCreate, deleteMany: vi.fn() },
    document_attachments: { create: attachmentCreate, deleteMany: vi.fn() },
    users: { findFirst: userFindFirst },
  },
  toId: (value: bigint | number) => Number(value),
  id: (value: number | bigint) => BigInt(value),
}));
vi.mock('@/lib/domain/internal-request-form', () => ({ isRecipientAt, mandatoryRouteOffices }));
vi.mock('@/lib/domain/internal-events', () => ({ tellTheHop }));
vi.mock('@/lib/domain/comments', () => ({ logSystemComment }));
vi.mock('@/lib/domain/qr-service', () => ({
  generateTrackingNumber: vi.fn(async () => 'INT-20261026-ROUTE1'),
  generateAndStore: vi.fn(async () => ({ success: true, relativePath: 'qrcodes/INT-20261026-ROUTE1.png' })),
}));
vi.mock('@/lib/storage', () => ({ storePrivate: vi.fn(async () => 'document-attachments/scan.pdf') }));
vi.mock('@/lib/domain/qr-stamp', () => ({ ensureStampedCopy: vi.fn(), NO_PLACEMENT: {} }));
vi.mock('@/lib/domain/upload-rules', () => ({ checkPaperScan: () => null }));

const { fileInternalRequest } = await import('@/lib/domain/internal-filing');

const filer = {
  id: 41,
  name: 'Ana Cruz',
  departmentId: CTCAO.id,
  can: (permission: string) => permission === 'create internal requests',
  hasRole: () => false,
} as unknown as Actor;

const scan = new File(['%PDF-1.4'], 'letter.pdf', { type: 'application/pdf' });

function offices(...rows: Array<{ id: number; name: string; code: string }>) {
  departmentFindFirst.mockImplementation(async ({ where }: { where: { id: bigint } }) => {
    const row = rows.find((office) => BigInt(office.id) === where.id);

    return row ? { ...row, id: BigInt(row.id) } : null;
  });
}

beforeEach(() => {
  vi.clearAllMocks();
  offices(CTCAO, GSO, OM, AM);
  mandatoryRouteOffices.mockResolvedValue([AM, OM]);
  isRecipientAt.mockResolvedValue(true);
  documentCreate.mockResolvedValue({ id: 70n });
  userFindFirst.mockResolvedValue({ name: 'Paolo Mendoza' });
  stepCreate.mockImplementation(async ({ data }: { data: Record<string, unknown> }) => ({
    id: BigInt(stepCreate.mock.calls.length),
    department_id: data.department_id,
    recipient_id: data.recipient_id,
    action: data.action,
  }));
});

describe('filing an internal request', () => {
  const base = { purpose: 'Twenty monoblock chairs', paperScan: scan };

  it('through the Mayor: queues Action Mamamayan (current) and the Office of the Mayor only', async () => {
    const result = await fileInternalRequest(filer, {
      ...base,
      route: ROUTE_THROUGH,
      destinationDepartmentId: null,
      hasHardCopy: true,
      recipientId: null,
    });

    const created = stepCreate.mock.calls.map(([{ data }]) => data);
    expect(created.map((step) => [Number(step.department_id), step.status, step.step_order])).toEqual([
      [AM.id, STEP_STATUS.Current, 1],
      [OM.id, STEP_STATUS.Pending, 2],
    ]);
    expect(created.map((step) => step.action)).toEqual(['Validate and confirm', 'Approve for the Mayor']);

    // Only the first office hears about it now.
    expect(tellTheHop).toHaveBeenCalledTimes(1);
    expect(tellTheHop.mock.calls[0][1]).toMatchObject({ department_id: AM.id });
    expect(result.message).toBe('Filed as INT-20261026-ROUTE1 — it goes to Action Mamamayan first.');
    expect(logSystemComment).toHaveBeenCalledWith(70, expect.stringContaining('route: AM → OM'));
  });

  it('goes through the Mayor when no route is given', async () => {
    await fileInternalRequest(filer, {
      ...base,
      destinationDepartmentId: GSO.id,
      hasHardCopy: true,
      recipientId: null,
    });

    expect(stepCreate.mock.calls.map(([{ data }]) => Number(data.department_id))).toEqual([AM.id, OM.id]);
  });

  it('through the Mayor, a soft copy names no recipient at filing', async () => {
    await fileInternalRequest(filer, {
      ...base,
      route: ROUTE_THROUGH,
      destinationDepartmentId: null,
      hasHardCopy: false,
      recipientId: 9,
    });

    expect(isRecipientAt).not.toHaveBeenCalled();
    expect(stepCreate.mock.calls.map(([{ data }]) => data.recipient_id)).toEqual([null, null]);
  });

  it('straight to an office: queues only that office', async () => {
    const result = await fileInternalRequest(filer, {
      ...base,
      route: ROUTE_DIRECT,
      destinationDepartmentId: GSO.id,
      hasHardCopy: true,
      recipientId: null,
    });

    const created = stepCreate.mock.calls.map(([{ data }]) => data);
    expect(created.map((step) => [Number(step.department_id), step.status, step.action])).toEqual([
      [GSO.id, STEP_STATUS.Current, 'Review and action'],
    ]);
    expect(result.message).toBe('Filed as INT-20261026-ROUTE1 — it goes straight to General Services Office.');
  });

  it('straight to the Office of the Mayor', async () => {
    await fileInternalRequest(filer, {
      ...base,
      route: ROUTE_DIRECT,
      destinationDepartmentId: OM.id,
      hasHardCopy: true,
      recipientId: null,
    });

    expect(stepCreate.mock.calls.map(([{ data }]) => Number(data.department_id))).toEqual([OM.id]);
  });

  it("straight to an office, a soft copy's named recipient is checked against that office", async () => {
    await fileInternalRequest(filer, {
      ...base,
      route: ROUTE_DIRECT,
      destinationDepartmentId: GSO.id,
      hasHardCopy: false,
      recipientId: 9,
    });

    expect(isRecipientAt).toHaveBeenCalledWith(9, GSO.id);
    expect(stepCreate.mock.calls.map(([{ data }]) => data.recipient_id)).toEqual([9n]);
  });

  it('refuses Action Mamamayan, or no office, as a direct destination', async () => {
    for (const destinationDepartmentId of [AM.id, null]) {
      await expect(
        fileInternalRequest(filer, {
          ...base,
          route: ROUTE_DIRECT,
          destinationDepartmentId,
          hasHardCopy: true,
          recipientId: null,
        }),
      ).rejects.toThrow('Choose an active office to send this request to.');
    }
    expect(documentCreate).not.toHaveBeenCalled();
  });

  it('refuses to go through the Mayor when neither mandatory office is set up', async () => {
    mandatoryRouteOffices.mockResolvedValue([]);

    await expect(
      fileInternalRequest(filer, {
        ...base,
        route: ROUTE_THROUGH,
        destinationDepartmentId: null,
        hasHardCopy: true,
        recipientId: null,
      }),
    ).rejects.toThrow('Send it straight to an office instead.');
    expect(documentCreate).not.toHaveBeenCalled();
  });
});

describe("the Clerk's home", () => {
  it('lands Action Mamamayan’s Clerk on Internal requests, not a citizen dashboard', async () => {
    const { landingFor } = await import('@/lib/routes');
    const clerk = { isSuperAdmin: () => false, isSupervisor: () => false, isClerk: () => true };
    const staff = { isSuperAdmin: () => false, isSupervisor: () => false, isClerk: () => false };

    expect(landingFor(clerk)).toBe('/requests');
    expect(landingFor(staff)).toBe('/my-dashboard');
  });
});
