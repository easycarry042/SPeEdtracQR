import { db, id, toId } from '@/lib/db';
import { formatNaive } from '@/lib/time';
import { tracking as trackingConfig } from '@/lib/config';
import { DocumentStatus } from '@/lib/domain/document-status';
import {
  currentRequestStep,
  currentStepHasCustody,
  internalStatusBand,
  internalStatusLabel,
  statusOf,
} from '@/lib/domain/document';
import { STEP_STATUS } from '@/lib/domain/request-step';

/**
 * What anyone holding an internal request's QR or tracking number may see,
 * without signing in: where it stands, which office has it, who there is
 * handling it, and the offices it has been through.
 *
 * The client asked for "who is handling it and which department". Nothing else
 * leaves the building: not what is being requested, not remarks, not the files
 * — a tracking number is printed on a folder anyone can pick up.
 */
export type InternalSummary = {
  showsNames: boolean;
  tracking: string;
  status: string;
  band: string;
  filedBy: string | null;
  filedOn: string;
  now: { office: string | null; person: string | null; note: string };
  steps: Array<{
    office: string | null;
    code: string | null;
    state: string;
    when: string | null;
    current: boolean;
  }>;
};

const STEP_STATE: Record<string, string> = {
  [STEP_STATUS.Current]: 'With this office now',
  [STEP_STATUS.Approved]: 'Approved and passed on',
  [STEP_STATUS.Forwarded]: 'Handled and passed on',
  [STEP_STATUS.Returned]: 'Returned for revision',
  [STEP_STATUS.Denied]: 'Denied',
};

export async function summary(trackingNumber: string): Promise<InternalSummary | null> {
  const document = await db.documents.findFirst({
    where: { tracking_number: trackingNumber, origin: 'internal', deleted_at: null },
    select: {
      id: true,
      tracking_number: true,
      status: true,
      origin: true,
      has_hard_copy: true,
      created_at: true,
      completed_at: true,
      requesting_department_id: true,
      requestingDepartment: { select: { name: true } },
      creator: { select: { name: true } },
      request_steps: {
        orderBy: [{ step_order: 'asc' }, { id: 'asc' }],
        select: {
          status: true,
          acted_at: true,
          started_at: true,
          department: { select: { name: true, code: true } },
        },
      },
    },
  });

  if (!document) {
    return null;
  }

  const documentId = toId(document.id);

  // Resolved once: the label, the custody check and "where is it now" all need
  // the same hop, and it is two queries each time.
  const step = await currentRequestStep(documentId);
  const hasCustody = await currentStepHasCustody(documentId, step);

  // Names are the client's ask; the config switch turns them off for
  // offices-only, should legal say so.
  const showsNames = trackingConfig.publicInternalTracker.showNames;
  const current = await whereItIsNow(documentId, document, step, hasCustody);

  if (!showsNames) {
    current.person = null;
    current.note = current.note.replace('Sent to them', 'Sent to someone there');
  }

  return {
    showsNames,
    tracking: document.tracking_number,
    status: internalStatusLabel(
      {
        status: document.status,
        requesting_department_id:
          document.requesting_department_id === null
            ? null
            : toId(document.requesting_department_id),
      },
      step,
      hasCustody,
    ),
    band: internalStatusBand(document),
    filedBy: document.requestingDepartment?.name ?? null,
    filedOn: document.created_at ? formatNaive(document.created_at, 'M j, Y g:i A') : '',
    now: current,
    steps: document.request_steps.map((step) => ({
      office: step.department?.name ?? null,
      code: step.department?.code ?? null,
      state: STEP_STATE[step.status] ?? 'Not reached',
      when: (step.acted_at ?? step.started_at)
        ? formatNaive((step.acted_at ?? step.started_at)!, 'M j, g:i A')
        : null,
      current: step.status === STEP_STATUS.Current,
    })),
  };
}

type SummarySource = {
  status: string;
  completed_at: Date | null;
  requestingDepartment: { name: string } | null;
  creator: { name: string } | null;
};

/** Who has it right now, in words. */
async function whereItIsNow(
  documentId: number,
  document: SummarySource,
  step: Awaited<ReturnType<typeof currentRequestStep>>,
  hasCustody: boolean,
): Promise<{ office: string | null; person: string | null; note: string }> {
  const stage = statusOf(document);

  if (stage === DocumentStatus.Completed) {
    return {
      office: null,
      person: null,
      note: `Completed${document.completed_at ? ` on ${formatNaive(document.completed_at, 'M j, Y')}` : ''}.`,
    };
  }

  if (stage === DocumentStatus.Denied) {
    return { office: null, person: null, note: 'This request was denied.' };
  }

  if (stage === DocumentStatus.Withdrawn) {
    return {
      office: null,
      person: null,
      note: 'The office that filed it withdrew this request.',
    };
  }

  if (stage === DocumentStatus.Returned) {
    return {
      office: document.requestingDepartment?.name ?? null,
      person: document.creator?.name ?? null,
      note: 'Returned to the office that filed it, for revision.',
    };
  }

  if (!step) {
    return { office: null, person: null, note: 'Not with any office right now.' };
  }

  if (hasCustody) {
    const holder = await db.document_custody_events.findFirst({
      where: { document_id: id(documentId) },
      orderBy: { id: 'desc' },
      select: { created_at: true, user: { select: { name: true } } },
    });

    return {
      office: step.departmentName,
      person: holder?.user?.name ?? null,
      note: `Received ${holder?.created_at ? formatNaive(holder.created_at, 'M j, g:i A') : ''}.`,
    };
  }

  if (step.recipientName) {
    return {
      office: step.departmentName,
      person: step.recipientName,
      note: 'Sent to them — not received yet.',
    };
  }

  return {
    office: step.departmentName,
    person: null,
    note: 'On its way — nobody at the office has received it yet.',
  };
}
