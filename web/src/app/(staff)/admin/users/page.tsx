import type { Metadata } from 'next';
import Link from 'next/link';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { requirePermission } from '@/lib/auth/actor';
import { routes } from '@/lib/routes';
import { userList } from '@/lib/domain/admin-users';
import { UserDirectory } from './user-directory';

export const metadata: Metadata = { title: 'Users' };

/** Workload and "Active now" are both clock-dependent. */
export const dynamic = 'force-dynamic';

/**
 * Accounts: who can sign in, as what, for which office — and what they are
 * carrying, because the Users page absorbed the staff directory's workload
 * column rather than keeping a second list of the same people.
 *
 * Port of `Admin\UserController@index`. The filters and the page live in the
 * URL, as they did in Blade: the list is paged on the server, so a filter that
 * only ran in the browser would filter one page of twenty and silently hide
 * the rest.
 */
export default async function UsersPage({
  searchParams,
}: {
  searchParams: Promise<{ search?: string; role?: string; page?: string; saved?: string }>;
}) {
  const actor = await requirePermission('manage users');

  const { search, role, page, saved } = await searchParams;

  const listing = await userList({
    search: search ?? null,
    role: role ?? null,
    page: page ? Number(page) : 1,
  });

  const flash = saved === 'created' ? 'Account created.' : saved === 'updated' ? 'Account updated.' : null;

  return (
    <AuthenticatedPage
      title="Users"
      pageActions={
        <Link href={routes.adminUsersCreate()} className="cr-btn cr-btn-primary cr-btn-sm">
          Add user
        </Link>
      }
    >
      <UserDirectory
        listing={listing}
        search={search ?? ''}
        role={role ?? ''}
        viewerId={actor.id}
        flash={flash}
      />
    </AuthenticatedPage>
  );
}
