'use server';

import { revalidatePath } from 'next/cache';
import { currentActor } from '@/lib/auth/actor';
import * as lending from '@/lib/domain/lending-desk';
import { parseNaive } from '@/lib/time';

/**
 * Port of `BookingController@approve/reschedule/cancel` — the Booking
 * calendar's controls, for whoever holds `manage bookings`. The rules are the
 * Resources page's (lending-desk): approval is stock-checked, a reschedule is
 * re-checked and moves the request's claiming date, a handed-over loan stays.
 */
export type BookingResult = { ok: boolean; message: string };

async function allowed(): Promise<boolean> {
  return Boolean((await currentActor())?.can('manage bookings'));
}

function done(refusal: string | null, success: string): BookingResult {
  revalidatePath('/', 'layout');

  return refusal ? { ok: false, message: refusal } : { ok: true, message: success };
}

export async function approveBooking(bookingId: number, quantity: number | null): Promise<BookingResult> {
  if (!(await allowed())) {
    return { ok: false, message: 'This action is unauthorized.' };
  }

  if (quantity !== null && (!Number.isInteger(quantity) || quantity < 1 || quantity > 100_000)) {
    return { ok: false, message: 'Enter how many to lend as a whole number.' };
  }

  return done(await lending.approve(bookingId, quantity), 'Booking approved.');
}

export async function rescheduleBooking(bookingId: number, startsAt: string, endsAt: string): Promise<BookingResult> {
  if (!(await allowed())) {
    return { ok: false, message: 'This action is unauthorized.' };
  }

  const starts = parseNaive(startsAt);
  const ends = parseNaive(endsAt);

  if (!starts || !ends) {
    return { ok: false, message: 'Pick a new start and end.' };
  }

  if (ends.getTime() <= starts.getTime()) {
    return { ok: false, message: 'The end time must be after the start time.' };
  }

  return done(await lending.reschedule(bookingId, starts, ends), 'Booking rescheduled.');
}

export async function cancelBooking(bookingId: number): Promise<BookingResult> {
  if (!(await allowed())) {
    return { ok: false, message: 'This action is unauthorized.' };
  }

  return done(await lending.cancel(bookingId), 'Booking cancelled.');
}
