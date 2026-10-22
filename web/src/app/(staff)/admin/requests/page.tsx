import type { Metadata } from 'next';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { db } from '@/lib/db';
import { requireRole } from '@/lib/auth/actor';
import { ROLE_SUPER_ADMIN } from '@/lib/auth/rbac';
import { REVIEW_SELECT, collection } from '@/lib/domain/request-review';
import { citizenFacing } from '@/lib/domain/scope';
import { OversightQueues } from './queues';

export const metadata: Metadata = { title: 'Requests' };

/** Lateness is measured against the clock, so nothing here may be cached. */
export const dynamic = 'force-dynamic';

/**
 * The super admin's Requests page.
 *
 * Port of `RequestOverviewController@index`: every citizen request in the city,
 * read-only, in the shared Review modal with oversight's one write — Notify.
 * `?open=SPD-…` opens that request, which is where the command center's lists,
 * the unrouted alert and a super admin's tracking-page links all land.
 */
export default async function AdminRequestsPage({
  searchParams,
}: {
  searchParams: Promise<{ open?: string }>;
}) {
  const actor = await requireRole(ROLE_SUPER_ADMIN);
  const { open } = await searchParams;

  /**
   * Unbounded, as the Blade page was: the tabs, the search and the pager all
   * run on the client over this one payload, so a `take` would make the queue
   * counts lie and could hide the very request an `?open=` link was sent to
   * open.
   */
  const documents = await db.documents.findMany({
    where: { ...citizenFacing, deleted_at: null },
    orderBy: { created_at: 'desc' },
    select: REVIEW_SELECT,
  });

  const requests = await collection(documents, actor);

  return (
    <AuthenticatedPage title="Requests">
      <OversightQueues requests={requests} openTracking={open ? open.trim().toUpperCase() : null} />
    </AuthenticatedPage>
  );
}
