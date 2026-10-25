import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Actor } from '@/lib/auth/actor';
import { STEP_STATUS } from '@/lib/domain/request-step';
import { isDestination, planRoute } from '@/lib/domain/internal-route-plan';

/**
 * Every internal request goes to Action Mamamayan to be validated, then to the
 * Office of the Mayor, and only then to the office it was filed for. The whole
 * route is queued when the request is filed.
 */
const AM = { id: 11, name: 'Action Mamamayan', code: 'AM' };
const OM = { id: 1, name: 'Office of the Mayor', code: 'OM' };
const GSO = { id: 5, name: 'General Services Office', code: 'GSO' };
const CTCAO = { id: 8, name: 'City Tourism, Culture and Arts Office', code: 'CTCAO' };

describe('planRoute', () => {
  it('sends a request through Action Mamamayan and the Office of the Mayor to its destination', () => {
    expect(planRoute([AM, OM], CTCAO.id, GSO).map((office) => office.code)).toEqual(['AM', 'OM', 'GSO']);
  });

  it('ends at the Office of the Mayor when that is the destination', () => {
    expect(planRoute([AM, OM], CTCAO.id, OM).map((office) => office.code)).toEqual(['AM', 'OM']);
  });

  it("skips the filing office's own place on the route", () => {
    expect(planRoute([AM, OM], AM.id, GSO).map((office) => office.code)).toEqual(['OM', 'GSO']);
    expect(planRoute([AM, OM], OM.id, GSO).map((office) => office.code)).toEqual(['AM', 'GSO']);
  });

  it('leaves out a mandatory office that does not exist rather than block filing', () => {
    expect(planRoute([OM], CTCAO.id, GSO).map((office) => office.code)).toEqual(['OM', 'GSO']);
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
  it('queues Action Mamamayan (current), the Office of the Mayor, then the destination', async () => {
    const result = await fileInternalRequest(filer, {
      destinationDepartmentId: GSO.id,
      purpose: 'Twenty monoblock chairs',
      hasHardCopy: true,
      recipientId: null,
      paperScan: scan,
    });

    const created = stepCreate.mock.calls.map(([{ data }]) => data);
    expect(created.map((step) => [Number(step.department_id), step.status, step.step_order])).toEqual([
      [AM.id, STEP_STATUS.Current, 1],
      [OM.id, STEP_STATUS.Pending, 2],
      [GSO.id, STEP_STATUS.Pending, 3],
    ]);
    expect(created.map((step) => step.action)).toEqual([
      'Validate and confirm',
      'Approve for the Mayor',
      'Review and action',
    ]);

    // Only the first office hears about it now.
    expect(tellTheHop).toHaveBeenCalledTimes(1);
    expect(tellTheHop.mock.calls[0][1]).toMatchObject({ department_id: AM.id });
    expect(result.message).toBe('Filed as INT-20261026-ROUTE1 — it goes to Action Mamamayan first.');
    expect(logSystemComment).toHaveBeenCalledWith(70, expect.stringContaining('route: AM → OM → GSO'));
  });

  it("puts a soft copy's named recipient on the destination's hop, checked against that office", async () => {
    await fileInternalRequest(filer, {
      destinationDepartmentId: GSO.id,
      purpose: 'Twenty monoblock chairs',
      hasHardCopy: false,
      recipientId: 9,
      paperScan: scan,
    });

    expect(isRecipientAt).toHaveBeenCalledWith(9, GSO.id);
    const created = stepCreate.mock.calls.map(([{ data }]) => data);
    expect(created.map((step) => step.recipient_id)).toEqual([null, null, 9n]);
  });

  it('refuses Action Mamamayan as a destination', async () => {
    await expect(
      fileInternalRequest(filer, {
        destinationDepartmentId: AM.id,
        purpose: 'Twenty monoblock chairs',
        hasHardCopy: true,
        recipientId: null,
        paperScan: scan,
      }),
    ).rejects.toThrow('Choose an active office this request is for.');
    expect(documentCreate).not.toHaveBeenCalled();
  });

  it('when Action Mamamayan files, the route starts at the Office of the Mayor', async () => {
    await fileInternalRequest({ ...filer, departmentId: AM.id } as Actor, {
      destinationDepartmentId: GSO.id,
      purpose: 'Program supplies',
      hasHardCopy: true,
      recipientId: null,
      paperScan: scan,
    });

    expect(stepCreate.mock.calls.map(([{ data }]) => Number(data.department_id))).toEqual([OM.id, GSO.id]);
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
