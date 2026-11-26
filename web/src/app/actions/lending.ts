'use server';

import { revalidatePath } from 'next/cache';
import { db, id, toId } from '@/lib/db';
import { now, parseNaive } from '@/lib/time';
import { currentActor } from '@/lib/auth/actor';
import { logOnResource } from '@/lib/activity';
import * as lending from '@/lib/domain/lending-desk';
import { isEquipment, unitsOnLoan } from '@/lib/domain/lending';
import { DocumentStatus } from '@/lib/domain/document-status';
import { canBeAdvancedBy } from '@/lib/domain/document';
import { checks } from '@/lib/domain/status-gate';
import { submitForClearance } from '@/lib/domain/review-actions';

/**
 * The Resources page's write paths.
 *
 * Every one returns `{ ok, message }` — the desk shows a refusal in the row it
 * came from, which is where the person was looking when they pressed the
 * button. The rules themselves live in `lending-desk`, shared with the booking
 * calendar so the two can never disagree.
 */
export type LendingResult = {
  ok: boolean;
  message: string;
  autoSubmitted?: boolean;
};

async function requireInventoryActor() {
  const actor = await currentActor();

  if (!actor || !actor.can('manage inventory')) {
    return null;
  }

  return actor;
}

function done(refusal: string | null, success: string): LendingResult {
  revalidatePath('/', 'layout');

  return refusal ? { ok: false, message: refusal } : { ok: true, message: success };
}

export async function approveLoan(bookingId: number, units: number | null): Promise<LendingResult> {
  const actor = await requireInventoryActor();

  if (!actor) {
    return { ok: false, message: 'This action is unauthorized.' };
  }

  const refusal = await lending.approve(bookingId, units);

  if (refusal) {
    revalidatePath('/', 'layout');
    return { ok: false, message: refusal };
  }

  let autoSubmitted = false;
  let message = 'Booking approved.';

  const bookingRow = await db.bookings.findUnique({
    where: { id: id(bookingId) },
    select: {
      document_id: true,
      document: { select: { status: true, assigned_to: true } },
    },
  });

  if (bookingRow?.document && bookingRow.document.status === DocumentStatus.InProgress) {
    const docId = toId(bookingRow.document_id);
    const assignedTo =
      bookingRow.document.assigned_to === null ? null : toId(bookingRow.document.assigned_to);

    if (canBeAdvancedBy({ assigned_to: assignedTo }, actor)) {
      const gateChecks = await checks({ id: docId }, DocumentStatus.InReview, null, actor);

      if (gateChecks.every((check) => check.passed)) {
        try {
          await submitForClearance(docId, actor, null);
          autoSubmitted = true;
          message = 'Booking approved and submitted for supervisor review.';
        } catch {
          message = 'Booking approved. Click Submit for Supervisor Review when ready.';
        }
      } else {
        message =
          'Booking approved. Check any remaining required documents or claiming date below, then click Submit for Supervisor Review.';
      }
    }
  }

  revalidatePath('/', 'layout');

  return { ok: true, message, autoSubmitted };
}

export async function releaseLoan(bookingId: number): Promise<LendingResult> {
  const actor = await requireInventoryActor();

  if (!actor) {
    return { ok: false, message: 'This action is unauthorized.' };
  }

  return done(await lending.release(bookingId, actor), 'Items handed over.');
}

export async function receiveLoan(
  bookingId: number,
  input: { returned: number; damaged: number; lost: number; notes: string | null },
): Promise<LendingResult> {
  const actor = await requireInventoryActor();

  if (!actor) {
    return { ok: false, message: 'This action is unauthorized.' };
  }

  return done(
    await lending.receive(bookingId, input.returned, input.damaged, input.lost, input.notes, actor),
    'Return recorded.',
  );
}

export async function finishLoan(bookingId: number): Promise<LendingResult> {
  const actor = await requireInventoryActor();

  if (!actor) {
    return { ok: false, message: 'This action is unauthorized.' };
  }

  return done(await lending.finish(bookingId, actor), 'Marked done.');
}

export async function cancelLoan(bookingId: number): Promise<LendingResult> {
  const actor = await requireInventoryActor();

  if (!actor) {
    return { ok: false, message: 'This action is unauthorized.' };
  }

  return done(await lending.cancel(bookingId), 'Booking cancelled.');
}

export async function extendLoan(bookingId: number, returnBy: string): Promise<LendingResult> {
  const actor = await requireInventoryActor();

  if (!actor) {
    return { ok: false, message: 'This action is unauthorized.' };
  }

  const parsed = parseNaive(returnBy);

  if (!parsed) {
    return { ok: false, message: 'Pick a return date.' };
  }

  return done(await lending.extend(bookingId, parsed), 'Return date changed.');
}

export async function remindBorrower(bookingId: number): Promise<LendingResult> {
  const actor = await requireInventoryActor();

  if (!actor) {
    return { ok: false, message: 'This action is unauthorized.' };
  }

  const sent = await lending.remind(bookingId);
  revalidatePath('/', 'layout');

  return sent
    ? { ok: true, message: 'Reminder emailed to the borrower.' }
    : {
        ok: false,
        message: 'No email on file for this borrower — call them instead. The reminder was logged.',
      };
}

/**
 * Staff correct the counts as stock changes hands — the office buys fifty more
 * chairs, a broken tent is repaired. The catalog itself (adding and removing
 * items) stays with the super admin.
 */
export async function updateStock(
  resourceId: number,
  input: {
    totalQuantity: number | null;
    damagedQuantity: number | null;
    capacity: number | null;
    location: string | null;
  },
): Promise<LendingResult> {
  const actor = await requireInventoryActor();

  if (!actor) {
    return { ok: false, message: 'This action is unauthorized.' };
  }

  const resource = await db.resources.findUnique({
    where: { id: id(resourceId) },
    select: { id: true, name: true, kind: true, total_quantity: true, damaged_quantity: true },
  });

  if (!resource) {
    return { ok: false, message: 'That item no longer exists.' };
  }

  if (isEquipment(resource)) {
    const damaged = input.damagedQuantity ?? 0;
    const total = input.totalQuantity;

    if (total !== null && total < 0) {
      return { ok: false, message: 'The number owned cannot be negative.' };
    }

    // More broken than owned is not a state the office can be in, and it would
    // read as negative stock on the page.
    if (total !== null && damaged > total) {
      return { ok: false, message: 'There cannot be more out of service than the office owns.' };
    }

    // Nor can it own fewer than are out with borrowers right now.
    const onLoan = await unitsOnLoan(resourceId);

    if (total !== null && onLoan > 0 && total < damaged + onLoan) {
      return {
        ok: false,
        message:
          `${onLoan} ${resource.name} are out on loan` +
          (damaged ? ` and ${damaged} are out of service` : '') +
          `, so the office owns at least ${damaged + onLoan}. Record a loss on the loan instead if some are gone.`,
      };
    }
  }

  await db.resources.update({
    where: { id: resource.id },
    data: {
      total_quantity: input.totalQuantity,
      damaged_quantity: input.damagedQuantity ?? 0,
      capacity: input.capacity,
      location: input.location,
      updated_at: now(),
    },
  });

  await logOnResource(resourceId, `Counts updated for ${resource.name}`, actor.id);
  revalidatePath('/', 'layout');

  return { ok: true, message: 'Counts saved.' };
}
