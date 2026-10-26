import type { Metadata } from 'next';
import { notFound, redirect } from 'next/navigation';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { requireActor } from '@/lib/auth/actor';
import { routes } from '@/lib/routes';
import { internalRequestDetail } from '@/lib/domain/internal-request';
import { InternalRequestView } from './request-detail';

export const metadata: Metadata = { title: 'Internal Request' };

/** The chain and the custody trail are live, so nothing here caches. */
export const dynamic = 'force-dynamic';

/**
 * One internal request as a page.
 *
 * Port of `InternalRequestController@show`. Every staff member may read a
 * request; what differs is which panel they get, and that follows the same
 * gates the server enforces. Anyone with the Internal page opens it there, in
 * the request modal — notifications, scans and the filing receipt all link
 * here, so this is where they are sent on. Everyone else reads it as a page.
 */
export default async function InternalRequestPage({
  params,
}: {
  params: Promise<{ document: string }>;
}) {
  const actor = await requireActor();
  const { document } = await params;
  const documentId = Number(document);

  if (!Number.isInteger(documentId) || documentId <= 0) {
    notFound();
  }

  const detail = await internalRequestDetail(documentId, actor);

  // A citizen request is not an internal one — its own page is the tracker.
  if (!detail) {
    notFound();
  }

  if (actor.can('act on internal requests') || actor.can('create internal requests')) {
    redirect(routes.requestsIndex({ open: String(documentId) }));
  }

  return (
    <AuthenticatedPage title="Internal Request">
      <InternalRequestView detail={detail} closeHref={routes.requestsIndex()} />
    </AuthenticatedPage>
  );
}
