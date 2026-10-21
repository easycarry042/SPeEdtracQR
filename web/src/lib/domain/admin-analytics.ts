import type { Prisma } from '@prisma/client';
import { db, id } from '@/lib/db';
import { addDays, endOfDay, now, startOfDay, toDateString } from '@/lib/time';
import { DocumentStatus, activeValues, isTerminal, slaHours } from '@/lib/domain/document-status';
import { citizenFacing } from '@/lib/domain/scope';
import { statusOf } from '@/lib/domain/document';

/**
 * Read model for the CITIZEN side of the command center and the supervisor's
 * Reports page.
 *
 * Internal (office-to-office) requests are left out entirely — they have their
 * own lifecycle and their own read model. Mixed in, they were judged against
 * the citizen stage clocks and padded the overdue list with INT- requests.
 *
 * Every metric comes from the MANUAL status model — `status`,
 * `status_changed_at`, `completed_at`, `assigned_to` — plus the activity log
 * for per-stage timing.
 *
 * The tile row reads ONE cohort: the requests filed inside the window, split by
 * where each one is now. The tiles used to mix two kinds — Total and Completed
 * from the window, Pending and In Progress from all time — so they looked like
 * they added up and only did by coincidence.
 */

/** Elapsed share of a stage's SLA at which a document is "at risk". */
const AT_RISK_FRACTION = 0.75;

export type Delta = { value: number; direction: 'up' | 'down' | 'flat'; good: boolean } | null;

export type Kpis = {
  total: { value: number; delta: Delta; spark: number[] };
  completed: { value: number; delta: Delta; spark: number[] };
  active: { value: number; spark: number[] };
  pending: { value: number };
  inProgress: { value: number };
  denied: { value: number };
  /** Past their stage's processing time, open now. */
  overdue: { value: number; pill: 'breach' | 'ok' };
  /** Not late yet, but three quarters of the way there. */
  atRisk: { value: number; pill: 'watch' | 'ok' };
  avgProcessing: {
    value: number | null;
    delta: Delta;
    /** How many completions the average stands on: one request finished in 53 minutes is not a processing time. */
    sample: number;
  };
};

export type SlaState = 'overdue' | 'at_risk' | 'on_time' | null;

type Scope = {
  from: Date;
  to: Date;
  documentType?: string | null;
  /** One office's numbers (a supervisor's Reports page); null = the city. */
  departmentId?: number | null;
};

function scopedWhere(scope: Scope): Prisma.documentsWhereInput {
  return {
    deleted_at: null,
    ...citizenFacing,
    ...(scope.departmentId ? { department_id: id(scope.departmentId) } : {}),
    ...(scope.documentType ? { document_type: scope.documentType } : {}),
  };
}

/**
 * Whether an open request is on time, at risk or overdue — decided in ONE
 * place. The tile, the breakdown panel and the attention list each used to work
 * it out themselves, and the page showed 15, 0 and 12 for the same thing.
 */
export function slaState(document: {
  status: string;
  status_changed_at: Date | null;
  updated_at: Date | null;
  created_at: Date | null;
}): SlaState {
  const stage = statusOf(document);
  const sla = slaHours(stage);
  // The same anchor `isOverdue()` uses.
  const start = document.status_changed_at ?? document.updated_at ?? document.created_at;

  if (sla === null || isTerminal(stage) || !start) {
    return null;
  }

  const elapsed = Math.abs(now().getTime() - start.getTime()) / 3_600_000;

  if (elapsed > sla) {
    return 'overdue';
  }

  return elapsed >= sla * AT_RISK_FRACTION ? 'at_risk' : 'on_time';
}

/**
 * Requests filed in the window, by where each one is now. Pending, in progress
 * (every open stage after Pending, held and returned included), completed and
 * denied add back to the total.
 */
async function cohortCounts(
  scope: Scope,
  from: Date,
  to: Date,
): Promise<{ total: number; pending: number; inProgress: number; completed: number; denied: number }> {
  const rows = await db.documents.groupBy({
    by: ['status'],
    where: { ...scopedWhere(scope), created_at: { gte: from, lte: to } },
    _count: { _all: true },
  });

  const counts = new Map(rows.map((row) => [row.status, row._count._all]));
  const total = rows.reduce((sum, row) => sum + row._count._all, 0);
  const pending = counts.get(DocumentStatus.Pending) ?? 0;
  const completed = counts.get(DocumentStatus.Completed) ?? 0;
  const denied = counts.get(DocumentStatus.Denied) ?? 0;

  return {
    total,
    pending,
    inProgress: Math.max(0, total - pending - completed - denied),
    completed,
    denied,
  };
}

function delta(current: number, previous: number, lowerIsBetter = false): Delta {
  if (previous === 0) {
    return current === 0 ? { value: 0, direction: 'flat', good: true } : null;
  }

  const change = Math.round(((current - previous) / previous) * 100);
  const direction = change > 0 ? 'up' : change < 0 ? 'down' : 'flat';
  const good = direction === 'flat' ? true : lowerIsBetter ? direction === 'down' : direction === 'up';

  return { value: Math.abs(change), direction, good };
}

function comparableDelta(
  current: number | null,
  previous: number | null,
  lowerIsBetter = false,
): Delta {
  if (current === null || previous === null) {
    return null;
  }

  return delta(current, previous, lowerIsBetter);
}

async function avgProcessingDays(scope: Scope, from: Date, to: Date): Promise<number | null> {
  const rows = await db.documents.findMany({
    where: {
      ...scopedWhere(scope),
      completed_at: { gte: from, lte: to, not: null },
    },
    select: { created_at: true, completed_at: true },
  });

  if (rows.length === 0) {
    return null;
  }

  const total = rows.reduce(
    (sum, row) =>
      sum +
      Math.abs((row.completed_at!.getTime() - (row.created_at?.getTime() ?? 0)) / 86_400_000),
    0,
  );

  // Three decimals, not one: a day is 1440 minutes, so rounding to a tenth of a
  // day printed a 53-minute average as "0".
  return Math.round((total / rows.length) * 1000) / 1000;
}

function completedCount(scope: Scope, from: Date, to: Date): Promise<number> {
  return db.documents.count({
    where: { ...scopedWhere(scope), completed_at: { gte: from, lte: to } },
  });
}

/** Every day in the window is present, so a sparkline has no holes. */
async function dailySeries(
  scope: Scope,
  column: 'created_at' | 'completed_at',
): Promise<number[]> {
  const rows = await db.documents.findMany({
    where: { ...scopedWhere(scope), [column]: { gte: scope.from, lte: scope.to } },
    select: { [column]: true } as never,
  });

  const counts = new Map<string, number>();

  for (const row of rows as unknown as Array<Record<string, Date | null>>) {
    const value = row[column];

    if (value) {
      const day = toDateString(value);
      counts.set(day, (counts.get(day) ?? 0) + 1);
    }
  }

  const series: number[] = [];

  for (let day = startOfDay(scope.from); day <= scope.to; day = addDays(day, 1)) {
    series.push(counts.get(toDateString(day)) ?? 0);
  }

  return series;
}

/**
 * Honest "active as of end of day D": documents created on or before D and not
 * yet completed by D. Derived purely from `created_at`/`completed_at`.
 */
async function dailyActive(scope: Scope): Promise<number[]> {
  const rows = await db.documents.findMany({
    where: { ...scopedWhere(scope), created_at: { lte: scope.to } },
    select: { created_at: true, completed_at: true },
  });

  const series: number[] = [];

  for (let day = startOfDay(scope.from); day <= scope.to; day = addDays(day, 1)) {
    const eod = endOfDay(day);

    series.push(
      rows.filter(
        (row) =>
          row.created_at !== null &&
          row.created_at <= eod &&
          (row.completed_at === null || row.completed_at > eod),
      ).length,
    );
  }

  return series;
}

/** The KPI tile row. */
export async function kpis(
  from: Date,
  to: Date,
  departmentId: number | null = null,
  documentType: string | null = null,
): Promise<Kpis> {
  const scope: Scope = { from, to, departmentId, documentType };

  // Previous window of equal length, ending just before `from`, for deltas.
  const span = to.getTime() - from.getTime();
  const prevTo = new Date(from.getTime() - 1);
  const prevFrom = new Date(prevTo.getTime() - span);

  const [cohort, cohortPrev, open, avgNow, avgPrev, sample, createdSpark, completedSpark, activeSpark] =
    await Promise.all([
      cohortCounts(scope, from, to),
      cohortCounts(scope, prevFrom, prevTo),
      // "All open right now", whenever filed — the charts and the CSV read it.
      db.documents.findMany({
        where: { ...scopedWhere(scope), status: { in: activeValues() } },
        select: { status: true, status_changed_at: true, updated_at: true, created_at: true },
      }),
      avgProcessingDays(scope, from, to),
      avgProcessingDays(scope, prevFrom, prevTo),
      completedCount(scope, from, to),
      dailySeries(scope, 'created_at'),
      dailySeries(scope, 'completed_at'),
      dailyActive(scope),
    ]);

  const states = open.map(slaState);
  const overdue = states.filter((state) => state === 'overdue').length;
  const atRisk = states.filter((state) => state === 'at_risk').length;

  return {
    total: { value: cohort.total, delta: delta(cohort.total, cohortPrev.total), spark: createdSpark },
    completed: {
      value: cohort.completed,
      delta: delta(cohort.completed, cohortPrev.completed),
      spark: completedSpark,
    },
    active: { value: open.length, spark: activeSpark },
    pending: { value: cohort.pending },
    inProgress: { value: cohort.inProgress },
    denied: { value: cohort.denied },
    overdue: { value: overdue, pill: overdue > 0 ? 'breach' : 'ok' },
    atRisk: { value: atRisk, pill: atRisk > 0 ? 'watch' : 'ok' },
    avgProcessing: {
      value: avgNow,
      // Lower is better, so the delta direction is inverted for display.
      delta: comparableDelta(avgNow, avgPrev, true),
      sample,
    },
  };
}

/** Status distribution for the donut — a snapshot of "now", not the window. */
export async function statusDistribution(
  departmentId: number | null = null,
): Promise<Array<{ status: string; count: number }>> {
  const rows = await db.documents.groupBy({
    by: ['status'],
    where: {
      deleted_at: null,
      ...citizenFacing,
      ...(departmentId ? { department_id: id(departmentId) } : {}),
    },
    _count: { _all: true },
  });

  return rows
    .map((row) => ({ status: row.status, count: row._count._all }))
    .sort((a, b) => b.count - a.count);
}

/**
 * Open requests needing attention, worst first — the "Needs attention" panel.
 * Decided by `slaState()` only, so it can never disagree with the tiles.
 */
export async function needsAttention(
  departmentId: number | null = null,
  limit = 12,
): Promise<
  Array<{
    id: number;
    trackingNumber: string;
    documentType: string;
    status: string;
    department: string | null;
    assignee: string | null;
    state: Exclude<SlaState, null>;
  }>
> {
  const rows = await db.documents.findMany({
    where: {
      deleted_at: null,
      ...citizenFacing,
      ...(departmentId ? { department_id: id(departmentId) } : {}),
      status: { in: activeValues() },
    },
    select: {
      id: true,
      tracking_number: true,
      document_type: true,
      status: true,
      status_changed_at: true,
      updated_at: true,
      created_at: true,
      department: { select: { name: true } },
      assignee: { select: { name: true } },
    },
  });

  return rows
    .map((row) => ({ row, state: slaState(row) }))
    .filter(
      (entry): entry is { row: (typeof rows)[number]; state: 'overdue' | 'at_risk' } =>
        entry.state === 'overdue' || entry.state === 'at_risk',
    )
    // Overdue before at-risk, then oldest stage first.
    .sort((a, b) => {
      if (a.state !== b.state) {
        return a.state === 'overdue' ? -1 : 1;
      }

      const aAnchor = a.row.status_changed_at ?? a.row.created_at ?? now();
      const bAnchor = b.row.status_changed_at ?? b.row.created_at ?? now();

      return aAnchor.getTime() - bAnchor.getTime();
    })
    .slice(0, limit)
    .map(({ row, state }) => ({
      id: Number(row.id),
      trackingNumber: row.tracking_number,
      documentType: row.document_type,
      status: row.status,
      department: row.department?.name ?? null,
      assignee: row.assignee?.name ?? null,
      state,
    }));
}
