'use server';

import { revalidatePath } from 'next/cache';
import { currentActor } from '@/lib/auth/actor';
import * as profile from '@/lib/domain/profile';
import { ActionError } from '@/lib/domain/status-actions';

/**
 * The Profile page's forms. Each acts on the signed-in person only — the
 * domain functions take the actor, never a user id from the form.
 */
export type ProfileResult = { ok: boolean; message: string };

async function run(fn: (actor: NonNullable<Awaited<ReturnType<typeof currentActor>>>) => Promise<{ message: string }>): Promise<ProfileResult> {
  const actor = await currentActor();

  if (!actor) {
    return { ok: false, message: 'Please sign in again.' };
  }

  try {
    const result = await fn(actor);

    revalidatePath('/', 'layout');

    return { ok: true, message: result.message };
  } catch (error) {
    if (error instanceof ActionError) {
      return { ok: false, message: error.message };
    }

    console.error('[profile]', error);

    return { ok: false, message: 'That could not be saved. Please try again.' };
  }
}

const text = (form: FormData, key: string): string => String(form.get(key) ?? '');

export async function updateProfileDetails(form: FormData): Promise<ProfileResult> {
  return run((actor) => profile.updateDetails(actor, { name: text(form, 'name'), email: text(form, 'email') }));
}

export async function updatePassword(form: FormData): Promise<ProfileResult> {
  return run((actor) =>
    profile.changePassword(actor, {
      current: text(form, 'current_password'),
      password: text(form, 'password'),
      confirmation: text(form, 'password_confirmation'),
    }),
  );
}

export async function uploadAvatar(form: FormData): Promise<ProfileResult> {
  const file = form.get('avatar');

  return run((actor) => profile.storeAvatar(actor, file instanceof File ? file : null));
}

export async function deleteAvatar(): Promise<ProfileResult> {
  return run((actor) => profile.removeAvatar(actor));
}

export async function saveSignature(dataUrl: string): Promise<ProfileResult> {
  return run((actor) => profile.storeSignature(actor, dataUrl));
}

export async function deleteSignature(): Promise<ProfileResult> {
  return run((actor) => profile.removeSignature(actor));
}

export async function reissueBadge(): Promise<ProfileResult> {
  return run((actor) => profile.regenerateBadge(actor));
}
