import type { Prisma } from '@prisma/client';
import { db, id } from '@/lib/db';
import type { Actor } from '@/lib/auth/actor';
import { DocumentStatus } from '@/lib/domain/document-status';
import { REVIEW_SELECT, collection, type ReviewRow } from '@/lib/domain/request-review';
import { ORIGIN_INTERNAL, documentScope } from '@/lib/domain/scope';
import { parseNaive, startOfDay } from '@/lib/time';

/**
 * Port of `HistoryController::data` — the dashboards' Completed tab.
 *
 * Every finished citizen request, a page at a time, in the same payload the
 * Review modal opens, so the whole history never rides in the page. Staff see
 * what they handled; a supervisor sees their office's.
 */
export const PER_PAGE = 10;

export type CompletedFilters = {
  search?: string;
  documentType?: string;
  /** `YYYY-MM-DD`, on the filing date, inclusive. */
  from?: string;
  to?: string;
};

export type CompletedPage = {
  rows: ReviewRow[];
  total: number;
  page: number;
  lastPage: number;
  perPage: number;
  types: string[];
};

/** Finished citizen requests for one viewer's dashboard. */
export function completedScope(actor: Actor): Prisma.documentsWhereInput {
  const base: Prisma.documentsWhereInput = {
    deleted_at: null,
    origin: { not: ORIGIN_INTERNAL },
    status: DocumentStatus.Completed,
  };

  // Staff: their own finished work, as the Completed tile counts it.
  if (!actor.can('assign documents')) {
    return { ...base, assigned_to: id(actor.id) };
  }

  // A supervisor reads their office; org-wide admins read everything.
  const office =
    !actor.can('manage system') && actor.departmentId !== null
      ? { department_id: id(actor.departmentId) }
      : {};

  return { AND: [base, documentScope(actor), office] };
}

function filtersWhere(filters: CompletedFilters): Prisma.documentsWhereInput {
  const where: Prisma.documentsWhereInput[] = [];
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

  if (filters.documentType) {
    where.push({ document_type: filters.documentType });
  }

  const from = filters.from ? parseNaive(filters.from) : null;
  const to = filters.to ? parseNaive(filters.to) : null;

  if (from) {
    where.push({ created_at: { gte: startOfDay(from) } });
  }

  if (to) {
    // Inclusive of the whole "to" day.
    where.push({ created_at: { lt: new Date(startOfDay(to).getTime() + 86_400_000) } });
  }

  return { AND: where };
}

export async function completedCount(actor: Actor): Promise<number> {
  return db.documents.count({ where: completedScope(actor) });
}

export async function completedPage(
  actor: Actor,
  requestedPage: number,
  filters: CompletedFilters = {},
): Promise<CompletedPage> {
  const scope = completedScope(actor);
  const where: Prisma.documentsWhereInput = { AND: [scope, filtersWhere(filters)] };

  const [total, types] = await Promise.all([
    db.documents.count({ where }),
    db.documents.findMany({
      where: scope,
      distinct: ['document_type'],
      orderBy: { document_type: 'asc' },
      select: { document_type: true },
    }),
  ]);

  const lastPage = Math.max(1, Math.ceil(total / PER_PAGE));
  const page = Math.min(Math.max(1, Math.floor(requestedPage) || 1), lastPage);

  const rows = await db.documents.findMany({
    where,
    // Postgres puts NULLs first on DESC; older rows with no completed_at sink.
    orderBy: [{ completed_at: { sort: 'desc', nulls: 'last' } }, { id: 'desc' }],
    skip: (page - 1) * PER_PAGE,
    take: PER_PAGE,
    select: REVIEW_SELECT,
  });

  return {
    rows: await collection(rows, actor),
    total,
    page,
    lastPage,
    perPage: PER_PAGE,
    types: types.map((row) => row.document_type),
  };
}
