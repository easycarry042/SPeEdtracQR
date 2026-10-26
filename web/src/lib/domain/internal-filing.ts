import { db, id, toId } from '@/lib/db';
import { now } from '@/lib/time';
import { storePrivate } from '@/lib/storage';
import { logSystemComment } from '@/lib/domain/comments';
import { ActionError } from '@/lib/domain/status-actions';
import { DocumentStatus } from '@/lib/domain/document-status';
import { statusChangeData } from '@/lib/domain/document';
import { ORIGIN_INTERNAL } from '@/lib/domain/scope';
import { STEP_STATUS } from '@/lib/domain/request-step';
import * as qr from '@/lib/domain/qr-service';
import { checkPaperScan } from '@/lib/domain/upload-rules';
import { isRecipientAt, mandatoryRouteOffices } from '@/lib/domain/internal-request-form';
import {
  ROUTE_DIRECT,
  ROUTE_THROUGH,
  isDestination,
  planRoute,
  type RouteMode,
} from '@/lib/domain/internal-route-plan';
import { tellTheHop } from '@/lib/domain/internal-events';
import { ensureStampedCopy, NO_PLACEMENT, type StampPlacement } from '@/lib/domain/qr-stamp';
import type { Actor } from '@/lib/auth/actor';

/**
 * Filing an internal office-to-office request.
 *
 * The filer sends it either through Action Mamamayan and the Office of the
 * Mayor (`internal-route-plan`), where the Mayor's office chooses the office it
 * goes to next, or straight to an office of their choosing. The route is queued
 * at filing — the first office's hop current, the rest pending — and approving
 * a hop opens the next one. Past the queued route, the office holding it passes
 * it on or marks it done.
 *
 * The signed paper IS the request, so the scan is required: filing without it
 * leaves the receiving office nothing to act on.
 */
export type FilingInput = {
  /** Through Action Mamamayan and the Mayor (the default), or straight to an office. */
  route?: RouteMode;
  /** The office it goes straight to; ignored when it goes through the Mayor. */
  destinationDepartmentId: number | null;
  purpose: string;
  /** Paper carried along the route, or a soft copy. */
  hasHardCopy: boolean;
  /**
   * Who receives a soft copy at the office it goes straight to (required when
   * there is no paper). Through the Mayor nobody is named at filing: anyone at
   * Action Mamamayan and the Office of the Mayor who decides on internal
   * requests receives it, and the Mayor's office names the next recipient.
   */
  recipientId: number | null;
  paperScan: File | null;
  /** Where the filer dragged the QR on the scan; null fields mean bottom-right. */
  qrPlacement?: StampPlacement;
};

const PURPOSE_LIMIT = 255;

/**
 * Internal requests are filed on behalf of an office, so the filer must belong
 * to an active one — an org-wide account has no office to file from.
 */
async function requireDepartment(actor: Actor | null): Promise<{ id: number; name: string }> {
  if (!actor || !actor.can('create internal requests')) {
    throw new ActionError('This action is unauthorized.', 403);
  }

  if (actor.departmentId === null) {
    throw new ActionError(
      'Your account has no active department, so you cannot file internal requests. Ask a system administrator to assign one.',
      403,
    );
  }

  const office = await db.departments.findFirst({
    where: { id: id(actor.departmentId), is_active: true },
    select: { id: true, name: true },
  });

  if (!office) {
    throw new ActionError(
      'Your account has no active department, so you cannot file internal requests. Ask a system administrator to assign one.',
      403,
    );
  }

  return { id: toId(office.id), name: office.name };
}

export async function fileInternalRequest(
  actor: Actor | null,
  input: FilingInput,
): Promise<{ message: string; url: string }> {
  const office = await requireDepartment(actor);
  const filer = actor as Actor;

  const purpose = input.purpose.trim();

  if (purpose === '') {
    throw new ActionError('Say what is being requested.');
  }

  if (purpose.length > PURPOSE_LIMIT) {
    throw new ActionError(`Keep the purpose to ${PURPOSE_LIMIT} characters.`);
  }

  const mode: RouteMode = input.route ?? ROUTE_THROUGH;
  let destination: { id: number; name: string; code: string } | null = null;

  if (mode === ROUTE_DIRECT) {
    const destinationRow =
      input.destinationDepartmentId === null
        ? null
        : await db.departments.findFirst({
            where: { id: id(input.destinationDepartmentId), is_active: true },
            select: { id: true, name: true, code: true },
          });

    if (!destinationRow || !isDestination(destinationRow)) {
      throw new ActionError('Choose an active office to send this request to.');
    }

    destination = { id: toId(destinationRow.id), name: destinationRow.name, code: destinationRow.code };
  }

  const route = planRoute(await mandatoryRouteOffices(), office.id, mode, destination);

  if (route.length === 0) {
    throw new ActionError(
      'Action Mamamayan and the Office of the Mayor are not set up yet. Send it straight to an office instead.',
    );
  }

  const first = route[0];

  // Only a request sent straight to an office names its recipient at filing.
  const recipientId = input.hasHardCopy || destination === null ? null : input.recipientId;

  if (!input.hasHardCopy && destination !== null) {
    if (recipientId === null) {
      throw new ActionError(
        `Without a hard copy, choose who at ${destination.name} receives this request.`,
      );
    }

    if (!(await isRecipientAt(recipientId, destination.id))) {
      throw new ActionError(`Choose someone who works at ${destination.name}.`);
    }
  }

  const scanProblem = checkPaperScan(input.paperScan);

  if (scanProblem !== null) {
    throw new ActionError(scanProblem);
  }

  // INT- so internal requests read distinctly from citizen tickets (SPD-).
  const tracking = await qr.generateTrackingNumber('INT');

  const document = await db.documents.create({
    data: {
      tracking_number: tracking,
      document_type: 'Internal Request',
      purpose,
      ...statusChangeData(DocumentStatus.Pending),
      source: 'walk_in',
      origin: ORIGIN_INTERNAL,
      has_hard_copy: input.hasHardCopy,
      requesting_department_id: id(office.id),
      created_by: id(filer.id),
      created_at: now(),
      updated_at: now(),
    },
    select: { id: true },
  });

  const documentId = toId(document.id);

  try {
    // The whole route, queued now: the first hop current, the rest waiting.
    // (step_order 0 was the filing office's own endorsement hop, which older
    // requests may still carry.) A soft copy's named recipient belongs to the
    // destination's hop.
    const steps = [];

    for (const [index, hop] of route.entries()) {
      const isFirst = index === 0;
      const isDestinationHop = destination !== null && hop.id === destination.id;

      steps.push(
        await db.request_steps.create({
          data: {
            document_id: document.id,
            step_order: index + 1,
            department_id: id(hop.id),
            recipient_id: isDestinationHop && recipientId !== null ? id(recipientId) : null,
            action: isDestinationHop ? 'Review and action' : hopAction(hop.code),
            status: isFirst ? STEP_STATUS.Current : STEP_STATUS.Pending,
            started_at: isFirst ? now() : null,
            created_at: now(),
            updated_at: now(),
          },
          select: { id: true, department_id: true, recipient_id: true, action: true },
        }),
      );
    }

    const step = steps[0];

    // The filing office physically holds the paper it just created, so the trail
    // starts with it. The receiving office still scans the folder to take custody
    // before acting. A soft copy has no paper, so no trail.
    if (input.hasHardCopy) {
      await db.document_custody_events.create({
        data: {
          document_id: document.id,
          user_id: id(filer.id),
          capture_method: 'manual',
          override_reason: 'Filing office holds the paper at submission.',
          created_at: now(),
          updated_at: now(),
        },
      });
    }

    const generated = await qr.generateAndStore(tracking);

    if (generated.success && generated.relativePath) {
      await db.documents.update({
        where: { id: document.id },
        data: { qr_code_path: generated.relativePath, updated_at: now() },
      });
    }

    if (input.paperScan) {
      const path = await storePrivate(input.paperScan, 'document-attachments');

      await db.document_attachments.create({
        data: {
          document_id: document.id,
          file_path: path,
          uploaded_by: id(filer.id),
          sort_order: 0,
          created_at: now(),
          updated_at: now(),
        },
      });

      // Archive a digital copy with the QR stamped on, beside the untouched
      // original. Never fatal: the request stands without it.
      await ensureStampedCopy(documentId, input.qrPlacement ?? NO_PLACEMENT, filer.id);
    }

    const recipientName = recipientId === null ? null : await nameOf(recipientId);

    const routeLine = route.map((hop) => hop.code).join(' → ');

    await logSystemComment(
      documentId,
      destination === null
        ? `Internal request filed by ${filer.name} (${office.name}) through Action Mamamayan and the Mayor — route: ${routeLine}, then the office the Mayor's office chooses${input.hasHardCopy ? '' : ' — soft copy only'}.`
        : `Internal request filed by ${filer.name} (${office.name}) straight to ${destination.name} — route: ${routeLine}${input.hasHardCopy ? '' : ` — soft copy only, received at ${destination.code} by ${recipientName ?? 'the office'}`}.`,
    );

    // Ping the first hop: its supervisors, and for a soft copy the person named
    // to receive it. A paper request is on its way to them; a soft copy is
    // already there.
    await tellTheHop(
      { id: documentId, tracking_number: tracking, document_type: 'Internal Request', purpose },
      {
        department_id: toId(step.department_id),
        recipient_id: step.recipient_id === null ? null : toId(step.recipient_id),
        action: step.action,
      },
      filer.id,
    );

    return {
      message:
        destination === null
          ? `Filed as ${tracking} — it goes to ${first.name} first.`
          : `Filed as ${tracking} — it goes straight to ${destination.name}.`,
      url: `/requests/${documentId}/created`,
    };
  } catch (error) {
    // If anything fails downstream, clean up partial database rows
    await db.document_attachments.deleteMany({ where: { document_id: document.id } }).catch(() => {});
    await db.document_custody_events.deleteMany({ where: { document_id: document.id } }).catch(() => {});
    await db.request_steps.deleteMany({ where: { document_id: document.id } }).catch(() => {});
    await db.documents.delete({ where: { id: document.id } }).catch(() => {});

    throw error;
  }
}

async function nameOf(userId: number): Promise<string | null> {
  const user = await db.users.findFirst({ where: { id: id(userId) }, select: { name: true } });

  return user?.name ?? null;
}

/** What each mandatory office does with a request on its way through. */
function hopAction(code: string): string {
  return code === 'AM' ? 'Validate and confirm' : code === 'OM' ? 'Approve for the Mayor' : 'Review and action';
}
