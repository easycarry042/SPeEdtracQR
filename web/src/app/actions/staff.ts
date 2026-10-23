'use server';

import { revalidatePath } from 'next/cache';
import { currentActor } from '@/lib/auth/actor';
import { claimUnassigned, postHighlight } from '@/lib/domain/staff-profile';
import { ActionError } from '@/lib/domain/status-actions';

/** The staff profile's writes: post a highlight, take an unclaimed request. */
export type StaffResult = { ok: boolean; message: string };

async function run(fn: () => Promise<{ message: string }>): Promise<StaffResult> {
  try {
    const result = await fn();

    revalidatePath('/', 'layout');

    return { ok: true, message: result.message };
  } catch (error) {
    if (error instanceof ActionError) {
      return { ok: false, message: error.message };
    }

    console.error('[staff]', error);

    return { ok: false, message: 'That could not be saved. Please try again.' };
  }
}

export async function postStaffHighlight(input: { body: string; type: string; documentId: number | null }): Promise<StaffResult> {
  const actor = await currentActor();

  return actor ? run(() => postHighlight(actor, input)) : { ok: false, message: 'Please sign in again.' };
}

export async function claimRequest(documentId: number): Promise<StaffResult> {
  const actor = await currentActor();

  return actor ? run(() => claimUnassigned(actor, documentId)) : { ok: false, message: 'Please sign in again.' };
}
