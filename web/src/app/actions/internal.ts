'use server';

import { revalidatePath } from 'next/cache';
import { currentActor, AuthError } from '@/lib/auth/actor';
import { ActionError } from '@/lib/domain/status-actions';
import { GateError } from '@/lib/domain/status-gate';
import * as steps from '@/lib/domain/internal-steps';
import * as custody from '@/lib/domain/custody';
import { fileInternalRequest, type FilingInput } from '@/lib/domain/internal-filing';

/**
 * The internal request write paths: receiving a folder, the four hop decisions,
 * and the filing office's resend / withdraw.
 *
 * Same contract as the citizen review actions — `{ ok, message }` rather than a
 * thrown error — because every one of these is refused for a reason the person
 * acting needs to read: the folder is not in the office yet, the QR belongs to
 * another request, there is no registered signature.
 */
export type InternalResult = {
  ok: boolean;
  message: string;
  /** Where to go when the action created or moved something (filing). */
  url?: string;
};

async function run(
  fn: () => Promise<{ message: string; url?: string }>,
): Promise<InternalResult> {
  try {
    const result = await fn();

    // The chain, the badges and the inbox all read from the server.
    revalidatePath('/', 'layout');

    return { ok: true, ...result };
  } catch (error) {
    if (error instanceof GateError) {
      return { ok: false, message: error.messages[0] ?? 'That could not be saved.' };
    }

    if (error instanceof ActionError || error instanceof AuthError) {
      return { ok: false, message: error.message };
    }

    console.error('[internal action]', error);

    return { ok: false, message: 'Something went wrong. Nothing was saved.' };
  }
}

export async function receiveRequest(
  documentId: number,
  input: {
    captureMethod: custody.CaptureMethod;
    scannedValue?: string | null;
    overrideReason?: string | null;
    note?: string | null;
  },
): Promise<InternalResult> {
  const actor = await currentActor();

  return run(() => custody.takeCustody(documentId, actor, input));
}

export async function approveStep(
  documentId: number,
  input: steps.StepDecisionInput,
): Promise<InternalResult> {
  const actor = await currentActor();

  return run(() => steps.approve(documentId, actor, input));
}

export async function completeStep(
  documentId: number,
  input: steps.StepDecisionInput,
): Promise<InternalResult> {
  const actor = await currentActor();

  return run(() => steps.complete(documentId, actor, input));
}

export async function denyStep(
  documentId: number,
  input: steps.StepDecisionInput,
): Promise<InternalResult> {
  const actor = await currentActor();

  return run(() => steps.deny(documentId, actor, input));
}

export async function returnStep(
  documentId: number,
  input: steps.StepDecisionInput,
): Promise<InternalResult> {
  const actor = await currentActor();

  return run(() => steps.returnToRequester(documentId, actor, input));
}

/** Resend carries an optional revised scan, so it posts a FormData. */
export async function resendRequest(
  documentId: number,
  form: FormData,
): Promise<InternalResult> {
  const actor = await currentActor();
  const scan = form.get('paper_scan');

  return run(() =>
    steps.resend(documentId, actor, {
      note: String(form.get('note') ?? ''),
      paperScan: scan instanceof File && scan.size > 0 ? scan : null,
    }),
  );
}

export async function withdrawRequest(
  documentId: number,
  reason: string | null,
): Promise<InternalResult> {
  const actor = await currentActor();

  return run(() => steps.withdraw(documentId, actor, reason));
}

/** File a new internal request (the wizard posts the signed paper with it). */
export async function fileRequest(form: FormData): Promise<InternalResult> {
  const actor = await currentActor();
  const scan = form.get('paper_scan');
  const hasHardCopy = form.get('has_hard_copy');
  const recipient = form.get('recipient_id');

  const input: FilingInput = {
    firstDepartmentId: Number(form.get('first_department_id') ?? 0),
    purpose: String(form.get('purpose') ?? ''),
    // Absent means paper, as every request was before the choice existed.
    hasHardCopy: hasHardCopy === null ? true : hasHardCopy === '1' || hasHardCopy === 'true',
    recipientId: recipient ? Number(recipient) : null,
    paperScan: scan instanceof File && scan.size > 0 ? scan : null,
  };

  return run(() => fileInternalRequest(actor, input));
}
