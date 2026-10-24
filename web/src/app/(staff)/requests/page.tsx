import type { Metadata } from 'next';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import { requireActor } from '@/lib/auth/actor';
import { routes } from '@/lib/routes';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { inboxFor } from '@/lib/domain/internal-inbox';
import { InternalQueues } from './queues';

export const metadata: Metadata = { title: 'Internal Requests' };

/**
 * The Internal tab: office-to-office requests (`requests/index.blade.php`).
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
  if (open && /^\d+$/.test(open)) {
    redirect(routes.requestsShow(open));
  }

  const inbox = await inboxFor(actor, q ?? '');

  return (
    <AuthenticatedPage
      title="Internal Requests"
      fixedHeight
      pageActions={
        actor.can('create internal requests') && actor.departmentId !== null ? (
          <Link href={routes.requestsCreate()} className="header-action" title="File an internal request">
            <svg
              fill="none"
              stroke="currentColor"
              strokeWidth="2.2"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <path strokeLinecap="round" d="M12 5v14M5 12h14" />
            </svg>
            <span>File Request</span>
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
