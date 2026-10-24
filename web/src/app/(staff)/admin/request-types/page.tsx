import type { Metadata } from 'next';
import Link from 'next/link';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { requirePermission } from '@/lib/auth/actor';
import { routes } from '@/lib/routes';
import { requestTypeList } from '@/lib/domain/admin-catalog';
import { RequestTypeTable } from './request-type-table';

export const metadata: Metadata = { title: 'Request Types' };

export const dynamic = 'force-dynamic';

/**
 * The public request catalog: what a citizen can file, which office handles it,
 * and what they have to bring.
 *
 * Port of `Admin\RequestTypeController@index`. The handling office is the one
 * column that is a routing rule rather than a label — a type with no office
 * reaches no supervisor, which is why the command center has a "Reached no
 * office" alert at all.
 */
export default async function RequestTypesPage({
  searchParams,
}: {
  searchParams: Promise<{ saved?: string }>;
}) {
  await requirePermission('manage system');

  const { saved } = await searchParams;
  const types = await requestTypeList();

  const flash =
    saved === 'created'
      ? 'Request type created.'
      : saved === 'updated'
        ? 'Request type updated.'
        : null;

  return (
    <AuthenticatedPage
      title="Request Types"
      pageActions={
        <Link href={routes.adminRequestTypesCreate()} className="cr-btn cr-btn-primary cr-btn-sm">
          Add request type
        </Link>
      }
    >
      <p className="text-[length:var(--fs-small)] text-ink-soft">
        Public request types and the supporting requirements citizens must present for each.
      </p>

      <RequestTypeTable types={types} flash={flash} />
    </AuthenticatedPage>
  );
}
