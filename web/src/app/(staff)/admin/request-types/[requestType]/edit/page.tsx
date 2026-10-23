import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { Breadcrumbs } from '@/components/shell/breadcrumbs';
import { requirePermission } from '@/lib/auth/actor';
import { routes } from '@/lib/routes';
import { requestTypeById, resourceOptions } from '@/lib/domain/admin-catalog';
import { activeDepartments } from '@/lib/domain/internal-request-form';
import { RequestTypeForm } from '../../request-type-form';

export const metadata: Metadata = { title: 'Edit request type' };

/** Port of `Admin\RequestTypeController@edit`. */
export default async function EditRequestTypePage({
  params,
}: {
  params: Promise<{ requestType: string }>;
}) {
  await requirePermission('manage system');

  const { requestType: param } = await params;

  const [requestType, offices, resources] = await Promise.all([
    requestTypeById(Number(param)),
    activeDepartments(),
    resourceOptions(),
  ]);

  if (!requestType) {
    notFound();
  }

  return (
    <AuthenticatedPage
      title={`Edit ${requestType.name}`}
      header={
        <Breadcrumbs
          items={[
            { label: 'Request Types', url: routes.adminRequestTypesIndex() },
            { label: `Edit ${requestType.name}` },
          ]}
        />
      }
    >
      <RequestTypeForm requestType={requestType} offices={offices} resources={resources} />
    </AuthenticatedPage>
  );
}
