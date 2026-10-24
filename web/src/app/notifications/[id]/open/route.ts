import { NextResponse, type NextRequest } from 'next/server';
import { currentActor } from '@/lib/auth/actor';
import { followablePath, markRead } from '@/lib/domain/notifications';
import { routes } from '@/lib/routes';

/**
 * Port of `NotificationController@open` — mark one bell row read and follow it.
 *
 * Only the path and query of the stored URL are followed (followablePath).
 */
export const dynamic = 'force-dynamic';

export async function GET(request: NextRequest, { params }: { params: Promise<{ id: string }> }): Promise<Response> {
  const actor = await currentActor();

  if (!actor) {
    return NextResponse.redirect(new URL(routes.login(), request.url));
  }

  const result = await markRead((await params).id, actor.id);

  if (!result) {
    return new Response('Not found', { status: 404 });
  }

  return NextResponse.redirect(new URL(followablePath(result.url) ?? routes.home(), request.url));
}
