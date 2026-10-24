import type { Metadata } from 'next';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { Breadcrumbs } from '@/components/shell/breadcrumbs';
import { requirePermission } from '@/lib/auth/actor';
import { routes } from '@/lib/routes';
import { activeDepartments } from '@/lib/domain/internal-request-form';
import { TemplateForm } from '../template-form';

export const metadata: Metadata = { title: 'Add route template' };

/** Port of `Admin\RouteTemplateController@create`. */
export default async function CreateRouteTemplatePage() {
  await requirePermission('manage system');

  const offices = await activeDepartments();

  return (
    <AuthenticatedPage
      title="Add route template"
      header={
        <Breadcrumbs
          items={[
            { label: 'Route Templates', url: routes.adminRouteTemplatesIndex() },
            { label: 'Add template' },
          ]}
        />
      }
    >
      <TemplateForm template={null} offices={offices} />
    </AuthenticatedPage>
  );
}
