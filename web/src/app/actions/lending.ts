'use server';

import { revalidatePath } from 'next/cache';
import { db, id } from '@/lib/db';
import { now, parseNaive } from '@/lib/time';
import { currentActor } from '@/lib/auth/actor';
import { logOnResource } from '@/lib/activity';
import * as lending from '@/lib/domain/lending-desk';
import { isEquipment, unitsOnLoan } from '@/lib/domain/lending';

/**
 * The Resources page's write paths.
 *
 * Every one returns `{ ok, message }` — the desk shows a refusal in the row it
 * came from, which is where the person was looking when they pressed the
 * button. The rules themselves live in `lending-desk`, shared with the booking
 * calendar so the two can never disagree.
 */
export type LendingResult = { ok: boolean; message: string };

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

  return done(await lending.approve(bookingId, units), 'Booking approved.');
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
