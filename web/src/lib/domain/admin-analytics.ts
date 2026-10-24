import type { Prisma } from '@prisma/client';
import { db, id } from '@/lib/db';
import { addDays, endOfDay, formatNaive, now, startOfDay, toDateString } from '@/lib/time';
import {
  DocumentStatus,
  activeValues,
  band,
  citizenCases,
  isTerminal,
  label as statusLabel,
  slaHours,
} from '@/lib/domain/document-status';
import { citizenFacing } from '@/lib/domain/scope';
import { holdOverdueDays, isHoldOverdue, statusOf } from '@/lib/domain/document';
import { fromHours } from '@/lib/domain/duration';
import { SUBJECT } from '@/lib/activity';
import { ROLE_STAFF, usersWithRole } from '@/lib/auth/rbac';

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

/* ── Region C: the command center's comparison panels ───────────────────────── */

/** Carbon's `startOfWeek(Carbon::SUNDAY)`, on the naive encoding. */
function startOfWeekSunday(date: Date): Date {
  const start = new Date(date.getTime());
  start.setUTCHours(0, 0, 0, 0);

  return new Date(start.getTime() - start.getUTCDay() * 86_400_000);
}

export type ThroughputRow = { label: string; created: number; completed: number };

/** Filed vs completed per week, over the selected window. */
export async function throughput(scope: Scope): Promise<ThroughputRow[]> {
  const [created, completed] = await Promise.all([
    db.documents.findMany({
      where: { ...scopedWhere(scope), created_at: { gte: scope.from, lte: scope.to } },
      select: { created_at: true },
    }),
    db.documents.findMany({
      where: { ...scopedWhere(scope), completed_at: { gte: scope.from, lte: scope.to } },
      select: { completed_at: true },
    }),
  ]);

  const bucket = (dates: Array<Date | null>): Map<string, number> => {
    const buckets = new Map<string, number>();

    for (const date of dates) {
      if (date) {
        const key = toDateString(startOfWeekSunday(date));
        buckets.set(key, (buckets.get(key) ?? 0) + 1);
      }
    }

    return buckets;
  };

  const createdBuckets = bucket(created.map((row) => row.created_at));
  const completedBuckets = bucket(completed.map((row) => row.completed_at));

  const weeks: ThroughputRow[] = [];
  let cursor = startOfWeekSunday(scope.from);

  while (cursor <= scope.to) {
    const key = toDateString(cursor);

    weeks.push({
      label: formatNaive(cursor, 'M j'),
      created: createdBuckets.get(key) ?? 0,
      completed: completedBuckets.get(key) ?? 0,
    });

    cursor = new Date(cursor.getTime() + 7 * 86_400_000);
  }

  return weeks;
}

export type StaffRow = { name: string; completed: number; active: number; overloaded: boolean };

/** Active assignments at which a staff member is flagged as overloaded. */
export const OVERLOADED_AT = 10;

/**
 * "Staff Workload" — completed (in window) plus current active load, for every
 * active staff member who can work requests, **idle ones included**: an empty
 * row is the answer to "who has room for more?", and leaving it out made the
 * panel look like the whole office was busy.
 */
export async function staffWorkload(scope: Scope, limit = 10): Promise<StaffRow[]> {
  const [completedRows, activeRows, staff] = await Promise.all([
    db.documents.groupBy({
      by: ['assigned_to'],
      where: {
        ...scopedWhere(scope),
        assigned_to: { not: null },
        completed_at: { gte: scope.from, lte: scope.to },
      },
      _count: { assigned_to: true },
    }),
    db.documents.groupBy({
      by: ['assigned_to'],
      where: {
        ...scopedWhere(scope),
        assigned_to: { not: null },
        status: { in: activeValues() },
      },
      _count: { assigned_to: true },
    }),
    // Super admins hold every permission but work no requests, so the roster is
    // read by role rather than by permission.
    usersWithRole(ROLE_STAFF, scope.departmentId ?? null),
  ]);

  const completed = new Map(completedRows.map((row) => [Number(row.assigned_to), row._count.assigned_to]));
  const active = new Map(activeRows.map((row) => [Number(row.assigned_to), row._count.assigned_to]));

  // Anyone holding work who is no longer on the staff list still counts.
  const ids = [...new Set([...staff.map((person) => person.id), ...completed.keys(), ...active.keys()])];

  if (ids.length === 0) {
    return [];
  }

  const names = new Map(
    (
      await db.users.findMany({
        where: { id: { in: ids.map((value) => id(value)) } },
        select: { id: true, name: true },
      })
    ).map((row) => [Number(row.id), row.name]),
  );

  return ids
    .map((personId): StaffRow => {
      const activeCount = active.get(personId) ?? 0;

      return {
        name: names.get(personId) ?? 'Unknown',
        completed: completed.get(personId) ?? 0,
        active: activeCount,
        overloaded: activeCount >= OVERLOADED_AT,
      };
    })
    .sort((a, b) => b.active + b.completed - (a.active + a.completed) || a.name.localeCompare(b.name))
    .slice(0, limit);
}

export type TypeRow = { label: string; value: number };

/** "Volume by Request Type" — created in window, top 6. */
export async function typeBreakdown(scope: Scope): Promise<TypeRow[]> {
  const rows = await db.documents.groupBy({
    by: ['document_type'],
    where: { ...scopedWhere(scope), created_at: { gte: scope.from, lte: scope.to } },
    _count: { document_type: true },
  });

  return rows
    .map((row) => ({ label: row.document_type, value: row._count.document_type }))
    .sort((a, b) => b.value - a.value)
    .slice(0, 6);
}

export type OfficeVolumeRow = {
  label: string;
  code: string | null;
  value: number;
  pct: number;
  busiest: boolean;
};

/**
 * "Requests by Office" — how much work each office is carrying.
 *
 * The bar is read against the busiest office, not against the total: the frame
 * draws one row at 100% and the rest below it, which is a comparison between
 * offices rather than a share of a whole, so the panel prints each office's
 * COUNT beside its bar.
 *
 * Every office that owns a citizen service is listed, at zero when nothing came
 * in: leaving empty offices out hid that a whole office had received nothing,
 * which is exactly what a misrouted service looks like.
 */
export async function departmentVolume(scope: Scope): Promise<OfficeVolumeRow[]> {
  const counts = await db.documents.groupBy({
    by: ['department_id'],
    where: {
      ...scopedWhere(scope),
      department_id: { not: null },
      created_at: { gte: scope.from, lte: scope.to },
    },
    _count: { department_id: true },
  });

  const serviceOffices = await db.request_types.findMany({
    where: { is_active: true, department_id: { not: null } },
    select: { department_id: true },
    distinct: ['department_id'],
  });

  const tally = new Map(counts.map((row) => [Number(row.department_id), row._count.department_id]));
  const ids = [
    ...new Set([...serviceOffices.map((row) => Number(row.department_id)), ...tally.keys()]),
  ];

  if (ids.length === 0) {
    return [];
  }

  const departments = await db.departments.findMany({
    where: { id: { in: ids.map((value) => id(value)) } },
    select: { id: true, name: true, code: true },
  });

  const max = Math.max(1, ...tally.values());

  return departments
    .map((department): OfficeVolumeRow => {
      const value = tally.get(Number(department.id)) ?? 0;

      return {
        label: department.name,
        code: department.code,
        value,
        pct: Math.round((value / max) * 100),
        busiest: value === max,
      };
    })
    .sort((a, b) => b.value - a.value || a.label.localeCompare(b.label));
}

export type UnroutedRow = {
  id: number;
  tracking_number: string;
  document_type: string;
  created_at: Date | null;
};

/**
 * Open citizen requests that reached no office: their request type has no
 * handling office, so no supervisor was told about them and they wait unseen.
 * Fixed on the Request Types page, then moved by hand.
 */
export async function unrouted(scope: Scope): Promise<UnroutedRow[]> {
  const rows = await db.documents.findMany({
    where: { ...scopedWhere(scope), department_id: null, status: { in: activeValues() } },
    select: { id: true, tracking_number: true, document_type: true, created_at: true },
    orderBy: { created_at: 'asc' },
  });

  return rows.map((row) => ({ ...row, id: Number(row.id) }));
}

export type AtRiskRow = {
  id: number;
  tracking_number: string;
  document_type: string;
  status: string;
  stage: string;
  assignee: string | null;
  /** The handling office — who the Notify dialog names when nobody is assigned. */
  office: string | null;
  hours_over: number;
  over: string;
  url: string;
};

/**
 * "Needs Attention" — every overdue open request, worst first.
 *
 * Uncapped by design: the panel shows the first rows and states the true total.
 * It used to stop at 12 and then report "12 overdue" while the tile above said
 * 15.
 */
export async function atRisk(scope: Scope): Promise<AtRiskRow[]> {
  const rows = await db.documents.findMany({
    where: { ...scopedWhere(scope), status: { in: activeValues() } },
    select: {
      id: true,
      tracking_number: true,
      document_type: true,
      status: true,
      status_changed_at: true,
      updated_at: true,
      created_at: true,
      assignee: { select: { name: true } },
      department: { select: { name: true } },
    },
  });

  return rows
    .filter((row) => slaState(row) === 'overdue')
    .map((row): AtRiskRow => {
      const stage = statusOf(row);
      const start = row.status_changed_at ?? row.updated_at ?? row.created_at;
      const elapsed = start ? Math.abs(now().getTime() - start.getTime()) / 3_600_000 : 0;
      const hoursOver = elapsed - (slaHours(stage) ?? 0);

      return {
        id: Number(row.id),
        tracking_number: row.tracking_number,
        document_type: row.document_type,
        status: row.status,
        stage: statusLabel(stage),
        assignee: row.assignee?.name ?? null,
        office: row.department?.name ?? null,
        hours_over: Math.round(hoursOver * 10) / 10,
        over: fromHours(hoursOver),
        url: `/admin/requests?open=${encodeURIComponent(row.tracking_number)}`,
      };
    })
    .sort((a, b) => b.hours_over - a.hours_over);
}

export type StalledHoldRow = {
  id: number;
  tracking_number: string;
  document_type: string;
  assignee: string | null;
  blocked_by: string | null;
  days_over: number;
  held_since: Date | null;
  url: string;
};

/**
 * Requests parked On Hold past their own hold deadline.
 *
 * These never reach `atRisk()`: a hold sets the stage SLA to null, so the
 * request is not overdue by design. Without this list a stalled hold would be
 * invisible on the command center — the exact blind spot the check-holds sweep
 * emails about.
 */
export async function stalledHolds(scope: Scope, limit = 12): Promise<StalledHoldRow[]> {
  const rows = await db.documents.findMany({
    where: { ...scopedWhere(scope), status: DocumentStatus.OnHold },
    select: {
      id: true,
      tracking_number: true,
      document_type: true,
      status: true,
      hold_until: true,
      held_at: true,
      blocked_by: true,
      assignee: { select: { name: true } },
    },
  });

  return rows
    .filter((row) => isHoldOverdue(row))
    .map((row): StalledHoldRow => ({
      id: Number(row.id),
      tracking_number: row.tracking_number,
      document_type: row.document_type,
      assignee: row.assignee?.name ?? null,
      blocked_by: row.blocked_by,
      days_over: holdOverdueDays(row),
      held_since: row.held_at,
      url: `/admin/requests?open=${encodeURIComponent(row.tracking_number)}`,
    }))
    .sort((a, b) => b.days_over - a.days_over)
    .slice(0, limit);
}

export type StageTimeRow = { label: string; hours: number; band: string };

/** Every citizen stage except On Hold, at zero — the shape the panel draws. */
function emptyStageSeries(): StageTimeRow[] {
  return citizenCases()
    // Paused time isn't a "slow stage".
    .filter((stage) => stage !== DocumentStatus.OnHold)
    .map((stage) => ({ label: statusLabel(stage), hours: 0, band: band(stage) }));
}

/**
 * "Average Time per Stage" — hours spent in each stage on average,
 * reconstructed from the activity log by diffing consecutive status-change
 * timestamps.
 *
 * Only CLOSED intervals count (a stage the request has already left): the stage
 * a request is sitting in right now has no end yet, and counting "so far" would
 * drag every average toward whatever is currently in flight.
 */
export async function timeByStage(scope: Scope): Promise<StageTimeRow[]> {
  const documents = await db.documents.findMany({
    where: scopedWhere(scope),
    select: { id: true, created_at: true },
  });

  if (documents.length === 0) {
    return emptyStageSeries();
  }

  const activities = await db.activity_log.findMany({
    where: {
      subject_type: SUBJECT.Document,
      subject_id: { in: documents.map((row) => row.id) },
    },
    select: { subject_id: true, properties: true, created_at: true },
    orderBy: { created_at: 'asc' },
  });

  const byDocument = new Map<string, typeof activities>();

  for (const entry of activities) {
    const key = String(entry.subject_id);
    byDocument.set(key, [...(byDocument.get(key) ?? []), entry]);
  }

  const total = new Map<string, number>();
  const closed = new Map<string, number>();

  for (const document of documents) {
    let previousTime = document.created_at;
    let previousStatus: string | null = null;

    for (const entry of byDocument.get(String(document.id)) ?? []) {
      const properties = (entry.properties ?? {}) as {
        attributes?: Record<string, unknown>;
        old?: Record<string, unknown>;
      };

      // Not a status transition.
      if (!properties.attributes || !('status' in properties.attributes)) {
        continue;
      }

      previousStatus ??= (properties.old?.status as string | undefined) ?? DocumentStatus.Pending;

      if (previousTime && entry.created_at) {
        const hours = Math.abs(entry.created_at.getTime() - previousTime.getTime()) / 3_600_000;
        total.set(previousStatus, (total.get(previousStatus) ?? 0) + hours);
        closed.set(previousStatus, (closed.get(previousStatus) ?? 0) + 1);
      }

      previousTime = entry.created_at;
      previousStatus = properties.attributes.status as string;
    }
  }

  return emptyStageSeries().map((row, index) => {
    const stage = citizenCases().filter((value) => value !== DocumentStatus.OnHold)[index];
    const count = closed.get(stage) ?? 0;

    return {
      ...row,
      hours: count > 0 ? Math.round(((total.get(stage) ?? 0) / count) * 10) / 10 : 0,
    };
  });
}
