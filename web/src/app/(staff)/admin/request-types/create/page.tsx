import type { Metadata } from 'next';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { Breadcrumbs } from '@/components/shell/breadcrumbs';
import { requirePermission } from '@/lib/auth/actor';
import { routes } from '@/lib/routes';
import { resourceOptions, staffOptions } from '@/lib/domain/admin-catalog';
import { activeDepartments } from '@/lib/domain/internal-request-form';
import { RequestTypeForm } from '../request-type-form';

export const metadata: Metadata = { title: 'Add request type' };

/** Port of `Admin\RequestTypeController@create`. */
export default async function CreateRequestTypePage() {
  await requirePermission('manage system');

  const [offices, resources, staff] = await Promise.all([activeDepartments(), resourceOptions(), staffOptions()]);

  return (
    <AuthenticatedPage
      title="Add request type"
      header={
        <Breadcrumbs
          items={[
            { label: 'Request Types', url: routes.adminRequestTypesIndex() },
            { label: 'Add request type' },
          ]}
        />
      }
    >
      <RequestTypeForm requestType={null} offices={offices} resources={resources} staff={staff} />
    </AuthenticatedPage>
  );
}
