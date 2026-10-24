import { db, toId } from '@/lib/db';
import { DocumentStatus, activeValues } from '@/lib/domain/document-status';
import { STEP_STATUS } from '@/lib/domain/request-step';
import { internalStepHours, DEFAULT_INTERNAL_STEP_HOURS } from '@/lib/domain/department';
import { involvingOffice, internalOnly } from '@/lib/domain/scope';
import { fromHours } from '@/lib/domain/duration';
import { now, formatNaive, toDateString } from '@/lib/time';
import type { Prisma } from '@prisma/client';

/**
 * Read model for the INTERNAL side of the command center: requests one office
 * files with another, travelling a chain of endorsement hops.
 *
 * Port of `App\Support\InternalAnalytics`. These are measured by their own
 * lifecycle rather than the citizen stage clocks: a request is overdue when the
 * office now holding it has had it longer than that office's turnaround, and a
 * request returned for revision is waiting on the office that filed it, with no
 * deadline at all.
 *
 * Tiles read one cohort — internal requests filed in the selected window, by
 * where each is now — so they add back to the total, as on the citizen side.
 */
export type InternalKpis = {
  filed: number;
  filed_prev: number;
  moving: number;
  returned: number;
  completed: number;
  denied: number;
  /** Filing to "done", in days, for requests finished in the window. */
  avg_turnaround: number | null;
  avg_sample: number;
  overdue: number;
};

export type WaitingRow = {
  id: number;
  tracking_number: string;
  document_type?: string;
  purpose: string | null;
  filed_by?: string | null;
  at: string;
  at_code: string | null;
  returned: boolean;
  waited_hours: number;
  waited: string;
  limit_hours: number | null;
  overdue: boolean;
  url: string;
};

export type OfficeRow = {
  label: string;
  code: string | null;
  count: number;
  overdue: number;
  longest: string;
  limit: string | null;
  returned: boolean;
};

export type TurnaroundRow = {
  label: string;
  code: string | null;
  hops: number;
  avg: string;
  avg_hours: number;
  limit: string;
  over: boolean;
};

export type BarRow = { label: string; code: string | null; value: number; pct: number };

export type ThroughputRow = { label: string; created: number; completed: number };

/** The window and (optionally) the one office a supervisor's Reports is about. */
export type InternalScope = {
  from: Date;
  to: Date;
  /** Filed by this office or routed through it. Null = city-wide. */
  departmentId?: number | null;
};

function scoped(scope: InternalScope): Prisma.documentsWhereInput {
  return scope.departmentId ? involvingOffice(scope.departmentId) : internalOnly;
}

/** The equivalent window immediately before this one, for the trend arrow. */
function previousWindow(scope: InternalScope): { from: Date; to: Date } {
  const span = scope.to.getTime() - scope.from.getTime();
  const to = new Date(scope.from.getTime() - 1000);

  return { from: new Date(to.getTime() - span), to };
}

type CohortCounts = {
  total: number;
  moving: number;
  returned: number;
  completed: number;
  denied: number;
};

/** Internal requests filed in the window, by where each one is now. */
async function cohortCounts(
  scope: InternalScope,
  from: Date,
  to: Date,
): Promise<CohortCounts> {
  const rows = await db.documents.groupBy({
    by: ['status'],
    where: { ...scoped(scope), created_at: { gte: from, lte: to } },
    _count: { status: true },
  });

  const count = (status: DocumentStatus): number =>
    rows.find((row) => row.status === status)?._count.status ?? 0;

  const total = rows.reduce((sum, row) => sum + row._count.status, 0);
  const returned = count(DocumentStatus.Returned);
  const completed = count(DocumentStatus.Completed);
  const denied = count(DocumentStatus.Denied);
  // Closed by the office that filed it: no longer moving, and not a denial.
  const withdrawn = count(DocumentStatus.Withdrawn);

  return {
    total,
    moving: Math.max(0, total - returned - completed - denied - withdrawn),
    returned,
    completed,
    denied,
  };
}

export async function kpis(scope: InternalScope): Promise<InternalKpis> {
  const previous = previousWindow(scope);

  const [cohort, prevCohort, completedInWindow, waitingRows] = await Promise.all([
    cohortCounts(scope, scope.from, scope.to),
    cohortCounts(scope, previous.from, previous.to),
    db.documents.findMany({
      where: { ...scoped(scope), completed_at: { gte: scope.from, lte: scope.to } },
      select: { created_at: true, completed_at: true },
    }),
    waiting(scope),
  ]);

  const spans = completedInWindow
    .filter((row) => row.created_at && row.completed_at)
    .map((row) => Math.abs(row.completed_at!.getTime() - row.created_at!.getTime()) / 86_400_000);

  return {
    filed: cohort.total,
    filed_prev: prevCohort.total,
    moving: cohort.moving,
    returned: cohort.returned,
    completed: cohort.completed,
    denied: cohort.denied,
    avg_turnaround:
      spans.length === 0
        ? null
        : Math.round((spans.reduce((a, b) => a + b, 0) / spans.length) * 1000) / 1000,
    avg_sample: spans.length,
    // Open now, whenever filed.
    overdue: waitingRows.filter((row) => row.overdue).length,
  };
}

/**
 * Every open request and where it is waiting, longest wait first.
 *
 * A request at an office is on that office's clock; one returned for revision is
 * waiting on the office that filed it, with no deadline.
 */
export async function waiting(scope: InternalScope): Promise<WaitingRow[]> {
  const documents = await db.documents.findMany({
    where: { ...scoped(scope), status: { in: activeValues() } },
    select: {
      id: true,
      tracking_number: true,
      document_type: true,
      purpose: true,
      status: true,
      status_changed_at: true,
      created_at: true,
      requestingDepartment: { select: { name: true, code: true, internal_step_hours: true } },
      request_steps: {
        select: {
          status: true,
          started_at: true,
          acted_at: true,
          department: { select: { name: true, code: true, internal_step_hours: true } },
        },
      },
    },
  });

  const at = now().getTime();

  const rows = documents.map((document): WaitingRow => {
    const returned = document.status === DocumentStatus.Returned;

    const step = returned
      ? [...document.request_steps]
          .filter((candidate) => candidate.status === STEP_STATUS.Returned)
          .sort((a, b) => (b.acted_at?.getTime() ?? 0) - (a.acted_at?.getTime() ?? 0))[0]
      : document.request_steps.find((candidate) => candidate.status === STEP_STATUS.Current);

    const since = returned
      ? (step?.acted_at ?? document.status_changed_at)
      : (step?.started_at ?? document.created_at);

    const hours = since ? Math.abs(at - since.getTime()) / 3_600_000 : 0;
    const office = returned ? document.requestingDepartment : step?.department;
    const limit = returned ? null : office ? internalStepHours(office) : null;

    return {
      id: toId(document.id),
      tracking_number: document.tracking_number,
      document_type: document.document_type,
      purpose: document.purpose,
      filed_by: document.requestingDepartment?.code ?? null,
      at: office?.name ?? 'No office',
      at_code: office?.code ?? null,
      returned: returned,
      waited_hours: Math.round(hours * 10) / 10,
      waited: fromHours(hours),
      limit_hours: limit,
      overdue: limit !== null && hours > limit,
      url: `/requests?open=${toId(document.id)}`,
    };
  });

  return rows.sort((a, b) => b.waited_hours - a.waited_hours);
}

/**
 * "Where they're waiting" — open requests per office holding them, with the
 * longest wait and how many are past that office's turnaround. The bottleneck
 * view: one row per office, busiest first.
 */
export async function waitingByOffice(scope: InternalScope): Promise<OfficeRow[]> {
  const rows = await waiting(scope);
  const groups = new Map<string, WaitingRow[]>();

  for (const row of rows) {
    // Returned rows are grouped separately: the same office can both hold
    // requests and be waiting on its own revisions.
    const key = `${row.returned ? 'returned' : 'at'}:${row.at}`;
    groups.set(key, [...(groups.get(key) ?? []), row]);
  }

  return [...groups.values()]
    .map((group): OfficeRow => {
      const first = group[0];

      return {
        label: first.returned ? `${first.at} (returned to them)` : first.at,
        code: first.at_code,
        count: group.length,
        overdue: group.filter((row) => row.overdue).length,
        longest: fromHours(Math.max(...group.map((row) => row.waited_hours))),
        limit: first.limit_hours ? fromHours(first.limit_hours) : null,
        returned: first.returned,
      };
    })
    .sort((a, b) => b.overdue - a.overdue || b.count - a.count);
}

/**
 * "How long each office takes" — the average time an office held a request
 * before acting on it, over hops closed in the window, against its own
 * turnaround.
 */
export async function officeTurnaround(scope: InternalScope): Promise<TurnaroundRow[]> {
  const steps = await db.request_steps.findMany({
    where: {
      started_at: { not: null },
      acted_at: { gte: scope.from, lte: scope.to },
      document: scoped(scope),
    },
    select: {
      department_id: true,
      started_at: true,
      acted_at: true,
      department: { select: { name: true, code: true, internal_step_hours: true } },
    },
  });

  const groups = new Map<string, typeof steps>();

  for (const step of steps) {
    const key = String(step.department_id ?? 'none');
    groups.set(key, [...(groups.get(key) ?? []), step]);
  }

  return [...groups.values()]
    .map((hops): TurnaroundRow => {
      const department = hops[0].department;
      const average =
        hops.reduce(
          (sum, hop) => sum + Math.abs(hop.acted_at!.getTime() - hop.started_at!.getTime()) / 3_600_000,
          0,
        ) / hops.length;
      const limit = department ? internalStepHours(department) : DEFAULT_INTERNAL_STEP_HOURS;

      return {
        label: department?.name ?? 'Unknown office',
        code: department?.code ?? null,
        hops: hops.length,
        avg: fromHours(average),
        avg_hours: Math.round(average * 10) / 10,
        limit: fromHours(limit),
        over: average > limit,
      };
    })
    .sort((a, b) => b.avg_hours - a.avg_hours);
}

/** "Filed by office" — who files internal requests, in the window. */
export async function byRequestingOffice(scope: InternalScope): Promise<BarRow[]> {
  const counts = await db.documents.groupBy({
    by: ['requesting_department_id'],
    where: {
      ...scoped(scope),
      requesting_department_id: { not: null },
      created_at: { gte: scope.from, lte: scope.to },
    },
    _count: { requesting_department_id: true },
  });

  if (counts.length === 0) {
    return [];
  }

  const departments = await db.departments.findMany({
    where: { id: { in: counts.map((row) => row.requesting_department_id!) } },
    select: { id: true, name: true, code: true },
  });

  const max = Math.max(1, ...counts.map((row) => row._count.requesting_department_id));

  return departments
    .map((department): BarRow => {
      const value =
        counts.find((row) => row.requesting_department_id === department.id)?._count
          .requesting_department_id ?? 0;

      return {
        label: department.name,
        code: department.code,
        value,
        pct: Math.round((value / max) * 100),
      };
    })
    .sort((a, b) => b.value - a.value);
}

/** Filed vs completed per week. */
export async function throughput(scope: InternalScope): Promise<ThroughputRow[]> {
  const [created, completed] = await Promise.all([
    db.documents.findMany({
      where: { ...scoped(scope), created_at: { gte: scope.from, lte: scope.to } },
      select: { created_at: true },
    }),
    db.documents.findMany({
      where: { ...scoped(scope), completed_at: { gte: scope.from, lte: scope.to } },
      select: { completed_at: true },
    }),
  ]);

  const bucket = (dates: Array<Date | null>): Map<string, number> => {
    const buckets = new Map<string, number>();

    for (const date of dates) {
      if (!date) {
        continue;
      }

      const key = toDateString(startOfWeekSunday(date));
      buckets.set(key, (buckets.get(key) ?? 0) + 1);
    }

    return buckets;
  };

  const createdBuckets = bucket(created.map((row) => row.created_at));
  const completedBuckets = bucket(completed.map((row) => row.completed_at));

  const weeks: ThroughputRow[] = [];
  let cursor = startOfWeekSunday(scope.from);
  const end = scope.to;

  while (cursor <= end) {
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

/** Carbon's `startOfWeek(Carbon::SUNDAY)`, on the naive encoding. */
function startOfWeekSunday(date: Date): Date {
  const start = new Date(date.getTime());
  start.setUTCHours(0, 0, 0, 0);

  return new Date(start.getTime() - start.getUTCDay() * 86_400_000);
}

