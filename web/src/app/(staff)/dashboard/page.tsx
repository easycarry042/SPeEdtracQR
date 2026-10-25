import type { Metadata } from 'next';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import type { Prisma } from '@prisma/client';
import { db, id } from '@/lib/db';
import { now, subDays } from '@/lib/time';
import { requireActor } from '@/lib/auth/actor';
import { routes } from '@/lib/routes';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { StaffStatTile } from '@/components/staff-stat-tile';
import { DocumentStatus, activeValues } from '@/lib/domain/document-status';
import { ORIGIN_INTERNAL, canViewAll, documentScope } from '@/lib/domain/scope';
import { REVIEW_SELECT, assignableStaff, collection } from '@/lib/domain/request-review';
import { DashboardQueues } from './queues';
import { GlanceStrip } from './glance-strip';
import { kpis } from '@/lib/domain/admin-analytics';
import { completedCount } from '@/lib/domain/completed-history';

export const metadata: Metadata = { title: 'Dashboard' };

/**
 * The supervisor desk (`dashboard.blade.php`).
 *
 * Three tabs over the office's citizen requests — Unassigned, In Progress and
 * Completed (the office's whole history, paged) — plus the 4 headline KPI
 * tiles, the 30-day Reports glance strip, and the Staff Workload table.
 *
 * Internal office-to-office requests are NOT here: they travel their own
 * endorsement chain on the Internal tab, with their own sidebar badge.
 */
export default async function DashboardPage({
  searchParams,
}: {
  searchParams: Promise<{ tab?: string; q?: string; open?: string }>;
}) {
  const actor = await requireActor();

  // Staff have no supervisor desk — their work lives on the cockpit.
  if (!canViewAll(actor)) {
    redirect(routes.staffDashboard());
  }

  const { tab, q, open } = await searchParams;
  const search = (q ?? '').trim();

  // A department-head supervisor manages only their OWN office; org-wide admins
  // (`manage system`) see everything.
  const spansDepartments = actor.can('manage system');
  const departmentId = spansDepartments ? null : actor.departmentId;

  /** This office's requests. */
  const deptScope: Prisma.documentsWhereInput =
    departmentId === null ? {} : { department_id: id(departmentId) };

  /**
   * Scope for queues of UNOWNED work: this office's requests plus every request
   * no office owns yet. Without the null branch, unrouted citizen submissions
   * are invisible to every supervisor — yet they are exactly who gets belled
   * about them.
   */
  const unroutedScope: Prisma.documentsWhereInput =
    departmentId === null
      ? {}
      : { OR: [{ department_id: id(departmentId) }, { department_id: null }] };

  const citizenBase: Prisma.documentsWhereInput = {
    deleted_at: null,
    origin: { not: ORIGIN_INTERNAL },
  };

  const scope = documentScope(actor);

  const [
    dept,
    pendingRows,
    activeRows,
    clearanceRows,
    completedTotal,
    pendingOnly,
    inProgressCount,
    staff,
    glance,
  ] = await Promise.all([
    departmentId
      ? db.departments.findFirst({
          where: { id: id(departmentId) },
          select: { id: true, name: true, code: true },
        })
      : Promise.resolve(null),

    // Awaiting approval: still Pending and not yet assigned. Assigning removes
    // it from this queue.
    db.documents.findMany({
      where: {
        AND: [scope, citizenBase, unroutedScope],
        status: DocumentStatus.Pending,
        assigned_to: null,
      },
      orderBy: { created_at: 'desc' },
      select: REVIEW_SELECT,
      take: 200,
    }),

    // The office's work in flight (Ongoing / On Hold).
    db.documents.findMany({
      where: {
        AND: [scope, citizenBase, deptScope],
        status: { in: [DocumentStatus.InProgress, DocumentStatus.OnHold] },
        assigned_to: { not: null },
      },
      orderBy: { status_changed_at: 'desc' },
      select: REVIEW_SELECT,
      take: 200,
    }),

    // For Clearance: submitted by staff awaiting supervisor approval.
    db.documents.findMany({
      where: {
        AND: [scope, citizenBase, deptScope],
        status: DocumentStatus.InReview,
      },
      orderBy: { status_changed_at: 'desc' },
      select: REVIEW_SELECT,
      take: 200,
    }),

    // Finished work is the whole history, paged by the Completed tab itself
    // (history.data); only its count is needed here.
    completedCount(actor),

    // Headline count: all Pending citizen requests in scope.
    db.documents.count({
      where: {
        AND: [scope, citizenBase, unroutedScope],
        status: DocumentStatus.Pending,
      },
    }),

    // Headline count: open non-Pending requests (or assigned active requests).
    db.documents.count({
      where: {
        AND: [scope, citizenBase, deptScope],
        status: {
          in: activeValues().filter((s) => s !== DocumentStatus.Pending),
        },
      },
    }),

    assignableStaff(departmentId),

    // A one-line glance at the office's last 30 days, from the same numbers as
    // Reports (the queue stays this page's job; the charts live there).
    departmentId === null ? Promise.resolve(null) : kpis(subDays(now(), 29), now(), departmentId),
  ]);

  const staffIds = staff.map((s) => id(s.id));
  const [staffActiveRows, staffCompletedRows, pending, active, clearance] = await Promise.all([
    staffIds.length === 0
      ? Promise.resolve([])
      : db.documents.groupBy({
          by: ['assigned_to'],
          where: {
            deleted_at: null,
            origin: { not: ORIGIN_INTERNAL },
            assigned_to: { in: staffIds },
            status: { in: activeValues() },
          },
          _count: { assigned_to: true },
        }),
    staffIds.length === 0
      ? Promise.resolve([])
      : db.documents.groupBy({
          by: ['assigned_to'],
          where: {
            deleted_at: null,
            origin: { not: ORIGIN_INTERNAL },
            assigned_to: { in: staffIds },
            status: DocumentStatus.Completed,
          },
          _count: { assigned_to: true },
        }),
    collection(pendingRows, actor),
    collection(activeRows, actor),
    collection(clearanceRows, actor),
  ]);

  const activeByStaff = new Map(
    staffActiveRows.map((r) => [Number(r.assigned_to), r._count.assigned_to]),
  );
  const completedByStaff = new Map(
    staffCompletedRows.map((r) => [Number(r.assigned_to), r._count.assigned_to]),
  );

  const staffWorkload = staff.map((member) => ({
    id: member.id,
    name: member.name,
    active_count: activeByStaff.get(member.id) ?? 0,
    completed_count: completedByStaff.get(member.id) ?? 0,
  }));

  const maxActive = Math.max(1, ...staffWorkload.map((m) => m.active_count));
  const allIdle = staffWorkload.every((m) => m.active_count === 0 && m.completed_count === 0);

  const unassignedCount = pending.length;
  const totalForCompletion = pendingOnly + inProgressCount + completedTotal;
  const completionRate =
    totalForCompletion > 0 ? Math.round((completedTotal / totalForCompletion) * 100) : null;

  const activeTab =
    tab === 'pending' || tab === 'inprogress' || tab === 'clearance' || tab === 'completed'
      ? tab
      : tab === 'active'
        ? 'inprogress'
        : tab === 'done'
          ? 'completed'
          : clearance.length > 0
            ? 'clearance'
            : pending.length > 0
              ? 'pending'
              : 'inprogress';

  return (
    <AuthenticatedPage
      title="Dashboard"
      pageActions={
        dept ? (
          <>
            <span className="hidden min-w-0 items-center gap-2 rounded-full border border-hairline-strong bg-green-wash px-3 py-1 sm:inline-flex">
              <span className="truncate text-sm font-bold text-green-deep">
                {dept.name}{' '}
                {dept.code ? <span className="font-medium text-green">({dept.code})</span> : null}
              </span>
            </span>
            {completionRate !== null ? (
              <span className="hidden shrink-0 items-center gap-1.5 text-sm md:inline-flex">
                <span className="text-xs font-semibold uppercase tracking-wide text-ink-soft">
                  Completion
                </span>
                <span className="font-bold text-green-deep">{completionRate}%</span>
              </span>
            ) : null}
          </>
        ) : undefined
      }
    >
      <div className="page-shell page-shell-loose">
        {/* Headline counts, on the same gradient ramp as the staff dashboard */}
        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
          <StaffStatTile
            label="Pending"
            value={pendingOnly}
            tone="deep"
            icon="hourglass"
          />
          <StaffStatTile
            label="Unassigned"
            value={unassignedCount}
            tone="mid"
            icon="people"
          />
          <StaffStatTile
            label="In Progress"
            value={inProgressCount}
            tone="bright"
            icon="clock"
          />
          <StaffStatTile
            label="Completed"
            value={completedTotal}
            tone="brightest"
            icon="check"
          />
        </div>

        {glance ? <GlanceStrip glance={glance} /> : null}

        {/* ── Staff workload ───────────────────────────────────────────────── */}
        {staffWorkload.length > 0 ? (
          <section>
            <div className="mb-4 flex flex-wrap items-center gap-3">
              <h2 className="st-section-title">Staff workload</h2>
              <span className="pill p-muted">Assignment distribution</span>
            </div>

            <div className="st-card">
              <div className="st-scroll">
                <table className="st-table">
                  <thead>
                    <tr>
                      <th>Staff member</th>
                      <th>Active</th>
                      <th>Completed</th>
                      <th className="min-w-[170px]">Load</th>
                    </tr>
                  </thead>
                  <tbody>
                    {staffWorkload.map((member) => (
                      <tr key={member.id}>
                        <td>
                          <Link
                            href={routes.staffProfile(member.id)}
                            className="st-name hover:underline"
                          >
                            {member.name}
                          </Link>
                        </td>
                        <td className="font-semibold text-green-deep">{member.active_count}</td>
                        <td className="text-ink-soft">{member.completed_count}</td>
                        <td>
                          <div className="h-2 w-32 overflow-hidden rounded-full bg-[#e6ece8]">
                            <div
                              className={`h-2 rounded-full ${
                                member.active_count > 0 ? 'bg-green' : 'bg-[#cdd9d2]'
                              }`}
                              style={{
                                width: `${Math.round((member.active_count / maxActive) * 100)}%`,
                              }}
                            />
                          </div>
                        </td>
                      </tr>
                    ))}

                    {allIdle ? (
                      <tr className="st-empty">
                        <td colSpan={4}>
                          No assignments yet — assign the pending requests below.
                        </td>
                      </tr>
                    ) : null}
                  </tbody>
                </table>
              </div>
            </div>
          </section>
        ) : null}

        {/* ── The office's requests ────────────────────────────────────────── */}
        <DashboardQueues
          pending={pending}
          active={active}
          clearance={clearance}
          completedCount={completedTotal}
          assignableStaff={staff.map((member) => ({ id: member.id, name: member.name }))}
          activeTab={activeTab}
          search={search}
          openTracking={open ?? null}
          officeName={actor.departmentName}
          spansDepartments={spansDepartments}
        />
      </div>
    </AuthenticatedPage>
  );
}
