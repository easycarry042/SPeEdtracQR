import type { Metadata } from 'next';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import { requireActor } from '@/lib/auth/actor';
import { routes } from '@/lib/routes';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { inboxFor } from '@/lib/domain/internal-inbox';
import { InternalQueues } from './queues';

export const metadata: Metadata = { title: 'Internal' };

/**
 * The Internal tab: office-to-office requests.
 *
 * These are NOT on the dashboard — they travel their own endorsement chain
 * rather than the citizen assign/advance flow, so they get their own page and
 * their own sidebar badge.
 */
export default async function InternalRequestsPage({
  searchParams,
}: {
  searchParams: Promise<{ tab?: string; q?: string }>;
}) {
  const actor = await requireActor();

  if (!actor.can('act on internal requests') && !actor.can('create internal requests')) {
    redirect(routes.welcome());
  }

  const { tab, q } = await searchParams;
  const inbox = await inboxFor(actor, q ?? '');

  return (
    <AuthenticatedPage
      title="Internal"
      pageActions={
        actor.can('create internal requests') ? (
          <Link href={routes.requestsCreate()} className="cr-btn cr-btn-primary cr-btn-sm">
            File a request
          </Link>
        ) : null
      }
    >
      <InternalQueues
        inbox={inbox}
        activeTab={tab === 'filed' || tab === 'closed' ? tab : 'awaiting'}
      />
    </AuthenticatedPage>
  );
}
