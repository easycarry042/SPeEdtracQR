import { db, id, toId } from '@/lib/db';
import { DocumentStatus, activeValues } from '@/lib/domain/document-status';
import { isOverdue } from '@/lib/domain/document';
import { SUBJECT } from '@/lib/activity';
import { forHumans, now } from '@/lib/time';

/**
 * Per-person workload figures — open, completed, overdue, last active — read by
 * the Users page and the staff directory.
 *
 * Port of `App\Support\StaffLoad`. One grouped query per figure, so a page of
 * people costs a handful of queries rather than four per person.
 */
export type Load = {
  assigned: number;
  completed: number;
  /** Completed since the first of this month. */
  month: number;
  overdue: number;
  last_active: string | null;
  active_now: boolean;
};

/** The window inside which someone counts as "Active now". */
const ACTIVE_WITHIN_MINUTES = 5;

function startOfMonth(): Date {
  const date = now();

  return new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), 1));
}

export async function forUsers(userIds: number[]): Promise<Map<number, Load>> {
  const ids = [...new Set(userIds)];

  if (ids.length === 0) {
    return new Map();
  }

  const keys = ids.map((value) => id(value));
  const assignedTo = { assigned_to: { in: keys }, deleted_at: null };

  const [assignedRows, completedRows, monthRows, activeDocs, users] = await Promise.all([
    db.documents.groupBy({
      by: ['assigned_to'],
      where: { ...assignedTo, status: { in: activeValues() } },
      _count: { assigned_to: true },
    }),
    db.documents.groupBy({
      by: ['assigned_to'],
      where: { ...assignedTo, status: DocumentStatus.Completed },
      _count: { assigned_to: true },
    }),
    db.documents.groupBy({
      by: ['assigned_to'],
      where: {
        ...assignedTo,
        status: DocumentStatus.Completed,
        status_changed_at: { gte: startOfMonth() },
      },
      _count: { assigned_to: true },
    }),
    // Overdue is per-stage logic (`slaHours`), so the small active set is
    // evaluated in memory rather than duplicating that rule in SQL.
    db.documents.findMany({
      where: { ...assignedTo, status: { in: activeValues() } },
      select: {
        assigned_to: true,
        status: true,
        status_changed_at: true,
        updated_at: true,
        created_at: true,
      },
    }),
    // Presence: the stamp written on every page a person opens. The activity log
    // is only a fallback, for accounts not seen since that column existed — on
    // its own it reports the last thing they CHANGED, so anyone merely reading
    // the site read as idle.
    db.users.findMany({
      where: { id: { in: keys } },
      select: { id: true, last_active_at: true },
    }),
  ]);

  const tally = (rows: Array<{ assigned_to: bigint | null; _count: { assigned_to: number } }>) =>
    new Map(rows.map((row) => [Number(row.assigned_to), row._count.assigned_to]));

  const assigned = tally(assignedRows);
  const completed = tally(completedRows);
  const month = tally(monthRows);

  const overdue = new Map<number, number>();
  for (const document of activeDocs) {
    if (document.assigned_to !== null && isOverdue(document)) {
      const key = toId(document.assigned_to);
      overdue.set(key, (overdue.get(key) ?? 0) + 1);
    }
  }

  const lastActive = new Map<number, Date>();
  for (const user of users) {
    if (user.last_active_at) {
      lastActive.set(toId(user.id), user.last_active_at);
    }
  }

  const unseen = ids.filter((value) => !lastActive.has(value));

  if (unseen.length > 0) {
    const fallback = await db.activity_log.groupBy({
      by: ['causer_id'],
      where: { causer_type: SUBJECT.User, causer_id: { in: unseen.map((value) => id(value)) } },
      _max: { created_at: true },
    });

    for (const row of fallback) {
      if (row.causer_id !== null && row._max.created_at) {
        lastActive.set(toId(row.causer_id), row._max.created_at);
      }
    }
  }

  const activeCutoff = new Date(now().getTime() - ACTIVE_WITHIN_MINUTES * 60_000);

  return new Map(
    ids.map((userId): [number, Load] => {
      const seen = lastActive.get(userId) ?? null;

      return [
        userId,
        {
          assigned: assigned.get(userId) ?? 0,
          completed: completed.get(userId) ?? 0,
          month: month.get(userId) ?? 0,
          overdue: overdue.get(userId) ?? 0,
          // The same five-minute window the profile header uses, so one person
          // cannot read "Active now" on their profile and "Active 1h ago" in
          // the directory at the same moment.
          last_active: seen ? forHumans(seen) : null,
          active_now: seen !== null && seen > activeCutoff,
        },
      ];
    }),
  );
}

