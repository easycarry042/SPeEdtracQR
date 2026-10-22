import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { requireActor } from '@/lib/auth/actor';
import { routes } from '@/lib/routes';
import { activeDepartments, canFile } from '@/lib/domain/internal-request-form';
import { FilingForm } from './form';

export const metadata: Metadata = { title: 'File an internal request' };

/**
 * The filing wizard's own page (it also opens in a modal from the header).
 *
 * Port of `InternalRequestController@create`. An internal request is filed on
 * behalf of an office, so an account with no active department is sent back to
 * the dashboard rather than shown a form it cannot submit.
 */
export default async function FileInternalRequestPage() {
  const actor = await requireActor();

  if (!canFile(actor)) {
    redirect(routes.requestsIndex());
  }

  const departments = await activeDepartments();

  return (
    <AuthenticatedPage title="File an internal request">
      <FilingForm
        departments={departments}
        officeName={actor.departmentName ?? 'your office'}
      />
    </AuthenticatedPage>
  );
}
