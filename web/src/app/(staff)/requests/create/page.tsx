import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { requireActor } from '@/lib/auth/actor';
import { db, id } from '@/lib/db';
import { routes } from '@/lib/routes';
import { activeDepartments, canFile, mandatoryRouteOffices } from '@/lib/domain/internal-request-form';
import { isDestination } from '@/lib/domain/internal-route-plan';
import { FilingForm } from './form';

export const metadata: Metadata = { title: 'File Internal Request' };

/**
 * Port of `InternalRequestController@create` (`requests/create.blade.php`).
 */
export default async function FileInternalRequestPage() {
  const actor = await requireActor();

  if (!canFile(actor)) {
    redirect(routes.requestsIndex());
  }

  const [departments, mandatory, office] = await Promise.all([
    activeDepartments(),
    mandatoryRouteOffices(),
    actor.departmentId
      ? db.departments.findFirst({
          where: { id: id(actor.departmentId) },
          select: { name: true, code: true },
        })
      : Promise.resolve(null),
  ]);

  return (
    <AuthenticatedPage title="File Internal Request">
      <div className="page-shell">
        <div className="dm-card mx-auto w-full max-w-[940px]">
          <FilingForm
            departments={departments.filter(isDestination)}
            mandatory={mandatory}
            officeId={actor.departmentId}
            officeName={office?.name ?? actor.departmentName ?? 'your office'}
            officeCode={office?.code ?? null}
          />
        </div>
      </div>
    </AuthenticatedPage>
  );
}
