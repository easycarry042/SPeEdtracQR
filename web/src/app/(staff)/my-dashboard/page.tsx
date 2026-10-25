import type { Metadata } from 'next';
import { db, id } from '@/lib/db';
import { requireActor } from '@/lib/auth/actor';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { DocumentStatus, activeValues } from '@/lib/domain/document-status';
import { ORIGIN_INTERNAL } from '@/lib/domain/scope';
import { REVIEW_SELECT, collection } from '@/lib/domain/request-review';
import { completedCount } from '@/lib/domain/completed-history';
import { atRisk } from '@/lib/domain/sla-watch';
import { StaffQueues } from './queues';
import { AtRiskStrip } from './at-risk-strip';

export const metadata: Metadata = { title: 'Requests' };

/**
 * The staff cockpit: the requests assigned to this person.
 *
 * At-risk work lives HERE, not on the supervisor desk — the supervisor assigns,
 * the staff member advances, so the warning belongs to whoever can act on it.
 */
export default async function StaffDashboardPage({
  searchParams,
}: {
  searchParams: Promise<{ tab?: string; open?: string }>;
}) {
  const actor = await requireActor();
  const { tab, open } = await searchParams;

  const mine = {
    deleted_at: null,
    origin: { not: ORIGIN_INTERNAL },
    assigned_to: id(actor.id),
  } as const;

  const deptFilter = actor.can('view all documents')
    ? {}
    : actor.departmentId !== null
      ? { department_id: id(actor.departmentId) }
      : { department_id: { lt: 0 } };

  const [openRows, triageRows, activeRows, completedTotal] = await Promise.all([
    // Department Pool ("Open Requests"): unassigned requests in staff's department
    db.documents.findMany({
      where: {
        deleted_at: null,
        origin: { not: ORIGIN_INTERNAL },
        assigned_to: null,
        ...deptFilter,
        status: { in: [DocumentStatus.Pending, DocumentStatus.InProgress] },
      },
      orderBy: { created_at: 'desc' },
      select: REVIEW_SELECT,
      take: 100,
    }),

    // Assigned but not yet accepted — the decision only this person can make.
    db.documents.findMany({
      where: {
        ...mine,
        accepted_at: null,
        status: { in: [DocumentStatus.Pending, DocumentStatus.InProgress] },
      },
      orderBy: { assigned_at: 'desc' },
      select: REVIEW_SELECT,
    }),

    db.documents.findMany({
      where: { ...mine, accepted_at: { not: null }, status: { in: activeValues() } },
      orderBy: { status_changed_at: 'desc' },
      select: REVIEW_SELECT,
      take: 200,
    }),

    // The whole history, paged by the Completed tab itself; only the count here.
    completedCount(actor),
  ]);

  const [openRequests, triage, active] = await Promise.all([
    collection(openRows, actor),
    collection(triageRows, actor),
    collection(activeRows, actor),
  ]);

  // Requests sent back by the supervisor for revision
  const returned = active.filter(
    (row) => Boolean(row.remarks) || row.status === DocumentStatus.Returned,
  );

  // Worst first — the order a queue should be worked in.
  const pressing = atRisk(
    activeRows.map((row) => ({
      id: Number(row.id),
      trackingNumber: row.tracking_number,
      documentType: row.document_type,
      status: row.status,
      status_changed_at: row.status_changed_at,
      updated_at: row.updated_at,
      created_at: row.created_at,
    })),
  );

  const normalizedTab = tab === 'open' ? 'pool' : tab;
  const activeTab =
    normalizedTab === 'pool' ||
    normalizedTab === 'active' ||
    normalizedTab === 'returned' ||
    normalizedTab === 'triage' ||
    normalizedTab === 'completed'
      ? normalizedTab
      : returned.length > 0
        ? 'returned'
        : triage.length > 0
          ? 'triage'
          : active.length > 0
            ? 'active'
            : openRequests.length > 0
              ? 'pool'
              : 'active';

  return (
    <AuthenticatedPage title="Requests">
      {pressing.length > 0 ? <AtRiskStrip rows={pressing} /> : null}

      <StaffQueues
        openRequests={openRequests}
        triage={triage}
        active={active}
        returned={returned}
        completedCount={completedTotal}
        activeTab={activeTab as 'pool' | 'active' | 'returned' | 'triage' | 'completed'}
        openTracking={open ?? null}
      />
    </AuthenticatedPage>
  );
}
