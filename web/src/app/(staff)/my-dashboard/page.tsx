import type { Metadata } from 'next';
import { db, id } from '@/lib/db';
import { now, subDays } from '@/lib/time';
import { requireActor } from '@/lib/auth/actor';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { DocumentStatus, activeValues } from '@/lib/domain/document-status';
import { ORIGIN_INTERNAL } from '@/lib/domain/scope';
import { REVIEW_SELECT, collection } from '@/lib/domain/request-review';
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

  const [triageRows, activeRows, completedRows] = await Promise.all([
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

    db.documents.findMany({
      where: {
        ...mine,
        status: DocumentStatus.Completed,
        status_changed_at: { gte: subDays(now(), 90) },
      },
      orderBy: { status_changed_at: 'desc' },
      select: REVIEW_SELECT,
      take: 100,
    }),
  ]);

  const [triage, active, completed] = await Promise.all([
    collection(triageRows, actor),
    collection(activeRows, actor),
    collection(completedRows, actor),
  ]);

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

  const activeTab =
    tab === 'triage' || tab === 'active' || tab === 'completed'
      ? tab
      : triage.length > 0
        ? 'triage'
        : 'active';

  return (
    <AuthenticatedPage title="Requests">
      {pressing.length > 0 ? <AtRiskStrip rows={pressing} /> : null}

      <StaffQueues
        triage={triage}
        active={active}
        completed={completed}
        activeTab={activeTab}
        openTracking={open ?? null}
      />
    </AuthenticatedPage>
  );
}
