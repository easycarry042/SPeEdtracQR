import type { NextRequest } from 'next/server';
import { currentActor } from '@/lib/auth/actor';
import { csvResponse } from '@/lib/csv';
import { historyExportRows } from '@/lib/domain/history';
import { forbidden } from '@/lib/file-response';
import { toDateTimeString } from '@/lib/time';

/** Port of `HistoryController@export` — the History page's rows as CSV. */
export const dynamic = 'force-dynamic';

export async function GET(request: NextRequest): Promise<Response> {
  const actor = await currentActor();

  if (!actor) {
    return forbidden();
  }

  const query = request.nextUrl.searchParams;
  const rows = await historyExportRows(actor, {
    search: query.get('search') ?? undefined,
    document_type: query.get('document_type') ?? undefined,
    from: query.get('from') ?? undefined,
    to: query.get('to') ?? undefined,
  });

  return csvResponse('documents_history.csv', [
    ['Tracking Number', 'Document Type', 'Citizen Name', 'Status', 'Assignee', 'Created At'],
    ...rows.map((row) => [
      row.tracking_number,
      row.document_type,
      row.citizen_name ?? 'N/A',
      row.status,
      row.assignee?.name ?? 'Unassigned',
      row.created_at ? toDateTimeString(row.created_at) : '',
    ]),
  ]);
}
