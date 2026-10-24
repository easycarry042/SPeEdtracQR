import type { NextRequest } from 'next/server';
import { currentActor } from '@/lib/auth/actor';
import { ROLE_SUPER_ADMIN } from '@/lib/auth/rbac';
import { csvResponse } from '@/lib/csv';
import { exportCsv } from '@/lib/domain/command-center';
import { forbidden } from '@/lib/file-response';

/** `admin.dashboard.export` — the city-wide command center as CSV. */
export const dynamic = 'force-dynamic';

export async function GET(request: NextRequest): Promise<Response> {
  const actor = await currentActor();

  if (!actor?.hasRole(ROLE_SUPER_ADMIN)) {
    return forbidden();
  }

  const { filename, rows } = await exportCsv(request.nextUrl.searchParams, null);

  return csvResponse(filename, rows);
}
