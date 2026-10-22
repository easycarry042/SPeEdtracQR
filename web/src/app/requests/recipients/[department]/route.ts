import { NextResponse } from 'next/server';
import { db, id } from '@/lib/db';
import { currentActor } from '@/lib/auth/actor';
import { recipients } from '@/lib/domain/internal-request-form';

/**
 * Who can be named to receive a soft copy at one office — fetched by the filing
 * form when that office is picked.
 *
 * The form opens in a modal on every staff page, so it does not ship the whole
 * staff list; it asks for one office at a time.
 */
export async function GET(
  _request: Request,
  { params }: { params: Promise<{ department: string }> },
) {
  const actor = await currentActor();

  if (!actor) {
    return NextResponse.json([], { status: 401 });
  }

  const { department } = await params;
  const departmentId = Number(department);

  if (!Number.isInteger(departmentId) || departmentId <= 0) {
    return NextResponse.json([], { status: 404 });
  }

  const office = await db.departments.findFirst({
    where: { id: id(departmentId), is_active: true },
    select: { id: true },
  });

  if (!office) {
    return NextResponse.json([], { status: 404 });
  }

  const people = await recipients([departmentId]);

  return NextResponse.json(people[departmentId] ?? [], {
    headers: { 'Cache-Control': 'no-store' },
  });
}
