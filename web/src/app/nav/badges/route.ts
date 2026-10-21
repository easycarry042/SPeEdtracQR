import { NextResponse } from 'next/server';
import { currentActor } from '@/lib/auth/actor';
import { badgesFor } from '@/lib/domain/nav-badges';

/** Polled by the sidebar once a minute to keep its counts current. */
export async function GET() {
  const actor = await currentActor();

  if (!actor) {
    return NextResponse.json({ home: 0, internal: 0, resources: 0 }, { status: 401 });
  }

  return NextResponse.json(await badgesFor(actor), {
    headers: { 'Cache-Control': 'no-store' },
  });
}
