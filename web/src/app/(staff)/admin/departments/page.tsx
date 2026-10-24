import type { Metadata } from 'next';
import Link from 'next/link';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { requirePermission } from '@/lib/auth/actor';
import { routes } from '@/lib/routes';
import { departmentList } from '@/lib/domain/admin-departments';
import { OfficeTable } from './office-table';

export const metadata: Metadata = { title: 'Departments' };

/** The office list and its supervisor column are both live tables. */
export const dynamic = 'force-dynamic';

/**
 * The office directory.
 *
 * Port of `Admin\DepartmentController@index`. The sidebar has linked this page
 * since the Setup group was added, so until now the row 404'd.
 *
 * Two columns here are quiet alarms rather than data: an office with no
 * supervisor notifies nobody when a request reaches it, and an office with no
 * citizen service cannot be routed a request from the public form at all.
 */
export default async function DepartmentsPage({
  searchParams,
}: {
  searchParams: Promise<{ saved?: string }>;
}) {
  await requirePermission('manage system');

  const { saved } = await searchParams;
  const offices = await departmentList();

  // Flashed across the redirect from the create / edit page. Read as a known
  // key rather than printed from the URL, so the banner cannot be made to say
  // something the system never did.
  const flash =
    saved === 'created' ? 'Department created.' : saved === 'updated' ? 'Department updated.' : null;

  return (
    <AuthenticatedPage
      title="Departments"
      pageActions={
        <Link href={routes.adminDepartmentsCreate()} className="cr-btn cr-btn-primary cr-btn-sm">
          Add department
        </Link>
      }
    >
      <p className="text-[length:var(--fs-small)] text-ink-soft">
        Every office: who supervises it, which citizen services it handles, and how long it has to
        act on an internal request.
      </p>

      <OfficeTable offices={offices} flash={flash} />
    </AuthenticatedPage>
  );
}
