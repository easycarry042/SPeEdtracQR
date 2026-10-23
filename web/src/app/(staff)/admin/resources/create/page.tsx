import type { Metadata } from 'next';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { Breadcrumbs } from '@/components/shell/breadcrumbs';
import { requirePermission } from '@/lib/auth/actor';
import { routes } from '@/lib/routes';
import { ResourceForm } from '../resource-form';

export const metadata: Metadata = { title: 'Add item' };

/** Port of `Admin\ResourceController@create`. */
export default async function CreateResourcePage() {
  await requirePermission('manage system');

  return (
    <AuthenticatedPage
      title="Add item"
      header={
        <Breadcrumbs
          items={[{ label: 'Resources', url: routes.inventoryIndex() }, { label: 'Add item' }]}
        />
      }
    >
      <ResourceForm resource={null} />
    </AuthenticatedPage>
  );
}
