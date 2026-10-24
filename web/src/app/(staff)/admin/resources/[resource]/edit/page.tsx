import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { Breadcrumbs } from '@/components/shell/breadcrumbs';
import { requirePermission } from '@/lib/auth/actor';
import { routes } from '@/lib/routes';
import { resourceById } from '@/lib/domain/admin-catalog';
import { ResourceForm } from '../../resource-form';

export const metadata: Metadata = { title: 'Edit item' };

/** Port of `Admin\ResourceController@edit`. */
export default async function EditResourcePage({
  params,
}: {
  params: Promise<{ resource: string }>;
}) {
  await requirePermission('manage system');

  const { resource: param } = await params;
  const resource = await resourceById(Number(param));

  if (!resource) {
    notFound();
  }

  return (
    <AuthenticatedPage
      title={`Edit ${resource.name}`}
      header={
        <Breadcrumbs
          items={[
            { label: 'Resources', url: routes.inventoryIndex() },
            { label: `Edit ${resource.name}` },
          ]}
        />
      }
    >
      <ResourceForm resource={resource} />
    </AuthenticatedPage>
  );
}
