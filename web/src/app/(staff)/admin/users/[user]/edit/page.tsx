import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { Breadcrumbs } from '@/components/shell/breadcrumbs';
import { requirePermission } from '@/lib/auth/actor';
import { routes } from '@/lib/routes';
import { assignableRoles, userById } from '@/lib/domain/admin-users';
import { activeDepartments } from '@/lib/domain/internal-request-form';
import { UserForm } from '../../user-form';

export const metadata: Metadata = { title: 'Edit user' };

/** Port of `Admin\UserController@edit`. */
export default async function EditUserPage({ params }: { params: Promise<{ user: string }> }) {
  await requirePermission('manage users');

  const { user: param } = await params;

  const [user, roles, offices] = await Promise.all([
    userById(Number(param)),
    assignableRoles(),
    activeDepartments(),
  ]);

  if (!user) {
    notFound();
  }

  return (
    <AuthenticatedPage
      title={`Edit ${user.name}`}
      header={
        <Breadcrumbs
          items={[
            { label: 'Users', url: routes.adminUsersIndex() },
            { label: `Edit ${user.name}` },
          ]}
        />
      }
    >
      <UserForm user={user} roles={roles} offices={offices} />
    </AuthenticatedPage>
  );
}
