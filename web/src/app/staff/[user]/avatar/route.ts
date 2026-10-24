import type { NextRequest } from 'next/server';
import { db, id } from '@/lib/db';
import { currentActor } from '@/lib/auth/actor';
import { contentTypeFor, forbidden, notFound, positiveId } from '@/lib/file-response';
import { readPrivate } from '@/lib/storage';

/**
 * Port of `AvatarController@show` — a staff photo, for signed-in staff only.
 * Cached privately: a shared counter PC must not serve one clerk's face from a
 * shared cache to the next person who signs in.
 */
export const dynamic = 'force-dynamic';

export async function GET(_request: NextRequest, { params }: { params: Promise<{ user: string }> }): Promise<Response> {
  if (!(await currentActor())) {
    return forbidden();
  }

  const userId = positiveId((await params).user);
  const user = userId === null ? null : await db.users.findUnique({ where: { id: id(userId) }, select: { avatar_path: true } });
  const contents = user?.avatar_path ? await readPrivate(user.avatar_path) : null;

  if (!user?.avatar_path || !contents) {
    return notFound();
  }

  return new Response(new Uint8Array(contents), {
    headers: { 'Content-Type': contentTypeFor(user.avatar_path), 'Cache-Control': 'private, max-age=300' },
  });
}
