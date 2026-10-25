'use server';

import { revalidatePath } from 'next/cache';
import { requireActor } from '@/lib/auth/actor';
import {
  setUserAvailability,
  toggleUserAvailability,
  toggleMyAvailability,
} from '@/lib/domain/availability';

export type AvailabilityResult = {
  ok: boolean;
  isAvailable?: boolean;
  message: string;
};

function revalidateAll() {
  revalidatePath('/', 'layout');
  revalidatePath('/dashboard');
  revalidatePath('/my-dashboard');
  revalidatePath('/admin/users');
  revalidatePath('/staff');
}

/**
 * Self-service toggle for the signed-in staff member.
 */
export async function toggleMyAvailabilityAction(): Promise<AvailabilityResult> {
  try {
    const actor = await requireActor();
    const result = await toggleMyAvailability(actor);
    revalidateAll();
    return result;
  } catch (err: unknown) {
    const message = err instanceof Error ? err.message : 'Failed to update availability.';
    return { ok: false, message };
  }
}

/**
 * Supervisor or admin sets availability for a specific user.
 */
export async function setUserAvailabilityAction(
  targetUserId: number,
  isAvailable: boolean,
): Promise<AvailabilityResult> {
  try {
    const actor = await requireActor();
    const result = await setUserAvailability(targetUserId, isAvailable, actor);
    revalidateAll();
    return result;
  } catch (err: unknown) {
    const message = err instanceof Error ? err.message : 'Failed to update availability.';
    return { ok: false, message };
  }
}

/**
 * Supervisor or admin toggles availability for a specific user.
 */
export async function toggleUserAvailabilityAction(
  targetUserId: number,
): Promise<AvailabilityResult> {
  try {
    const actor = await requireActor();
    const result = await toggleUserAvailability(targetUserId, actor);
    revalidateAll();
    return result;
  } catch (err: unknown) {
    const message = err instanceof Error ? err.message : 'Failed to update availability.';
    return { ok: false, message };
  }
}
