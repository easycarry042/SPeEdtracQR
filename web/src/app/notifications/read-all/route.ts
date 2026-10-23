import { NextResponse } from 'next/server';
import { revalidatePath } from 'next/cache';
import { currentActor } from '@/lib/auth/actor';
import { markAllRead } from '@/lib/domain/notifications';

/** Port of `NotificationController@readAll` — the bell's "Mark all read". */
export const dynamic = 'force-dynamic';

export async function POST(): Promise<Response> {
  const actor = await currentActor();

  if (!actor) {
    return NextResponse.json({ message: 'Unauthenticated.' }, { status: 401 });
  }

  await markAllRead(actor.id);
  revalidatePath('/', 'layout');

  return NextResponse.json({ message: 'All notifications marked as read.' });
}
