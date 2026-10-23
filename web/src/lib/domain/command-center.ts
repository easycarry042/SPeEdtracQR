import { db, id } from '@/lib/db';
import { citizenFacing } from '@/lib/domain/scope';
import { recentOnRequests, type ActivityRow } from '@/lib/activity';
import { describeRows, isEmptyUpdate, type DescribedRow } from '@/lib/domain/activity-line';
import * as citizen from '@/lib/domain/admin-analytics';
import * as internal from '@/lib/domain/internal-analytics';
import * as services from '@/lib/domain/service-report';
import { endOfDay, now, startOfDay, subDays } from '@/lib/time';

/**
 * One page, two audiences: the super admin's command center (`/admin/dashboard`,
 * city-wide) and a supervisor's Reports (`/reports`, their office only).
 *
 * Port of `App\Support\CommandCenter`. It replaced, for supervisors, a separate
 * Analytics page and Services report whose figures disagreed with their own
 * dashboard — they counted every office's internal requests as theirs.
 */

/** Selectable look-back windows, in days. */
export const RANGES = [7, 30, 90] as const;

/**
 * The two sides. Citizen requests and office-to-office requests live different
 * lives — stage clocks versus endorsement hops — so each is read on its own and
 * they are NEVER added together.
 */
export const VIEWS = ['citizen', 'internal'] as const;

export type View = (typeof VIEWS)[number];

export type Office = { id: number; name: string; code: string | null } | null;

export type Filters = {
  view: View;
  range: number;
  document_type: string | null;
  /** Whether anything is narrowed from the default, so the page can offer a reset. */
  active: boolean;
};

export type CommandCenterData = {
  audience: 'admin' | 'office';
  office: Office;
  filters: Filters;
  documentTypes: string[];
  updatedAt: Date;
  /** Citizen side — present only when `filters.view === 'citizen'`. */
  kpis?: citizen.Kpis;
  departmentVolume?: citizen.OfficeVolumeRow[];
  unrouted?: citizen.UnroutedRow[];
  throughput?: citizen.ThroughputRow[];
  statusDistribution?: Array<{ status: string; count: number }>;
  staffWorkload?: citizen.StaffRow[];
  timeByStage?: citizen.StageTimeRow[];
  typeBreakdown?: citizen.TypeRow[];
  requirementStats?: services.RequirementStats;
  bookings?: services.ResourceUseRow[];
  atRisk?: citizen.AtRiskRow[];
  stalledHolds?: citizen.StalledHoldRow[];
  /** Internal side — present only when `filters.view === 'internal'`. */
  internalKpis?: internal.InternalKpis;
  internalWaiting?: internal.WaitingRow[];
  waitingByOffice?: internal.OfficeRow[];
  officeTurnaround?: internal.TurnaroundRow[];
  byRequestingOffice?: internal.BarRow[];
  recentActivity: DescribedRow[];
};

/** Read and clamp the query string, so a hand-edited URL can't widen the window. */
export function filters(
  params: URLSearchParams,
  documentTypes: string[],
): Filters & { from: Date; to: Date } {
  const requestedView = params.get('view');
  const view: View = requestedView === 'internal' ? 'internal' : 'citizen';

  const requestedRange = Number.parseInt(params.get('range') ?? '', 10);
  const range = (RANGES as readonly number[]).includes(requestedRange) ? requestedRange : 30;

  // Only a type that actually exists — an unknown one would silently show zero
  // everywhere and read as "no work came in".
  const requested = params.get('document_type');
  const documentType = requested && documentTypes.includes(requested) ? requested : null;

  return {
    view,
    range,
    document_type: documentType,
    active: documentType !== null || range !== 30,
    // Inclusive of both end days, so "7 days" means seven whole days.
    from: startOfDay(subDays(now(), range - 1)),
    to: endOfDay(now()),
  };
}

/** The citizen request types on offer, for the filter dropdown. */
export async function citizenDocumentTypes(departmentId: number | null): Promise<string[]> {
  const rows = await db.documents.groupBy({
    by: ['document_type'],
    where: {
      deleted_at: null,
      ...citizenFacing,
      ...(departmentId ? { department_id: id(departmentId) } : {}),
    },
  });

  return rows.map((row) => row.document_type).sort((a, b) => a.localeCompare(b));
}

/**
 * Everything the page renders.
 *
 * `office` null means city-wide (the super admin's command center); an office
 * means that supervisor's Reports.
 */
export async function data(
  params: URLSearchParams,
  office: Office,
): Promise<CommandCenterData> {
  const departmentId = office?.id ?? null;
  const documentTypes = await citizenDocumentTypes(departmentId);
  const { from, to, ...active } = filters(params, documentTypes);

  const shared = {
    audience: (office ? 'office' : 'admin') as 'admin' | 'office',
    office,
    filters: active,
    documentTypes,
    updatedAt: now(),
  };

  if (active.view === 'internal') {
    const scope: internal.InternalScope = { from, to, departmentId };

    const [
      internalKpis,
      internalWaiting,
      waitingByOffice,
      officeTurnaround,
      byRequestingOffice,
      throughput,
      recentActivity,
    ] = await Promise.all([
      internal.kpis(scope),
      internal.waiting(scope),
      internal.waitingByOffice(scope),
      internal.officeTurnaround(scope),
      internal.byRequestingOffice(scope),
      internal.throughput(scope),
      recentWorded({ internal: true, departmentId }),
    ]);

    return {
      ...shared,
      internalKpis,
      internalWaiting,
      waitingByOffice,
      officeTurnaround,
      byRequestingOffice,
      throughput,
      recentActivity,
    };
  }

  const scope = { from, to, documentType: active.document_type, departmentId };

  const [
    kpis,
    departmentVolume,
    unrouted,
    throughput,
    statusDistribution,
    staffWorkload,
    timeByStage,
    typeBreakdown,
    requirementStats,
    bookings,
    atRisk,
    stalledHolds,
    recentActivity,
  ] = await Promise.all([
    citizen.kpis(from, to, departmentId, active.document_type),
    // The city page compares offices and flags requests that reached none; one
    // office's page has nothing to compare.
    office ? Promise.resolve([]) : citizen.departmentVolume(scope),
    office ? Promise.resolve([]) : citizen.unrouted(scope),
    citizen.throughput(scope),
    citizen.statusDistribution(departmentId),
    citizen.staffWorkload(scope),
    citizen.timeByStage(scope),
    citizen.typeBreakdown(scope),
    // From the retired Services report (all time).
    services.requirementStats(departmentId),
    // An office's own bookable resources (the city's live on the Resources page).
    office ? services.bookingsByResource(departmentId) : Promise.resolve([]),
    citizen.atRisk(scope),
    // Held past their hold date: invisible to atRisk() because a hold nulls the
    // stage SLA, so they get their own panel.
    citizen.stalledHolds(scope),
    recentWorded({ internal: false, departmentId }),
  ]);

  return {
    ...shared,
    kpis,
    departmentVolume,
    unrouted,
    throughput,
    statusDistribution,
    staffWorkload,
    timeByStage,
    typeBreakdown,
    requirementStats,
    bookings,
    atRisk,
    stalledHolds,
    recentActivity,
  };
}

/**
 * The audit strip, worded like the Audit Log page (ActivityLine). Empty
 * "updated" rows are dropped, so a few extra are read to still fill the strip.
 */
async function recentWorded(options: { internal: boolean; departmentId: number | null }): Promise<DescribedRow[]> {
  const rows: ActivityRow[] = await recentOnRequests({ ...options, limit: 40 });

  return describeRows(rows.filter((row) => !isEmptyUpdate(row)).slice(0, 25));
}
