import type { Prisma } from '@prisma/client';
import { db, id, toId } from '@/lib/db';
import { formatNaive } from '@/lib/time';
import type { Actor } from '@/lib/auth/actor';
import { DocumentStatus, activeValues } from '@/lib/domain/document-status';
import { ORIGIN_INTERNAL } from '@/lib/domain/scope';
import { STEP_STATUS } from '@/lib/domain/request-step';
import {
  currentRequestStep,
  currentStepHasCustody,
  internalStatusBand,
  internalStatusLabel,
  isSoftCopy,
} from '@/lib/domain/document';

/**
 * One office's internal (office-to-office) requests, in the three queues the
 * Internal page draws: what awaits the office, what it filed and is still open,
 * and what has closed.
 *
 * An account with no office (a super admin) sees every open request and every
 * closed one.
 */
export type InboxRow = {
  id: number;
  tracking: string;
  purpose: string | null;
  filedBy: string | null;
  filedByPerson: string | null;
  filedOn: string;
  status: string;
  statusLabel: string;
  band: string;
  isSoftCopy: boolean;
  /** Where it is sitting now, and whether anyone there has taken it in. */
  atOffice: string | null;
  recipient: string | null;
  received: boolean;
};

export type Inbox = {
  departmentName: string | null;
  awaiting: InboxRow[];
  filed: InboxRow[];
  closed: InboxRow[];
  search: string;
};

const SELECT = {
  id: true,
  tracking_number: true,
  purpose: true,
  status: true,
  origin: true,
  has_hard_copy: true,
  created_at: true,
  requesting_department_id: true,
  requestingDepartment: { select: { name: true } },
  creator: { select: { name: true } },
} satisfies Prisma.documentsSelect;

type Source = {
  id: bigint;
  tracking_number: string;
  purpose: string | null;
  status: string;
  origin: string;
  has_hard_copy: boolean;
  created_at: Date | null;
  requesting_department_id: bigint | null;
  requestingDepartment: { name: string } | null;
  creator: { name: string } | null;
};

async function toRow(source: Source): Promise<InboxRow> {
  const documentId = toId(source.id);
  const step = await currentRequestStep(documentId);
  const received = await currentStepHasCustody(documentId, step);

  return {
    id: documentId,
    tracking: source.tracking_number,
    purpose: source.purpose,
    filedBy: source.requestingDepartment?.name ?? null,
    filedByPerson: source.creator?.name ?? null,
    filedOn: source.created_at ? formatNaive(source.created_at, 'M j, Y') : '',
    status: source.status,
    statusLabel: internalStatusLabel(
      {
        status: source.status,
        requesting_department_id:
          source.requesting_department_id === null ? null : toId(source.requesting_department_id),
      },
      step,
      received,
    ),
    band: internalStatusBand(source),
    isSoftCopy: isSoftCopy(source),
    atOffice: step?.departmentName ?? null,
    recipient: step?.recipientName ?? null,
    received,
  };
}

export async function inboxFor(actor: Actor, search = ''): Promise<Inbox> {
  const trimmed = search.trim();
  const departmentId = actor.departmentId;

  const base: Prisma.documentsWhereInput = {
    origin: ORIGIN_INTERNAL,
    deleted_at: null,
    ...(trimmed === ''
      ? {}
      : {
          OR: [
            { tracking_number: { contains: trimmed } },
            { purpose: { contains: trimmed } },
          ],
        }),
  };

  const active = { in: activeValues() };
  const terminal = {
    in: [DocumentStatus.Completed, DocumentStatus.Denied, DocumentStatus.Withdrawn],
  };

  const order: Prisma.documentsOrderByWithRelationInput = { created_at: 'desc' };

  if (departmentId === null) {
    const [awaiting, closed] = await Promise.all([
      db.documents.findMany({ where: { ...base, status: active }, orderBy: order, select: SELECT, take: 200 }),
      db.documents.findMany({ where: { ...base, status: terminal }, orderBy: order, select: SELECT, take: 200 }),
    ]);

    return {
      departmentName: null,
      awaiting: await Promise.all(awaiting.map(toRow)),
      filed: [],
      closed: await Promise.all(closed.map(toRow)),
      search: trimmed,
    };
  }

  const key = id(departmentId);

  const [awaiting, filed, closed] = await Promise.all([
    // Sitting at this office right now.
    db.documents.findMany({
      where: {
        ...base,
        status: active,
        request_steps: { some: { status: STEP_STATUS.Current, department_id: key } },
      },
      orderBy: order,
      select: SELECT,
      take: 200,
    }),

    // Filed by this office and still open.
    db.documents.findMany({
      where: { ...base, status: active, requesting_department_id: key },
      orderBy: order,
      select: SELECT,
      take: 200,
    }),

    // Closed history stays office-scoped: filed here or passed through here.
    db.documents.findMany({
      where: {
        ...base,
        status: terminal,
        OR: [
          { requesting_department_id: key },
          { request_steps: { some: { department_id: key } } },
        ],
      },
      orderBy: order,
      select: SELECT,
      take: 200,
    }),
  ]);

  return {
    departmentName: actor.departmentName,
    awaiting: await Promise.all(awaiting.map(toRow)),
    filed: await Promise.all(filed.map(toRow)),
    closed: await Promise.all(closed.map(toRow)),
    search: trimmed,
  };
}
