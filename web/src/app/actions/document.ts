'use server';

import { revalidatePath } from 'next/cache';
import { currentActor } from '@/lib/auth/actor';
import { AuthError } from '@/lib/auth/actor';
import { ActionError } from '@/lib/domain/status-actions';
import { GateError } from '@/lib/domain/status-gate';
import * as status from '@/lib/domain/status-actions';
import * as review from '@/lib/domain/review-actions';
import { nudge } from '@/lib/domain/nudge';
import { reviewRequirement as reviewOneRequirement, type ReviewRequirement } from '@/lib/domain/requirement-review';

/**
 * Server actions behind the Review modal's controls.
 *
 * Every one of them returns `{ ok, message, errors }` rather than throwing into
 * the client: the modal shows stage-gate failures inline, exactly as the Blade
 * version did — no alerts, and never a lost form.
 */
export type ActionResult = {
  ok: boolean;
  message: string;
  /** Stage-gate failures, one line per unmet requirement. */
  errors?: string[];
  status?: string;
  statusLabel?: string;
  /** The requirement row as it now stands, for the Review modal to swap in. */
  requirement?: ReviewRequirement;
};

/** Wraps an action so the three error shapes all land as `ActionResult`. */
async function run(
  fn: () => Promise<{
    message: string;
    status?: string;
    statusLabel?: string;
    requirement?: ReviewRequirement;
  }>,
): Promise<ActionResult> {
  try {
    const result = await fn();

    // The queues, badges and the open row all read from the server, so they
    // have to be re-rendered after any write.
    revalidatePath('/', 'layout');

    return { ok: true, ...result };
  } catch (error) {
    if (error instanceof GateError) {
      return { ok: false, message: error.messages[0] ?? 'That could not be saved.', errors: error.messages };
    }

    if (error instanceof ActionError || error instanceof AuthError) {
      return { ok: false, message: error.message };
    }

    // Anything else is a bug, not a refusal — surface it rather than pretending
    // the action succeeded.
    console.error('[document action]', error);

    return { ok: false, message: 'Something went wrong. Nothing was saved.' };
  }
}

export async function advanceDocument(
  documentId: number,
  input: { expectedStatus: string; claimDate?: string | null; note?: string | null },
): Promise<ActionResult> {
  const actor = await currentActor();

  return run(() => status.advance(documentId, actor, input));
}

export async function revertDocument(
  documentId: number,
  input: { expectedStatus: string; reason: string },
): Promise<ActionResult> {
  const actor = await currentActor();

  return run(() => status.revert(documentId, actor, input));
}

export async function setDocumentStatus(
  documentId: number,
  input: { expectedStatus: string; status: string; reason?: string | null },
): Promise<ActionResult> {
  const actor = await currentActor();

  return run(() => status.setStatus(documentId, actor, input));
}

export async function holdDocument(
  documentId: number,
  input: { holdReason: string; blockedBy: string; holdUntil?: string | null },
): Promise<ActionResult> {
  const actor = await currentActor();

  return run(() => status.hold(documentId, actor, input));
}

export async function unholdDocument(documentId: number): Promise<ActionResult> {
  const actor = await currentActor();

  return run(() => status.unhold(documentId, actor));
}

export async function assignDocument(
  documentId: number,
  assigneeId: number,
): Promise<ActionResult> {
  const actor = await currentActor();

  return run(() => review.assign(documentId, actor, assigneeId));
}

export async function denyDocument(
  documentId: number,
  reason: string | null,
): Promise<ActionResult> {
  const actor = await currentActor();

  return run(() => review.deny(documentId, actor, reason));
}

export async function openDocumentReview(documentId: number): Promise<ActionResult> {
  const actor = await currentActor();

  return run(async () => {
    const result = await review.openReview(documentId, actor);

    return { message: 'Review opened.', ...result };
  });
}

/** Finish request: Ongoing → Done (checks requirements, tells the office). */
export async function finishDocument(
  documentId: number,
  input: { expectedStatus: string; note?: string | null },
): Promise<ActionResult> {
  const actor = await currentActor();

  return run(() => status.finish(documentId, actor, input));
}

export async function acceptAssignment(documentId: number): Promise<ActionResult> {
  const actor = await currentActor();

  return run(() => review.acceptAssignment(documentId, actor));
}

export async function declineAssignment(
  documentId: number,
  reason: string | null,
): Promise<ActionResult> {
  const actor = await currentActor();

  return run(() => review.declineAssignment(documentId, actor, reason));
}

export async function requestRevision(
  documentId: number,
  reason: string,
): Promise<ActionResult> {
  const actor = await currentActor();

  return run(() => review.requestRevision(documentId, actor, reason));
}

/**
 * Bell whoever owns this request about it. Oversight's one write: the super
 * admin (or a supervisor, for their own office) pushes without reassigning.
 */
export async function nudgeDocument(
  documentId: number,
  note: string | null,
): Promise<ActionResult> {
  const actor = await currentActor();

  return run(() => nudge(documentId, actor, note));
}

export async function setClaimDate(
  documentId: number,
  claimDate: string,
): Promise<ActionResult> {
  const actor = await currentActor();

  return run(() => review.setClaimDate(documentId, actor, claimDate));
}

/** Looks good / Needs fixing / Reject on one requirement upload. */
export async function reviewRequirement(
  documentId: number,
  requirementId: number,
  input: { reviewStatus: string; reviewComment?: string | null },
): Promise<ActionResult> {
  const actor = await currentActor();

  return run(() => reviewOneRequirement(documentId, requirementId, actor, input));
}
