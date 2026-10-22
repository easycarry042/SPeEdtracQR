import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { Breadcrumbs } from '@/components/shell/breadcrumbs';
import { requirePermission } from '@/lib/auth/actor';
import { routes } from '@/lib/routes';
import { routeTemplateById } from '@/lib/domain/admin-catalog';
import { activeDepartments } from '@/lib/domain/internal-request-form';
import { TemplateForm } from '../../template-form';

export const metadata: Metadata = { title: 'Edit route template' };

/** Port of `Admin\RouteTemplateController@edit`. */
export default async function EditRouteTemplatePage({
  params,
}: {
  params: Promise<{ routeTemplate: string }>;
}) {
  await requirePermission('manage system');

  const { routeTemplate: param } = await params;

  const [template, offices] = await Promise.all([
    routeTemplateById(Number(param)),
    activeDepartments(),
  ]);

  if (!template) {
    notFound();
  }

  return (
    <AuthenticatedPage
      title={`Edit ${template.name}`}
      header={
        <Breadcrumbs
          items={[
            { label: 'Route Templates', url: routes.adminRouteTemplatesIndex() },
            { label: `Edit ${template.name}` },
          ]}
        />
      }
    >
      <TemplateForm template={template} offices={offices} />
    </AuthenticatedPage>
  );
}
