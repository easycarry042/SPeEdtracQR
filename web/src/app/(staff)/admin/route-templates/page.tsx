import type { Metadata } from 'next';
import Link from 'next/link';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { requirePermission } from '@/lib/auth/actor';
import { routes } from '@/lib/routes';
import { routeTemplateList } from '@/lib/domain/admin-catalog';
import { TemplateTable } from './template-table';

export const metadata: Metadata = { title: 'Route Templates' };

export const dynamic = 'force-dynamic';

/**
 * The endorsement chains prefilled when an office files an internal request.
 *
 * Port of `Admin\RouteTemplateController@index`. A template is a starting
 * point, not a rule: chains already materialised onto filed requests are theirs
 * from then on, so editing or deleting a template never reaches back into work
 * in flight.
 */
export default async function RouteTemplatesPage({
  searchParams,
}: {
  searchParams: Promise<{ saved?: string }>;
}) {
  await requirePermission('manage system');

  const { saved } = await searchParams;
  const templates = await routeTemplateList();

  const flash =
    saved === 'created' ? 'Route template created.' : saved === 'updated' ? 'Route template updated.' : null;

  return (
    <AuthenticatedPage
      title="Route Templates"
      pageActions={
        <Link href={routes.adminRouteTemplatesCreate()} className="cr-btn cr-btn-primary cr-btn-sm">
          Add template
        </Link>
      }
    >
      <p className="text-[length:var(--fs-small)] text-ink-soft">
        Endorsement chains prefilled when an office files an internal request.
      </p>

      <TemplateTable templates={templates} flash={flash} />
    </AuthenticatedPage>
  );
}
