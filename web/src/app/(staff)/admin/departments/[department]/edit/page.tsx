import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { Breadcrumbs } from '@/components/shell/breadcrumbs';
import { requirePermission } from '@/lib/auth/actor';
import { routes } from '@/lib/routes';
import { departmentById } from '@/lib/domain/admin-departments';
import { DepartmentForm } from '../../department-form';

export const metadata: Metadata = { title: 'Edit department' };

/** Port of `Admin\DepartmentController@edit`. */
export default async function EditDepartmentPage({
  params,
}: {
  params: Promise<{ department: string }>;
}) {
  await requirePermission('manage system');

  const { department: param } = await params;
  const department = await departmentById(Number(param));

  if (!department) {
    notFound();
  }

  return (
    <AuthenticatedPage
      title={`Edit ${department.name}`}
      header={
        <Breadcrumbs
          items={[
            { label: 'Departments', url: routes.adminDepartmentsIndex() },
            { label: `Edit ${department.name}` },
          ]}
        />
      }
    >
      <DepartmentForm department={department} />
    </AuthenticatedPage>
  );
}
