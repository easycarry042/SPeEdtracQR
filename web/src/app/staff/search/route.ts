import { NextResponse, type NextRequest } from 'next/server';
import { currentActor } from '@/lib/auth/actor';
import { search } from '@/lib/domain/staff-profile';

/** `staff.search` — the top bar's "jump to a person", as JSON. */
export const dynamic = 'force-dynamic';

export async function GET(request: NextRequest): Promise<Response> {
  if (!(await currentActor())) {
    return NextResponse.json([], { status: 401 });
  }

  return NextResponse.json(await search(request.nextUrl.searchParams.get('q') ?? ''));
}
