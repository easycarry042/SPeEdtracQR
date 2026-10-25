import { beforeEach, describe, expect, it, vi } from 'vitest';
import { DocumentStatus } from '@/lib/domain/document-status';
import { STEP_STATUS } from '@/lib/domain/request-step';
import type { Actor } from '@/lib/auth/actor';

/**
 * Ports the gate half of `InternalRequestFlowTest`,
 * `InternalRequestScanOverrideTest` and `SoftCopyInternalRequestTest`.
 *
 * These are the rules that make an endorsement mean something, so they are
 * worth guarding one by one:
 *
 *  - no decision before the office has the folder (custody);
 *  - the QR scanned must be THIS request's, and only codes we issued count;
 *  - no approval without a registered e-signature, frozen as of that approval;
 *  - approving forwards, it never completes.
 */
const documentFindFirst = vi.fn();
const documentUpdate = vi.fn();
const stepFindFirst = vi.fn();
const stepUpdate = vi.fn();
const stepUpdateMany = vi.fn();
const stepCreate = vi.fn();
const stepAggregate = vi.fn();
const stepCount = vi.fn();
const departmentFindFirst = vi.fn();
const userFindFirst = vi.fn();
const custodyCreate = vi.fn();
const attachmentAggregate = vi.fn();
const attachmentCreate = vi.fn();

const currentRequestStep = vi.fn();
const currentStepHasCustody = vi.fn();
const logOnDocument = vi.fn();
const logSystemComment = vi.fn();
const notify = vi.fn();
const notifyMany = vi.fn();
const tellTheHop = vi.fn();
const privateExists = vi.fn();
const readPrivate = vi.fn();
const putPrivate = vi.fn();
const isRecipientAt = vi.fn();

vi.mock('@/lib/db', () => ({
  db: {
    documents: { findFirst: documentFindFirst, update: documentUpdate },
    request_steps: {
      findFirst: stepFindFirst,
      update: stepUpdate,
      updateMany: stepUpdateMany,
      create: stepCreate,
      aggregate: stepAggregate,
      count: stepCount,
    },
    departments: { findFirst: departmentFindFirst },
    users: { findFirst: userFindFirst },
    document_custody_events: { create: custodyCreate },
    document_attachments: { aggregate: attachmentAggregate, create: attachmentCreate },
  },
  toId: (value: bigint | number) => Number(value),
  id: (value: number) => BigInt(value),
}));

// The real module is exercised by its own tests; here only the two gate
// queries are stubbed so each rule can be driven in isolation.
vi.mock('@/lib/domain/document', async () => {
  const actual = await vi.importActual<typeof import('@/lib/domain/document')>(
    '@/lib/domain/document',
  );

  return { ...actual, currentRequestStep, currentStepHasCustody };
});

vi.mock('@/lib/activity', () => ({ logOnDocument, logActivity: vi.fn() }));
vi.mock('@/lib/domain/comments', () => ({ logSystemComment }));
vi.mock('@/lib/domain/notifications', () => ({ notify, notifyMany }));
vi.mock('@/lib/domain/internal-events', () => ({
  tellTheHop,
  internalOutcome: (document: { tracking_number: string }, summary: string) => ({
    title: 'Internal request update',
    body: `${document.tracking_number} — ${summary}`,
  }),
}));
vi.mock('@/lib/storage', () => ({ privateExists, readPrivate, putPrivate, storePrivate: vi.fn() }));
vi.mock('@/lib/domain/internal-request-form', () => ({ isRecipientAt }));

const steps = await import('@/lib/domain/internal-steps');

const TRACKING = 'INT-20261022-ABC123';

function actor(overrides: Partial<Actor> = {}): Actor {
  return {
    id: 5,
    name: 'Sal Supervisor',
    departmentId: 3,
    departmentName: 'Accounting Office',
    can: (permission: string) => permission === 'act on internal requests',
    hasRole: () => false,
    ...overrides,
  } as unknown as Actor;
}

function paperRequest(overrides: Record<string, unknown> = {}) {
  return {
    id: 7n,
    tracking_number: TRACKING,
    document_type: 'Internal Request',
    purpose: 'Twenty monoblock chairs',
    status: DocumentStatus.Pending,
    origin: 'internal',
    has_hard_copy: true,
    created_by: 9n,
    requesting_department_id: 2n,
    ...overrides,
  };
}

/** The hop the office is holding. */
function heldStep(overrides: Record<string, unknown> = {}) {
  return {
    id: 11,
    document_id: 7,
    step_order: 1,
    department_id: 3,
    recipient_id: null,
    action: 'Review and action',
    status: STEP_STATUS.Current,
    acted_by: null,
    acted_at: null,
    started_at: new Date('2026-10-20T01:00:00Z'),
    remarks: null,
    signature_path: null,
    departmentName: 'Accounting Office',
    departmentCode: 'ACC',
    recipientName: null,
    actorName: null,
    ...overrides,
  };
}

beforeEach(() => {
  vi.clearAllMocks();

  documentFindFirst.mockResolvedValue(paperRequest());
  currentRequestStep.mockResolvedValue(heldStep());
  currentStepHasCustody.mockResolvedValue(true);
  stepFindFirst.mockResolvedValue(null);
  stepAggregate.mockResolvedValue({ _max: { step_order: 1 } });
  stepCount.mockResolvedValue(0);
  departmentFindFirst.mockResolvedValue({ id: 4n, name: 'Office of the Mayor' });
  userFindFirst.mockResolvedValue({ signature_path: 'signatures/sal.png' });
  privateExists.mockResolvedValue(true);
  readPrivate.mockResolvedValue(Buffer.from('png'));
  isRecipientAt.mockResolvedValue(true);
  stepCreate.mockResolvedValue({
    id: 12n,
    department_id: 4n,
    recipient_id: null,
    action: 'Review and action',
    department: { name: 'Office of the Mayor' },
  });
  stepUpdate.mockResolvedValue({
    id: 12n,
    department_id: 4n,
    recipient_id: null,
    action: 'Review and action',
    department: { name: 'Office of the Mayor' },
  });
});

const decision = { documentScan: TRACKING, nextDepartmentId: 4 };

describe('the custody gate', () => {
  it('refuses every decision until the office has the folder', async () => {
    currentStepHasCustody.mockResolvedValue(false);

    await expect(steps.approve(7, actor(), decision)).rejects.toThrow(/take custody/i);
    await expect(steps.complete(7, actor(), { documentScan: TRACKING })).rejects.toThrow(
      /take custody/i,
    );
    await expect(
      steps.deny(7, actor(), { documentScan: TRACKING, remarks: 'No budget.' }),
    ).rejects.toThrow(/take custody/i);

    expect(stepUpdate).not.toHaveBeenCalled();
  });

  it('tells a soft copy to be received rather than scanned in', async () => {
    documentFindFirst.mockResolvedValue(paperRequest({ has_hard_copy: false }));
    currentStepHasCustody.mockResolvedValue(false);

    await expect(steps.approve(7, actor(), decision)).rejects.toThrow(/receive this request/i);
  });

  it('refuses someone from another office outright', async () => {
    await expect(steps.approve(7, actor({ departmentId: 9 }), decision)).rejects.toThrow(
      /unauthorized/i,
    );
  });

  it('refuses plain staff at the holding office — receiving is not deciding', async () => {
    const plain = actor({ can: () => false });

    await expect(steps.approve(7, plain, decision)).rejects.toThrow(/unauthorized/i);
  });

  it('lets a soft copy’s named recipient act even as plain staff', async () => {
    documentFindFirst.mockResolvedValue(paperRequest({ has_hard_copy: false }));
    currentRequestStep.mockResolvedValue(heldStep({ recipient_id: 5 }));

    const recipient = actor({ can: () => false });

    await expect(steps.complete(7, recipient, {})).resolves.toEqual({
      message: 'Marked as done — the request is complete.',
    });
  });
});

describe('the folder QR', () => {
  it('refuses a code from a different request', async () => {
    await expect(
      steps.approve(7, actor(), { ...decision, documentScan: 'INT-20261022-ZZZ999' }),
    ).rejects.toThrow(/different request \(INT-20261022-ZZZ999\)/);
  });

  it('refuses a foreign QR that merely mentions a tracking number', async () => {
    await expect(
      steps.approve(7, actor(), { ...decision, documentScan: `https://evil.test/${TRACKING}` }),
    ).rejects.toThrow(/not issued by SPeED TraQR/i);
  });

  it('refuses a decision with neither a scan nor a reason', async () => {
    await expect(steps.approve(7, actor(), { nextDepartmentId: 4 })).rejects.toThrow(
      /scan this request's QR code/i,
    );
  });

  it('accepts a written override, and records it where it can be seen', async () => {
    await expect(
      steps.approve(7, actor(), {
        nextDepartmentId: 4,
        scanOverrideReason: 'Sticker torn on the folder cover; checked the number by hand.',
      }),
    ).resolves.toMatchObject({ message: expect.stringContaining('Approved') });

    expect(logOnDocument).toHaveBeenCalledWith(
      7,
      expect.stringContaining('WITHOUT a QR scan'),
      5,
      expect.objectContaining({ tracking_number: TRACKING }),
    );
    expect(logSystemComment).toHaveBeenCalledWith(7, expect.stringContaining('without scanning'));
  });

  it('refuses a one-word override — the reason is the whole point', async () => {
    await expect(
      steps.approve(7, actor(), { nextDepartmentId: 4, scanOverrideReason: 'torn' }),
    ).rejects.toThrow(/explain briefly/i);
  });

  it('asks a soft copy for nothing to scan', async () => {
    documentFindFirst.mockResolvedValue(paperRequest({ has_hard_copy: false }));
    isRecipientAt.mockResolvedValue(true);

    await expect(
      steps.approve(7, actor(), { nextDepartmentId: 4, nextRecipientId: 8 }),
    ).resolves.toMatchObject({ message: expect.stringContaining('Approved') });
  });
});

describe('approving', () => {
  it('needs an onward office when none was queued at filing', async () => {
    await expect(steps.approve(7, actor(), { documentScan: TRACKING })).rejects.toThrow(
      /goes to next/i,
    );
  });

  it('refuses to forward a request to the office already holding it', async () => {
    await expect(
      steps.approve(7, actor(), { documentScan: TRACKING, nextDepartmentId: 3 }),
    ).rejects.toThrow(/already with yours/i);
  });

  it('takes the hop queued at filing over any office chosen now', async () => {
    stepFindFirst.mockResolvedValue({
      id: 12n,
      department_id: 4n,
      action: 'Review and action',
      recipient_id: null,
    });

    await steps.approve(7, actor(), { documentScan: TRACKING });

    // The queued hop is opened, not a new one appended.
    expect(stepUpdate).toHaveBeenCalledWith(
      expect.objectContaining({ data: expect.objectContaining({ status: STEP_STATUS.Current }) }),
    );
    expect(stepCreate).not.toHaveBeenCalled();
  });

  it('needs a named recipient for a soft copy, who must work at that office', async () => {
    documentFindFirst.mockResolvedValue(paperRequest({ has_hard_copy: false }));

    await expect(steps.approve(7, actor(), { nextDepartmentId: 4 })).rejects.toThrow(
      /who in the next office receives it/i,
    );

    isRecipientAt.mockResolvedValue(false);

    await expect(
      steps.approve(7, actor(), { nextDepartmentId: 4, nextRecipientId: 99 }),
    ).rejects.toThrow(/works in the next office/i);
  });

  it('refuses without a registered e-signature', async () => {
    privateExists.mockResolvedValue(false);

    await expect(steps.approve(7, actor(), decision)).rejects.toThrow(/e-signature/i);
    expect(stepUpdate).not.toHaveBeenCalled();
  });

  it('freezes a copy of the signature against that hop', async () => {
    await steps.approve(7, actor(), decision);

    expect(putPrivate).toHaveBeenCalledWith(
      `request-signatures/${TRACKING}-step-11.png`,
      expect.anything(),
      'image/png',
    );
    expect(stepUpdate).toHaveBeenCalledWith(
      expect.objectContaining({
        data: expect.objectContaining({
          status: STEP_STATUS.Approved,
          signature_path: `request-signatures/${TRACKING}-step-11.png`,
        }),
      }),
    );
  });

  it('never completes the request, however long the chain gets', async () => {
    await steps.approve(7, actor(), decision);

    const statuses = documentUpdate.mock.calls.map((call) => call[0].data.status);

    expect(statuses).toContain(DocumentStatus.InProgress);
    expect(statuses).not.toContain(DocumentStatus.Completed);
  });

  it('tells the next hop it has arrived', async () => {
    await steps.approve(7, actor(), decision);

    expect(tellTheHop).toHaveBeenCalledWith(
      expect.objectContaining({ tracking_number: TRACKING }),
      expect.objectContaining({ department_id: 4 }),
      5,
    );
  });
});

describe('finishing and halting', () => {
  it('Done is the only path to Completed, and clears what was queued behind it', async () => {
    await steps.complete(7, actor(), { documentScan: TRACKING });

    expect(stepUpdateMany).toHaveBeenCalledWith(
      expect.objectContaining({ data: expect.objectContaining({ status: STEP_STATUS.Skipped }) }),
    );
    expect(documentUpdate.mock.calls.at(-1)?.[0].data).toMatchObject({
      status: DocumentStatus.Completed,
    });
  });

  it('refuses Done while the filed route still has offices to go (Action Mamamayan → OM → destination)', async () => {
    stepFindFirst.mockResolvedValueOnce({ department: { name: 'Office of the Mayor' } });

    await expect(steps.complete(7, actor(), { documentScan: TRACKING })).rejects.toThrow(
      'This request still goes to Office of the Mayor. Approve it to pass it on',
    );
    expect(documentUpdate).not.toHaveBeenCalled();
    expect(stepUpdateMany).not.toHaveBeenCalled();
  });

  it('attributes a denial so the tracker can name who decided', async () => {
    await steps.deny(7, actor(), { documentScan: TRACKING, remarks: 'Outside the budget line.' });

    expect(documentUpdate.mock.calls.at(-1)?.[0].data).toMatchObject({
      status: DocumentStatus.Denied,
      decided_by: 5n,
    });
  });

  it('refuses a denial or a return with no reason', async () => {
    await expect(steps.deny(7, actor(), { documentScan: TRACKING })).rejects.toThrow(
      /explain the decision/i,
    );
    await expect(steps.returnToRequester(7, actor(), { documentScan: TRACKING })).rejects.toThrow(
      /explain the decision/i,
    );
  });

  it('tells whoever filed it what happened', async () => {
    await steps.returnToRequester(7, actor(), {
      documentScan: TRACKING,
      remarks: 'Attach the canvass.',
    });

    expect(notify).toHaveBeenCalledWith(9, expect.objectContaining({ body: expect.any(String) }));
  });

  it('does not bell the filer about their own decision', async () => {
    documentFindFirst.mockResolvedValue(paperRequest({ created_by: 5n }));

    await steps.complete(7, actor(), { documentScan: TRACKING });

    expect(notify).not.toHaveBeenCalled();
  });
});
