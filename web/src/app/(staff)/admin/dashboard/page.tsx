import type { Metadata } from 'next';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { CommandCenter } from '@/components/admin/command-center';
import { requireRole } from '@/lib/auth/actor';
import { ROLE_SUPER_ADMIN } from '@/lib/auth/rbac';
import { data } from '@/lib/domain/command-center';

export const metadata: Metadata = { title: 'Command center — SPeED TraQR' };

/** Every figure is live, so nothing here may be cached. */
export const dynamic = 'force-dynamic';

/**
 * The super admin's command center: the whole city's numbers.
 *
 * Port of `AdminController@dashboard`. The same panels serve a supervisor at
 * `/reports` with their office passed instead of null — two wrappers rather than
 * one parameterised route because the page titles must be literals.
 */
export default async function AdminDashboardPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  await requireRole(ROLE_SUPER_ADMIN);

  const params = new URLSearchParams();

  for (const [key, value] of Object.entries(await searchParams)) {
    if (typeof value === 'string') {
      params.set(key, value);
    }
  }

  // Null office = city-wide.
  const payload = await data(params, null);

  return (
    <AuthenticatedPage title="Command center">
      <CommandCenter data={payload} />
    </AuthenticatedPage>
  );
}
