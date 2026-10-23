import type { Metadata } from 'next';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { Breadcrumbs } from '@/components/shell/breadcrumbs';
import { requirePermission } from '@/lib/auth/actor';
import { routes } from '@/lib/routes';
import { assignableRoles } from '@/lib/domain/admin-users';
import { activeDepartments } from '@/lib/domain/internal-request-form';
import { UserForm } from '../user-form';

export const metadata: Metadata = { title: 'Add user' };

/** Port of `Admin\UserController@create`. */
export default async function CreateUserPage() {
  await requirePermission('manage users');

  const [roles, offices] = await Promise.all([assignableRoles(), activeDepartments()]);

  return (
    <AuthenticatedPage
      title="Add user"
      header={
        <Breadcrumbs
          items={[{ label: 'Users', url: routes.adminUsersIndex() }, { label: 'Add user' }]}
        />
      }
    >
      <UserForm user={null} roles={roles} offices={offices} />
    </AuthenticatedPage>
  );
}
