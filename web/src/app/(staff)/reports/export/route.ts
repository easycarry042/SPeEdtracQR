import { NextResponse, type NextRequest } from 'next/server';
import { currentActor } from '@/lib/auth/actor';
import { ROLE_SUPER_ADMIN } from '@/lib/auth/rbac';
import { csvResponse } from '@/lib/csv';
import { db, id } from '@/lib/db';
import { exportCsv } from '@/lib/domain/command-center';
import { forbidden } from '@/lib/file-response';

/**
 * `reports.export` — one office's Reports as CSV. A super admin is sent on to
 * the city-wide export, as the page itself sends them to the command center.
 */
export const dynamic = 'force-dynamic';

export async function GET(request: NextRequest): Promise<Response> {
  const actor = await currentActor();

  if (!actor?.can('view reports')) {
    return forbidden();
  }

  if (actor.hasRole(ROLE_SUPER_ADMIN)) {
    const target = new URL('/admin/dashboard/export', request.url);
    target.search = request.nextUrl.search;

    return NextResponse.redirect(target);
  }

  const office = actor.departmentId
    ? await db.departments.findFirst({ where: { id: id(actor.departmentId) }, select: { id: true, name: true, code: true } })
    : null;

  const { filename, rows } = await exportCsv(
    request.nextUrl.searchParams,
    office ? { id: Number(office.id), name: office.name, code: office.code } : null,
  );

  return csvResponse(filename, rows);
}
