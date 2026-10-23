import { currentActor } from '@/lib/auth/actor';
import { forbidden, notFound } from '@/lib/file-response';
import { readPrivate } from '@/lib/storage';

/** The owner's own registered signature, for the Profile preview. Never cached. */
export const dynamic = 'force-dynamic';

export async function GET(): Promise<Response> {
  const actor = await currentActor();

  if (!actor) {
    return forbidden();
  }

  const bytes = actor.signaturePath ? await readPrivate(actor.signaturePath) : null;

  if (!bytes) {
    return notFound();
  }

  return new Response(new Uint8Array(bytes), { headers: { 'Content-Type': 'image/png', 'Cache-Control': 'no-store' } });
}
