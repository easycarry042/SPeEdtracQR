import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import { requireActor } from '@/lib/auth/actor';
import { routes } from '@/lib/routes';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { inboxFor } from '@/lib/domain/internal-inbox';
import { internalRequestDetail } from '@/lib/domain/internal-request';
import { InternalQueues } from './queues';
import { RequestModal } from './request-modal';
import { InternalRequestView } from './[document]/request-detail';

export const metadata: Metadata = { title: 'Internal Requests' };

/** The open request and its custody trail are live, so nothing here caches. */
export const dynamic = 'force-dynamic';

/**
 * The Internal tab: office-to-office requests (`requests/index.blade.php`).
 *
 * `?open=<id>` opens that request in a modal over the queue; closing it keeps
 * the tab and search the queue had.
 */
export default async function InternalRequestsPage({
  searchParams,
}: {
  searchParams: Promise<{ tab?: string; q?: string; open?: string }>;
}) {
  const actor = await requireActor();

  if (!actor.can('act on internal requests') && !actor.can('create internal requests')) {
    redirect(routes.welcome());
  }

  const { tab, q, open } = await searchParams;
  const openId = open && /^\d+$/.test(open) ? Number(open) : null;

  const [inbox, detail] = await Promise.all([
    inboxFor(actor, q ?? ''),
    openId !== null ? internalRequestDetail(openId, actor) : Promise.resolve(null),
  ]);

  const closeHref = routes.requestsIndex({
    tab: tab === 'filed' || tab === 'closed' ? tab : undefined,
    q: q || undefined,
  });

  return (
    <AuthenticatedPage title="Internal Requests" fixedHeight>
      <InternalQueues
        inbox={inbox}
        activeTab={tab === 'filed' || tab === 'closed' ? tab : 'awaiting'}
      />
      {detail ? (
        <RequestModal closeHref={closeHref} labelledBy={`internal-${detail.id}-title`}>
          <InternalRequestView detail={detail} closeHref={closeHref} />
        </RequestModal>
      ) : null}
    </AuthenticatedPage>
  );
}
