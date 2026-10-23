import { NextResponse, type NextRequest } from 'next/server';
import { jsonSafe } from '@/lib/db';
import { currentActor } from '@/lib/auth/actor';
import { completedPage } from '@/lib/domain/completed-history';

/**
 * `history.data` — one page of the dashboards' Completed tab, with the search
 * and filter applied on the server.
 */
export const dynamic = 'force-dynamic';

export async function GET(request: NextRequest): Promise<Response> {
  const actor = await currentActor();

  if (!actor) {
    return NextResponse.json({ message: 'Unauthenticated.' }, { status: 401 });
  }

  const query = request.nextUrl.searchParams;
  const page = await completedPage(actor, Number(query.get('page') ?? 1), {
    search: query.get('search') ?? undefined,
    documentType: query.get('document_type') ?? undefined,
    from: query.get('from') ?? undefined,
    to: query.get('to') ?? undefined,
  });

  return NextResponse.json(jsonSafe(page), { headers: { 'Cache-Control': 'private, no-store' } });
}
