import type { Metadata } from 'next';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { Breadcrumbs } from '@/components/shell/breadcrumbs';
import { requirePermission } from '@/lib/auth/actor';
import { routes } from '@/lib/routes';
import { DepartmentForm } from '../department-form';

export const metadata: Metadata = { title: 'Add department' };

/** Port of `Admin\DepartmentController@create`. */
export default async function CreateDepartmentPage() {
  await requirePermission('manage system');

  return (
    <AuthenticatedPage
      title="Add department"
      header={
        <Breadcrumbs
          items={[
            { label: 'Departments', url: routes.adminDepartmentsIndex() },
            { label: 'Add department' },
          ]}
        />
      }
    >
      <DepartmentForm department={null} />
    </AuthenticatedPage>
  );
}
