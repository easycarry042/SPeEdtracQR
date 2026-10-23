import type { Prisma } from '@prisma/client';
import { db, id } from '@/lib/db';
import { SUBJECT, hydrateActivity } from '@/lib/activity';
import { describeRows, type DescribedRow } from '@/lib/domain/activity-line';
import { parseNaive, startOfDay } from '@/lib/time';

/**
 * Port of `Admin\AuditLogController@index` — the whole activity log, newest
 * first, 30 to a page, filterable by who, which kind of event and which day.
 */
export const PER_PAGE = 30;

export type AuditFilters = { user?: string; log?: string; date?: string };

export type AuditRow = DescribedRow & { causerEmail: string | null; ip: string | null; isAuth: boolean };

export type AuditPage = {
  rows: AuditRow[];
  page: number;
  lastPage: number;
  total: number;
  users: Array<{ id: number; name: string }>;
};

/** Model saves that changed nothing (ActivityLine::withoutEmptyUpdates). */
const NOT_EMPTY_UPDATE: Prisma.activity_logWhereInput = {
  OR: [
    { event: null },
    { event: { not: 'updated' } },
    {
      AND: [
        { NOT: { properties: { path: ['attributes'], equals: [] } } },
        { NOT: { properties: { path: ['attributes'], equals: {} } } },
      ],
    },
  ],
};

export async function auditPage(requestedPage: number, filters: AuditFilters): Promise<AuditPage> {
  const where: Prisma.activity_logWhereInput[] = [NOT_EMPTY_UPDATE];
  const userId = Number(filters.user);

  if (Number.isInteger(userId) && userId > 0) {
    where.push({ causer_id: id(userId), causer_type: SUBJECT.User });
  }

  if (filters.log) {
    where.push({ log_name: filters.log });
  }

  const day = filters.date ? parseNaive(filters.date) : null;

  if (day) {
    const start = startOfDay(day);
    where.push({ created_at: { gte: start, lt: new Date(start.getTime() + 86_400_000) } });
  }

  const [total, users] = await Promise.all([
    db.activity_log.count({ where: { AND: where } }),
    db.users.findMany({ orderBy: { name: 'asc' }, select: { id: true, name: true } }),
  ]);

  const lastPage = Math.max(1, Math.ceil(total / PER_PAGE));
  const page = Math.min(Math.max(1, Math.floor(requestedPage) || 1), lastPage);

  const raw = await db.activity_log.findMany({
    where: { AND: where },
    orderBy: [{ created_at: 'desc' }, { id: 'desc' }],
    skip: (page - 1) * PER_PAGE,
    take: PER_PAGE,
    select: {
      id: true,
      description: true,
      event: true,
      log_name: true,
      subject_type: true,
      subject_id: true,
      causer_id: true,
      properties: true,
      created_at: true,
    },
  });

  const described = await describeRows(await hydrateActivity(raw));
  const causerIds = [...new Set(described.map((row) => row.causerId).filter((v): v is number => v !== null))];
  const emails = new Map(
    (
      await db.users.findMany({
        where: { id: { in: causerIds.map((value) => id(value)) } },
        select: { id: true, email: true },
      })
    ).map((user) => [Number(user.id), user.email]),
  );

  const rows = described.map((row, index) => {
    const props = row.properties ?? {};

    return {
      ...row,
      causerEmail: typeof props.email === 'string' ? props.email : (row.causerId ? (emails.get(row.causerId) ?? null) : null),
      ip: typeof props.ip === 'string' ? props.ip : null,
      isAuth: raw[index].log_name === 'auth',
    };
  });

  return { rows, page, lastPage, total, users: users.map((user) => ({ id: Number(user.id), name: user.name })) };
}
