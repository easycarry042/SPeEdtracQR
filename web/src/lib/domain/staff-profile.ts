import { db, id, toId } from '@/lib/db';
import { SUBJECT, logOnDocument } from '@/lib/activity';
import type { Actor } from '@/lib/auth/actor';
import { logSystemComment } from '@/lib/domain/comments';
import { DocumentStatus, activeValues, fromLoose } from '@/lib/domain/document-status';
import { holdOverdueDays, isHoldOverdue, isOverdue, statusChangeData } from '@/lib/domain/document';
import { canViewUnclaimedQueue, documentScope } from '@/lib/domain/scope';
import { ActionError } from '@/lib/domain/status-actions';
import { forUsers } from '@/lib/domain/staff-load';
import { broadcastStatusUpdated } from '@/lib/realtime';
import { routes } from '@/lib/routes';
import { addDays, now, startOfDay, subDays, toDateString } from '@/lib/time';
import { claim } from '@/lib/domain/review-actions';

/**
 * Port of `StaffProfileController` + `App\Support\StaffProfile` — the staff
 * directory, a person's profile (accountability figures, a year of
 * completions, presence, a de-noised activity feed) and the highlight
 * composer. Any signed-in staff member may view any profile; only the owner
 * sees their worklist and the composer.
 */
export const HIGHLIGHT_TYPES = ['completed', 'milestone', 'note'] as const;

const OPEN_STATUSES = ['pending', 'in_progress', 'in_review', 'approved', 'on_hold'];

export type DocRow = {
  id: number;
  trackingNumber: string;
  documentType: string;
  status: string;
  source: string;
  creatorName: string | null;
  createdAt: Date | null;
  completedAt: Date | null;
  overdue: boolean;
  hold: { blockedBy: string | null; until: Date | null; overdueDays: number; reason: string | null } | null;
};

const DOC_SELECT = {
  id: true,
  tracking_number: true,
  document_type: true,
  status: true,
  source: true,
  created_at: true,
  completed_at: true,
  status_changed_at: true,
  updated_at: true,
  blocked_by: true,
  hold_until: true,
  held_at: true,
  hold_reason: true,
  creator: { select: { name: true } },
} as const;

type RawDoc = {
  id: bigint;
  tracking_number: string;
  document_type: string;
  status: string;
  source: string;
  created_at: Date | null;
  completed_at: Date | null;
  status_changed_at: Date | null;
  updated_at: Date | null;
  blocked_by: string | null;
  hold_until: Date | null;
  held_at: Date | null;
  hold_reason: string | null;
  creator: { name: string } | null;
};

function docRow(row: RawDoc): DocRow {
  const onHold = row.status === DocumentStatus.OnHold;

  return {
    id: toId(row.id),
    trackingNumber: row.tracking_number,
    documentType: row.document_type,
    status: row.status,
    source: row.source,
    creatorName: row.creator?.name ?? null,
    createdAt: row.created_at,
    completedAt: row.completed_at,
    overdue: isOverdue(row),
    hold: onHold
      ? {
          blockedBy: row.blocked_by,
          until: row.hold_until,
          overdueDays: isHoldOverdue(row) ? holdOverdueDays(row) : 0,
          reason: row.hold_reason,
        }
      : null,
  };
}

/* ── Directory ──────────────────────────────────────────────────────────── */

export type DirectoryRow = {
  id: number;
  name: string;
  role: string | null;
  avatarUrl: string | null;
  assigned: number;
  completed: number;
  month: number;
  overdue: number;
  lastActive: string | null;
  activeNow: boolean;
  isAvailable: boolean;
};

async function rolesByUser(userIds: bigint[]): Promise<Map<number, string>> {
  const links = await db.model_has_roles.findMany({
    where: { model_type: SUBJECT.User, model_id: { in: userIds } },
    select: { model_id: true, roles: { select: { name: true } } },
    orderBy: { role_id: 'asc' },
  });
  const map = new Map<number, string>();

  for (const link of links) {
    if (!map.has(toId(link.model_id))) {
      map.set(toId(link.model_id), link.roles.name);
    }
  }

  return map;
}

export async function directory(filters: { q?: string; role?: string; sort?: string }) {
  const q = filters.q?.trim() ?? '';
  const roles = (await db.roles.findMany({ orderBy: { name: 'asc' }, select: { name: true } })).map((r) => r.name);
  const role = filters.role && roles.includes(filters.role) ? filters.role : '';
  const sort = ['name', 'load', 'completed'].includes(filters.sort ?? '') ? (filters.sort as string) : 'name';

  const roleUserIds = role
    ? (
        await db.model_has_roles.findMany({
          where: { model_type: SUBJECT.User, roles: { name: role } },
          select: { model_id: true },
        })
      ).map((link) => link.model_id)
    : null;

  const users = await db.users.findMany({
    where: {
      deleted_at: null,
      ...(q
        ? { OR: [{ name: { contains: q, mode: 'insensitive' } }, { email: { contains: q, mode: 'insensitive' } }] }
        : {}),
      ...(roleUserIds ? { id: { in: roleUserIds } } : {}),
    },
    orderBy: { name: 'asc' },
    select: { id: true, name: true, avatar_path: true, is_available: true },
  });

  const [load, userRoles] = await Promise.all([forUsers(users.map((u) => toId(u.id))), rolesByUser(users.map((u) => u.id))]);

  let staff: DirectoryRow[] = users.map((user) => {
    const figures = load.get(toId(user.id));

    return {
      id: toId(user.id),
      name: user.name,
      role: userRoles.get(toId(user.id)) ?? null,
      avatarUrl: user.avatar_path ? routes.staffAvatar(toId(user.id)) : null,
      assigned: figures?.assigned ?? 0,
      completed: figures?.completed ?? 0,
      month: figures?.month ?? 0,
      overdue: figures?.overdue ?? 0,
      lastActive: figures?.last_active ?? null,
      activeNow: figures?.active_now ?? false,
      isAvailable: user.is_available ?? true,
    };
  });

  if (sort === 'load') {
    staff = [...staff].sort((a, b) => b.assigned - a.assigned);
  } else if (sort === 'completed') {
    staff = [...staff].sort((a, b) => b.completed - a.completed);
  }

  return { staff, q, role, sort, roles, loadMax: Math.max(1, ...staff.map((s) => s.assigned)) };
}

/** The top bar's "jump to a person". */
export async function search(q: string): Promise<Array<{ name: string; role: string | null; url: string }>> {
  const term = q.trim();

  if (term === '') {
    return [];
  }

  const users = await db.users.findMany({
    where: {
      deleted_at: null,
      OR: [{ name: { contains: term, mode: 'insensitive' } }, { email: { contains: term, mode: 'insensitive' } }],
    },
    orderBy: { name: 'asc' },
    take: 8,
    select: { id: true, name: true },
  });
  const userRoles = await rolesByUser(users.map((u) => u.id));

  return users.map((user) => ({
    name: user.name,
    role: userRoles.get(toId(user.id)) ?? null,
    url: routes.staffProfile(toId(user.id)),
  }));
}

/* ── Profile ────────────────────────────────────────────────────────────── */

export type Heatmap = {
  counts: Record<string, number>;
  max: number;
  total: number;
  since: string;
  last30: number;
  busiestType: string | null;
  busiestTypeCount: number;
  peakDay: string | null;
  peakDayCount: number;
  peakMonth: string | null;
  peakMonthCount: number;
  currentStreak: number;
  longestStreak: number;
};

/**
 * Consecutive days finishing at least one request. The current streak may end
 * yesterday: at 9am nobody has completed anything yet, and zeroing a
 * fortnight's run for that would be wrong.
 */
export function streaks(days: string[], today: string, yesterday: string): [number, number] {
  if (days.length === 0) {
    return [0, 0];
  }

  const sorted = [...days].sort();
  let longest = 1;
  let run = 1;

  for (let i = 1; i < sorted.length; i += 1) {
    const next = toDateString(addDays(new Date(`${sorted[i - 1]}T00:00:00Z`), 1));
    run = next === sorted[i] ? run + 1 : 1;
    longest = Math.max(longest, run);
  }

  const last = sorted[sorted.length - 1];

  return [last === today || last === yesterday ? run : 0, longest];
}

function topEntry(counts: Map<string, number>): [string | null, number] {
  let best: string | null = null;
  let value = 0;

  for (const [key, count] of counts) {
    if (count > value) {
      best = key;
      value = count;
    }
  }

  return [best, value];
}

export async function heatmap(userId: number): Promise<Heatmap> {
  const since = startOfDay(subDays(now(), 365));
  const rows = await db.documents.findMany({
    where: { assigned_to: id(userId), completed_at: { not: null, gte: since }, deleted_at: null },
    select: { completed_at: true, document_type: true },
  });

  const byDay = new Map<string, number>();
  const byMonth = new Map<string, number>();
  const byType = new Map<string, number>();
  const cutoff30 = startOfDay(subDays(now(), 30));
  let last30 = 0;

  for (const row of rows) {
    const day = toDateString(row.completed_at as Date);
    byDay.set(day, (byDay.get(day) ?? 0) + 1);
    byMonth.set(day.slice(0, 7), (byMonth.get(day.slice(0, 7)) ?? 0) + 1);
    byType.set(row.document_type, (byType.get(row.document_type) ?? 0) + 1);

    if ((row.completed_at as Date) >= cutoff30) {
      last30 += 1;
    }
  }

  const [peakDay, peakDayCount] = topEntry(byDay);
  const [peakMonth, peakMonthCount] = topEntry(byMonth);
  const [busiestType, busiestTypeCount] = topEntry(byType);
  const [currentStreak, longestStreak] = streaks(
    [...byDay.keys()],
    toDateString(now()),
    toDateString(subDays(now(), 1)),
  );

  return {
    counts: Object.fromEntries(byDay),
    max: Math.max(0, ...byDay.values()),
    total: rows.length,
    since: toDateString(since),
    last30,
    busiestType,
    busiestTypeCount,
    peakDay,
    peakDayCount,
    peakMonth,
    peakMonthCount,
    currentStreak,
    longestStreak,
  };
}

export type FeedEntry =
  | { kind: 'manual'; type: string; body: string; author: string; document: DocRow | null; at: Date | null }
  | { kind: 'completion'; author: string; document: DocRow; at: Date | null }
  | {
      kind: 'system_group';
      document: DocRow;
      latestStatus: string;
      at: Date | null;
      items: Array<{ body: string; at: Date | null }>;
    };

export async function profile(userId: number, viewer: Actor, tab: string) {
  const user = await db.users.findFirst({
    where: { id: id(userId) },
    select: { id: true, name: true, email: true, avatar_path: true, created_at: true, last_active_at: true, is_available: true },
  });

  if (!user) {
    return null;
  }

  const isOwn = viewer.id === userId;
  const [role] = [...(await rolesByUser([user.id])).values()];

  const completedDocs = await db.documents.findMany({
    where: { assigned_to: user.id, status: DocumentStatus.Completed, deleted_at: null },
    select: { created_at: true, completed_at: true, sla_breached_at: true },
  });
  const timed = completedDocs.filter((d) => d.completed_at && d.created_at);
  const avgDays =
    timed.length === 0
      ? null
      : timed.reduce((sum, d) => sum + Math.abs((d.completed_at as Date).getTime() - (d.created_at as Date).getTime()) / 86_400_000, 0) /
        timed.length;

  const assignedCount = await db.documents.count({
    where: { assigned_to: user.id, status: { in: activeValues() }, deleted_at: null },
  });

  // Looking at your own profile is itself proof you are here.
  let lastActiveAt: Date | null = isOwn ? now() : user.last_active_at;

  if (!lastActiveAt) {
    const latest = await db.activity_log.aggregate({
      where: { causer_type: SUBJECT.User, causer_id: user.id },
      _max: { created_at: true },
    });
    lastActiveAt = latest._max.created_at;
  }

  const kpis = {
    completed: completedDocs.length,
    assigned: assignedCount,
    avgProcessingDays: avgDays === null ? null : Math.round(avgDays * 10) / 10,
    slaRate:
      completedDocs.length > 0
        ? Math.round((completedDocs.filter((d) => d.sla_breached_at === null).length / completedDocs.length) * 100)
        : null,
  };

  const completions = async (limit = 20) =>
    (
      await db.documents.findMany({
        where: { assigned_to: user.id, status: DocumentStatus.Completed, deleted_at: null },
        orderBy: { completed_at: { sort: 'desc', nulls: 'last' } },
        take: limit,
        select: DOC_SELECT,
      })
    ).map(docRow);

  return {
    user: {
      id: toId(user.id),
      name: user.name,
      email: user.email,
      avatarUrl: user.avatar_path ? routes.staffAvatar(toId(user.id)) : null,
      createdAt: user.created_at,
      isAvailable: user.is_available ?? true,
    },
    isOwn,
    role: role ?? null,
    kpis,
    lastActiveAt,
    heatmap: await heatmap(toId(user.id)),
    feed: tab === 'activity' ? await feed(toId(user.id), user.name) : [],
    assigned:
      tab === 'assigned'
        ? (
            await db.documents.findMany({
              where: { assigned_to: user.id, status: { in: activeValues() }, deleted_at: null },
              orderBy: { status_changed_at: { sort: 'desc', nulls: 'last' } },
              select: DOC_SELECT,
            })
          ).map(docRow)
        : [],
    completions: tab === 'completions' ? await completions() : [],
    worklist: isOwn
      ? (
          await db.documents.findMany({
            where: { AND: [documentScope(viewer), { status: { in: OPEN_STATUSES }, deleted_at: null }] },
            orderBy: { created_at: 'desc' },
            take: 20,
            select: DOC_SELECT,
          })
        ).map(docRow)
      : [],
    unclaimed:
      isOwn && canViewUnclaimedQueue(viewer)
        ? (
            await db.documents.findMany({
              where: { assigned_to: null, status: { in: ['pending', 'in_progress', 'in_review', 'approved'] }, deleted_at: null },
              orderBy: { created_at: 'desc' },
              take: 10,
              select: DOC_SELECT,
            })
          ).map(docRow)
        : [],
    ownDocuments: isOwn
      ? (
          await db.documents.findMany({
            where: { OR: [{ assigned_to: user.id }, { created_by: user.id }], deleted_at: null },
            orderBy: { created_at: 'desc' },
            take: 50,
            select: { id: true, tracking_number: true, document_type: true },
          })
        ).map((d) => ({ id: toId(d.id), label: `${d.tracking_number} · ${d.document_type}` }))
      : [],
  };
}

/**
 * Staff posts and completions are prominent cards; every other log line on a
 * document is folded into ONE card per document. A display grouping only —
 * the log itself is untouched.
 */
async function feed(userId: number, name: string, limit = 40): Promise<FeedEntry[]> {
  const [highlights, completed, activity] = await Promise.all([
    db.staff_highlights.findMany({
      where: { user_id: id(userId) },
      orderBy: { created_at: 'desc' },
      take: limit,
      select: { highlight_type: true, body: true, created_at: true, document: { select: DOC_SELECT } },
    }),
    db.documents.findMany({
      where: { assigned_to: id(userId), status: DocumentStatus.Completed, deleted_at: null },
      orderBy: { completed_at: { sort: 'desc', nulls: 'last' } },
      take: limit,
      select: DOC_SELECT,
    }),
    db.activity_log.findMany({
      where: { causer_type: SUBJECT.User, causer_id: id(userId), subject_type: SUBJECT.Document },
      orderBy: { created_at: 'desc' },
      take: 200,
      select: { subject_id: true, description: true, properties: true, created_at: true },
    }),
  ]);

  // The "→ Completed" transition is already its own completion card.
  const isCompletionLine = (properties: unknown): boolean => {
    const props = (properties ?? {}) as { to?: string; attributes?: { status?: string } };

    return props.to === DocumentStatus.Completed || props.attributes?.status === DocumentStatus.Completed;
  };

  const grouped = new Map<bigint, Array<{ body: string; at: Date | null }>>();

  for (const row of activity) {
    if (row.subject_id === null || isCompletionLine(row.properties)) {
      continue;
    }

    grouped.set(row.subject_id, [...(grouped.get(row.subject_id) ?? []), { body: row.description, at: row.created_at }]);
  }

  const docs = await db.documents.findMany({ where: { id: { in: [...grouped.keys()] } }, select: DOC_SELECT });
  const docById = new Map(docs.map((doc) => [doc.id, doc]));

  const entries: FeedEntry[] = [
    ...highlights.map((h) => ({
      kind: 'manual' as const,
      type: h.highlight_type,
      body: h.body,
      author: name,
      document: h.document ? docRow(h.document) : null,
      at: h.created_at,
    })),
    ...completed.map((doc) => ({ kind: 'completion' as const, author: name, document: docRow(doc), at: doc.completed_at })),
    ...[...grouped.entries()].flatMap(([docId, items]) => {
      const doc = docById.get(docId);

      return doc
        ? [{ kind: 'system_group' as const, document: docRow(doc), latestStatus: doc.status, at: items[0]?.at ?? null, items }]
        : [];
    }),
  ];

  return entries.sort((a, b) => (b.at?.getTime() ?? 0) - (a.at?.getTime() ?? 0)).slice(0, limit);
}

/* ── Writes ─────────────────────────────────────────────────────────────── */

/** A highlight may only reference a document the author owns (assigned or created). */
export async function postHighlight(
  actor: Actor,
  input: { body: string; type: string; documentId: number | null },
): Promise<{ message: string }> {
  const body = input.body.trim();

  if (body === '' || body.length > 2000) {
    throw new ActionError('Write the highlight first (up to 2,000 characters).');
  }

  if (!(HIGHLIGHT_TYPES as readonly string[]).includes(input.type)) {
    throw new ActionError('Choose what kind of highlight this is.');
  }

  if (input.documentId !== null) {
    const owns = await db.documents.findFirst({
      where: { id: id(input.documentId), OR: [{ assigned_to: id(actor.id) }, { created_by: id(actor.id) }] },
      select: { id: true },
    });

    if (!owns) {
      throw new ActionError('You can only attach a document assigned to or created by you.');
    }
  }

  await db.staff_highlights.create({
    data: {
      user_id: id(actor.id),
      document_id: input.documentId === null ? null : id(input.documentId),
      highlight_type: input.type,
      body,
      created_at: now(),
      updated_at: now(),
    },
  });

  return { message: 'Highlight posted.' };
}

/** Port of `AssignmentController@accept` — take an unassigned request yourself. */
export async function claimUnassigned(actor: Actor, documentId: number): Promise<{ message: string }> {
  return claim(documentId, actor);
}
