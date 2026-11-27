'use client';

export type DirectUploadScope = 'citizen-requirement' | 'citizen-supporting' | 'internal-paper';

type PresignResponse =
  | { mode: 'server' }
  | { mode: 'direct'; signedUrl: string; path: string; receipt: string };

/**
 * Requests a signed private Supabase upload URL and uploads directly from the
 * browser when Supabase is active (bypassing Vercel's 4.5 MB body limit). Falls
 * back seamlessly to standard FormData submission in local dev (`mode: 'server'`).
 */
export async function uploadFileDirect(
  file: File,
  scope: DirectUploadScope,
): Promise<{ mode: 'direct'; receipt: string } | { mode: 'server' }> {
  const res = await fetch('/api/uploads/presign', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ filename: file.name, size: file.size, scope }),
  });

  const payload = (await res.json().catch(() => null)) as
    | (PresignResponse & { message?: string })
    | { message?: string }
    | null;

  if (!res.ok) {
    throw new Error(payload?.message || 'Could not prepare file upload.');
  }

  if (!payload || !('mode' in payload) || payload.mode !== 'direct') {
    return { mode: 'server' };
  }

  const uploadForm = new FormData();
  uploadForm.append('cacheControl', '3600');
  uploadForm.append('', file);

  const putRes = await fetch(payload.signedUrl, {
    method: 'PUT',
    headers: { 'x-upsert': 'true' },
    body: uploadForm,
  });

  if (!putRes.ok) {
    throw new Error(`Could not upload "${file.name}". Please try again.`);
  }

  return { mode: 'direct', receipt: payload.receipt };
}
