import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import type { Prisma } from '@prisma/client';
import { db, id } from '@/lib/db';
import { now, subDays } from '@/lib/time';
import { requireActor } from '@/lib/auth/actor';
import { routes } from '@/lib/routes';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { DocumentStatus, activeValues } from '@/lib/domain/document-status';
import { ORIGIN_INTERNAL, canViewAll, documentScope } from '@/lib/domain/scope';
import { REVIEW_SELECT, assignableStaff, collection } from '@/lib/domain/request-review';
import { DashboardQueues } from './queues';
import { GlanceStrip } from './glance-strip';
import { kpis } from '@/lib/domain/admin-analytics';

export const metadata: Metadata = { title: 'Dashboard' };

/**
 * The supervisor desk.
 *
 * Three tabs over the office's citizen requests — Unassigned, In Progress and
 * Completed (last 90 days) — and the Review modal, which assigns, reassigns and
 * denies. The Assignments page these replaced listed the same work with half
 * the tools.
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
  const departmentId = actor.can('manage system') ? null : actor.departmentId;

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

  const citizenOnly: Prisma.documentsWhereInput = {
    deleted_at: null,
    origin: { not: ORIGIN_INTERNAL },
    ...(search === ''
      ? {}
      : {
          OR: [
            { tracking_number: { contains: search, mode: 'insensitive' } },
            { document_type: { contains: search, mode: 'insensitive' } },
            { citizen_name: { contains: search, mode: 'insensitive' } },
          ],
        }),
  };

  const scope = documentScope(actor);

  const [pendingRows, activeRows, completedRows, staff, glance] = await Promise.all([
    // Awaiting approval: still Pending and not yet assigned. Assigning removes
    // it from this queue.
    db.documents.findMany({
      where: {
        AND: [scope, citizenOnly, unroutedScope],
        status: DocumentStatus.Pending,
        assigned_to: null,
      },
      orderBy: { created_at: 'desc' },
      select: REVIEW_SELECT,
      take: 200,
    }),

    // The office's work in flight. Without this a supervisor could only see
    // what nobody had started — the moment a request was assigned it dropped
    // off their screen until it came back completed.
    db.documents.findMany({
      where: {
        AND: [scope, citizenOnly, deptScope],
        status: { in: activeValues() },
        assigned_to: { not: null },
      },
      orderBy: { status_changed_at: 'desc' },
      select: REVIEW_SELECT,
      take: 200,
    }),

    // Finished work. The last 90 days keeps the payload bounded; older ones
    // live in Reports.
    db.documents.findMany({
      where: {
        AND: [scope, citizenOnly, deptScope],
        status: DocumentStatus.Completed,
        status_changed_at: { gte: subDays(now(), 90) },
      },
      orderBy: { status_changed_at: 'desc' },
      select: REVIEW_SELECT,
      take: 200,
    }),

    assignableStaff(actor.can('manage system') ? null : actor.departmentId),

    // A one-line glance at the office's last 30 days, from the same numbers as
    // Reports (the queue stays this page's job; the charts live there).
    departmentId === null ? Promise.resolve(null) : kpis(subDays(now(), 29), now(), departmentId),
  ]);

  const [pending, active, completed] = await Promise.all([
    collection(pendingRows, actor),
    collection(activeRows, actor),
    collection(completedRows, actor),
  ]);

  const activeTab =
    tab === 'pending' || tab === 'inprogress' || tab === 'completed' ? tab : 'pending';

  return (
    <AuthenticatedPage title="Dashboard">
      {glance ? <GlanceStrip glance={glance} /> : null}

      <DashboardQueues
        pending={pending}
        active={active}
        completed={completed}
        assignableStaff={staff.map((member) => ({ id: member.id, name: member.name }))}
        activeTab={activeTab}
        search={search}
        openTracking={open ?? null}
        officeName={actor.departmentName}
      />
    </AuthenticatedPage>
  );
}
