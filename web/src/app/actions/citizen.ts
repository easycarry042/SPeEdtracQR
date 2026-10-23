'use server';

import { revalidatePath } from 'next/cache';
import { headers } from 'next/headers';
import { reuploadRequirement, uploadFiles } from '@/lib/domain/citizen-uploads';
import { ActionError } from '@/lib/domain/status-actions';
import { routes } from '@/lib/routes';
import { throttle } from '@/lib/rate-limit';

/**
 * The tracking page's two upload forms. Public (the tracking number is the
 * key), so each is throttled like Laravel's route was; a server action already
 * refuses cross-site posts.
 */
export type CitizenActionResult = { ok: boolean; message: string };

async function run(trackingNumber: string, fn: () => Promise<{ message: string }>): Promise<CitizenActionResult> {
  if (!(await throttle('citizenUpload', await headers()))) {
    return { ok: false, message: 'Too many uploads in a short time. Please wait a minute and try again.' };
  }

  try {
    const result = await fn();

    revalidatePath(routes.trackShow(trackingNumber).split('?')[0]);

    return { ok: true, message: result.message };
  } catch (error) {
    if (error instanceof ActionError) {
      return { ok: false, message: error.message };
    }

    console.error('[citizen upload]', error);

    return { ok: false, message: 'Your upload did not go through. Please try again.' };
  }
}

export async function uploadSupportingFiles(trackingNumber: string, form: FormData): Promise<CitizenActionResult> {
  const files = form.getAll('attachments').filter((value): value is File => value instanceof File);
  const note = form.get('note');

  return run(trackingNumber, () => uploadFiles(trackingNumber, files, typeof note === 'string' ? note : null));
}

export async function reuploadRevisedRequirement(
  trackingNumber: string,
  requirementId: number,
  form: FormData,
): Promise<CitizenActionResult> {
  const file = form.get('file');

  return run(trackingNumber, () =>
    reuploadRequirement(trackingNumber, requirementId, file instanceof File ? file : null),
  );
}
