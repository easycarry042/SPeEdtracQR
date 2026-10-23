import type { Prisma } from '@prisma/client';
import { db } from '@/lib/db';
import type { Actor } from '@/lib/auth/actor';
import { DocumentStatus } from '@/lib/domain/document-status';
import { documentScope } from '@/lib/domain/scope';
import { parseNaive, startOfDay } from '@/lib/time';

/**
 * Port of `HistoryController@index` / `@export` — the archive of finished
 * work. Completed requests only: anything still moving belongs on a dashboard,
 * and mixing the two made History a second, confusing worklist.
 */
export const PER_PAGE = 15;

export type HistoryFilters = { search?: string; document_type?: string; from?: string; to?: string };

export type HistoryRow = {
  id: number;
  trackingNumber: string;
  fileName: string;
  documentType: string;
  status: string;
  createdAt: Date | null;
};

function scope(actor: Actor): Prisma.documentsWhereInput {
  return { AND: [documentScope(actor), { deleted_at: null, status: DocumentStatus.Completed }] };
}

function filtered(actor: Actor, filters: HistoryFilters): Prisma.documentsWhereInput {
  const where: Prisma.documentsWhereInput[] = [scope(actor)];
  const search = filters.search?.trim() ?? '';

  if (search !== '') {
    where.push({
      OR: [
        { tracking_number: { contains: search, mode: 'insensitive' } },
        { citizen_name: { contains: search, mode: 'insensitive' } },
        { document_type: { contains: search, mode: 'insensitive' } },
      ],
    });
  }

  if (filters.document_type) {
    where.push({ document_type: filters.document_type });
  }

  const from = filters.from ? parseNaive(filters.from) : null;
  const to = filters.to ? parseNaive(filters.to) : null;

  if (from) {
    where.push({ created_at: { gte: startOfDay(from) } });
  }

  if (to) {
    where.push({ created_at: { lt: new Date(startOfDay(to).getTime() + 86_400_000) } });
  }

  return { AND: where };
}

export async function historyPage(
  actor: Actor,
  requestedPage: number,
  filters: HistoryFilters,
): Promise<{ rows: HistoryRow[]; page: number; lastPage: number; total: number; documentTypes: string[] }> {
  const where = filtered(actor, filters);

  const [total, types] = await Promise.all([
    db.documents.count({ where }),
    db.documents.findMany({
      where: scope(actor),
      distinct: ['document_type'],
      orderBy: { document_type: 'asc' },
      select: { document_type: true },
    }),
  ]);

  const lastPage = Math.max(1, Math.ceil(total / PER_PAGE));
  const page = Math.min(Math.max(1, Math.floor(requestedPage) || 1), lastPage);

  const documents = await db.documents.findMany({
    where,
    orderBy: [{ completed_at: { sort: 'desc', nulls: 'last' } }, { created_at: 'desc' }],
    skip: (page - 1) * PER_PAGE,
    take: PER_PAGE,
    select: { id: true, tracking_number: true, citizen_name: true, document_type: true, status: true, created_at: true },
  });

  return {
    rows: documents.map((document) => ({
      id: Number(document.id),
      trackingNumber: document.tracking_number,
      fileName: document.citizen_name || `File ${document.tracking_number.slice(-5)}`,
      documentType: document.document_type,
      status: document.status,
      createdAt: document.created_at,
    })),
    page,
    lastPage,
    total,
    documentTypes: types.map((row) => row.document_type),
  };
}

/** Every matching row, for `history.export`. */
export async function historyExportRows(actor: Actor, filters: HistoryFilters) {
  return db.documents.findMany({
    where: filtered(actor, filters),
    orderBy: { created_at: 'desc' },
    select: {
      tracking_number: true,
      document_type: true,
      citizen_name: true,
      status: true,
      created_at: true,
      assignee: { select: { name: true } },
    },
  });
}
