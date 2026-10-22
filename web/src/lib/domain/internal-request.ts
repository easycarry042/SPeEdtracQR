import { db, id, toId } from '@/lib/db';
import { formatNaive, forHumans } from '@/lib/time';
import { routes } from '@/lib/routes';
import { DocumentStatus, isTerminal } from '@/lib/domain/document-status';
import { STEP_STATUS, statusBadge, type RequestStepRow } from '@/lib/domain/request-step';
import {
  canActOnCurrentStep,
  canBeRevisedBy,
  canReceiveCurrentStep,
  currentRequestStep,
  currentStepHasCustody,
  internalStatusBand,
  internalStatusLabel,
  isInternal,
  isSoftCopy,
  statusOf,
} from '@/lib/domain/document';
import { currentCustody, custodyTrail, captureLabel, type CustodyEntry } from '@/lib/domain/custody';
import { activeDepartments, recipients, type FormDepartment, type FormRecipient } from '@/lib/domain/internal-request-form';
import { privateExists } from '@/lib/storage';
import type { Actor } from '@/lib/auth/actor';

/**
 * One internal request, as its page needs it: the chain, the scan trail, and
 * which of the three panels this viewer gets — receive, decide, or revise.
 *
 * Port of `InternalRequestController@show`. Receiving comes first and deciding
 * waits on it: anyone at the holding office may scan a paper folder in, but
 * only the office's supervisor (or a soft copy's named recipient) approves,
 * returns, denies or completes.
 */
export type ChainRow = {
  id: number;
  order: number;
  office: string;
  officeCode: string;
  action: string;
  status: string;
  statusLabel: string;
  band: string;
  actedBy: string | null;
  actedAt: string | null;
  startedAt: string | null;
  recipient: string | null;
  remarks: string | null;
  signatureUrl: string | null;
};

export type InternalRequestDetail = {
  id: number;
  trackingNumber: string;
  purpose: string;
  documentType: string;
  description: string | null;
  status: string;
  statusLabel: string;
  band: string;
  isSoftCopy: boolean;
  filedBy: string | null;
  filedByOffice: string | null;
  filedOn: string;
  qrUrl: string | null;
  stickerUrl: string;
  chain: ChainRow[];
  custodyTrail: CustodyEntry[];
  /** Who holds it now, for the "document in hand" line on the decision card. */
  heldBy: { name: string | null; how: string } | null;
  attachments: Array<{ id: number; url: string; label: string; ext: string }>;
  currentStep: {
    id: number;
    office: string;
    officeCode: string;
    action: string;
    recipient: string | null;
    recipientId: number | null;
    sinceHuman: string | null;
  } | null;
  /** The hop queued when the request was filed, if any — it answers "where next". */
  queuedNext: { office: string; officeCode: string } | null;
  returnedStep: { office: string | null; actedBy: string | null; remarks: string | null } | null;
  canAct: boolean;
  canReceive: boolean;
  canRevise: boolean;
  hasCustody: boolean;
  /** Approving affixes a signature, so the panel says when there is none to affix. */
  hasSignature: boolean;
  forwardDepartments: FormDepartment[];
  forwardRecipients: Record<number, FormRecipient[]>;
};

const DETAIL_SELECT = {
  id: true,
  tracking_number: true,
  document_type: true,
  purpose: true,
  description: true,
  status: true,
  origin: true,
  has_hard_copy: true,
  created_by: true,
  created_at: true,
  qr_code_path: true,
  requesting_department_id: true,
  requestingDepartment: { select: { name: true } },
  creator: { select: { name: true } },
  document_attachments: {
    select: { id: true, file_path: true },
    orderBy: { sort_order: 'asc' },
  },
} as const;

function extensionOf(path: string): string {
  const match = /\.([^.\\/]+)$/.exec(path);

  return match ? match[1].toUpperCase() : '';
}

export async function internalRequestDetail(
  documentId: number,
  actor: Actor | null,
): Promise<InternalRequestDetail | null> {
  const document = await db.documents.findFirst({
    where: { id: id(documentId), deleted_at: null },
    select: DETAIL_SELECT,
  });

  if (!document || !isInternal(document)) {
    return null;
  }

  const requestingDepartmentId =
    document.requesting_department_id === null
      ? null
      : toId(document.requesting_department_id);

  const [steps, step, trail, held, signerRow] = await Promise.all([
    db.request_steps.findMany({
      where: { document_id: document.id },
      orderBy: [{ step_order: 'asc' }, { id: 'asc' }],
      select: {
        id: true,
        step_order: true,
        action: true,
        status: true,
        acted_at: true,
        started_at: true,
        remarks: true,
        signature_path: true,
        recipient_id: true,
        department: { select: { name: true, code: true } },
        actor: { select: { name: true } },
        recipient: { select: { name: true } },
      },
    }),
    currentRequestStep(documentId),
    custodyTrail(documentId),
    currentCustody(documentId),
    actor
      ? db.users.findFirst({ where: { id: id(actor.id) }, select: { signature_path: true } })
      : Promise.resolve(null),
  ]);

  const hasCustody = await currentStepHasCustody(documentId, step);

  const canAct = canActOnCurrentStep(document, step, actor);
  const canReceive = canReceiveCurrentStep(document, step, actor);
  const canRevise = canBeRevisedBy(
    {
      origin: document.origin,
      status: document.status,
      created_by: document.created_by === null ? null : toId(document.created_by),
      requesting_department_id: requestingDepartmentId,
    },
    actor,
  );

  // The office holding the paper decides where it goes next, unless a hop was
  // already queued when the request was filed.
  const queued = steps.find((row) => row.status === STEP_STATUS.Pending) ?? null;

  const returned =
    statusOf(document) === DocumentStatus.Returned
      ? [...steps]
          .filter((row) => row.status === STEP_STATUS.Returned)
          .sort((a, b) => (b.acted_at?.getTime() ?? 0) - (a.acted_at?.getTime() ?? 0))[0] ?? null
      : null;

  // Only offered where a decision can actually be made, so the page does not
  // load every office's staff list for a read-only viewer.
  const needsForwardOptions = canAct && hasCustody && queued === null;
  const forwardDepartments = needsForwardOptions
    ? (await activeDepartments()).filter((office) => office.id !== step?.department_id)
    : [];
  const forwardRecipients =
    needsForwardOptions && isSoftCopy(document)
      ? await recipients(forwardDepartments.map((office) => office.id))
      : {};

  return {
    id: documentId,
    trackingNumber: document.tracking_number,
    purpose: document.purpose ?? document.document_type,
    documentType: document.document_type,
    description: document.description,
    status: document.status,
    statusLabel: internalStatusLabel(
      { status: document.status, requesting_department_id: requestingDepartmentId },
      step,
      hasCustody,
    ),
    band: internalStatusBand(document),
    isSoftCopy: isSoftCopy(document),
    filedBy: document.creator?.name ?? null,
    filedByOffice: document.requestingDepartment?.name ?? null,
    filedOn: document.created_at ? formatNaive(document.created_at, 'M j, Y g:i A') : '—',
    qrUrl: document.qr_code_path ? routes.storage(document.qr_code_path) : null,
    stickerUrl: routes.documentsSticker(documentId),
    chain: steps.map((row) => {
      const badge = statusBadge(row.status);

      return {
        id: toId(row.id),
        order: row.step_order,
        office: row.department.name,
        officeCode: row.department.code,
        action: row.action,
        status: row.status,
        statusLabel: badge.label,
        band: badge.band,
        actedBy: row.actor?.name ?? null,
        actedAt: row.acted_at ? formatNaive(row.acted_at, 'M j, Y g:i A') : null,
        startedAt: row.started_at ? formatNaive(row.started_at, 'M j, Y g:i A') : null,
        recipient: row.recipient?.name ?? null,
        remarks: row.remarks,
        // The frozen signature affixed at approval, served to staff only.
        signatureUrl: row.signature_path ? routes.requestsStepsSignature(toId(row.id)) : null,
      };
    }),
    custodyTrail: trail,
    heldBy: held ? { name: held.userName, how: captureLabel(held.method) } : null,
    attachments: document.document_attachments.map((attachment) => ({
      id: toId(attachment.id),
      url: routes.attachmentsShow(toId(attachment.id)),
      // A stamped copy is a different artefact from the original upload.
      label: attachment.file_path.includes('-qr-stamped.')
        ? 'QR-stamped copy'
        : 'Uploaded document',
      ext: extensionOf(attachment.file_path),
    })),
    currentStep: step
      ? {
          id: step.id,
          office: step.departmentName,
          officeCode: step.departmentCode,
          action: step.action,
          recipient: step.recipientName,
          recipientId: step.recipient_id,
          sinceHuman: step.started_at ? forHumans(step.started_at) : null,
        }
      : null,
    queuedNext: queued
      ? { office: queued.department.name, officeCode: queued.department.code }
      : null,
    returnedStep: returned
      ? {
          office: returned.department.name,
          actedBy: returned.actor?.name ?? null,
          remarks: returned.remarks,
        }
      : null,
    canAct,
    canReceive,
    canRevise,
    hasCustody,
    hasSignature: await privateExists(signerRow?.signature_path ?? null),
    forwardDepartments,
    forwardRecipients,
  };
}

/** Whether this request is finished, for the "nothing pending" line. */
export function isClosedRequest(detail: Pick<InternalRequestDetail, 'status'>): boolean {
  return isTerminal(statusOf(detail));
}

export type { RequestStepRow };
