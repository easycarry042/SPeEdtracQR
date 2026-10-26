import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { CommandCenter } from '@/components/admin/command-center';
import { requirePermission } from '@/lib/auth/actor';
import { db, id } from '@/lib/db';
import { data } from '@/lib/domain/command-center';
import { REVIEW_SELECT, assignableStaff, citizenFacing, collection } from '@/lib/domain/request-review';
import { documentScope } from '@/lib/domain/scope';
import { ReportsRequestModal } from './request-modal';

export const metadata: Metadata = { title: 'Reports — SPeED TraQR' };

export const dynamic = 'force-dynamic';

/**
 * A supervisor's Reports: the same panels as the command center, scoped to
 * their own office.
 *
 * Port of `ReportsController@index`. It replaced a separate Analytics page and
 * Services report whose figures disagreed with the supervisor's own dashboard —
 * they counted every office's internal requests as theirs. Staff have no
 * `view reports` permission, so they never reach this.
 *
 * `?open=SPD-…` opens that request in the Dashboard's Review modal over the
 * report — only a citizen request this supervisor may see.
 */
export default async function ReportsPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  const actor = await requirePermission('view reports');

  // A super admin asking for Reports wants the city, which is the command
  // center — this page can only ever show one office.
  if (actor.roles.includes('super_admin')) {
    redirect('/admin/dashboard');
  }

  const office = actor.departmentId
    ? await db.departments.findFirst({
        where: { id: id(actor.departmentId) },
        select: { id: true, name: true, code: true },
      })
    : null;

  const params = new URLSearchParams();

  for (const [key, value] of Object.entries(await searchParams)) {
    if (typeof value === 'string') {
      params.set(key, value);
    }
  }

  const openTracking = params.get('open')?.trim().toUpperCase() || null;
  params.delete('open');

  const openDocument = openTracking
    ? await db.documents.findFirst({
        where: {
          AND: [documentScope(actor), citizenFacing],
          tracking_number: openTracking,
          deleted_at: null,
        },
        select: REVIEW_SELECT,
      })
    : null;

  const [openRow] = openDocument ? await collection([openDocument], actor) : [];
  const staff = openRow && actor.departmentId ? await assignableStaff(actor.departmentId) : [];
  const closeHref = params.size > 0 ? `/reports?${params.toString()}` : '/reports';

  const payload = await data(
    params,
    office ? { id: Number(office.id), name: office.name, code: office.code } : null,
  );

  return (
    <AuthenticatedPage title="Reports">
      <CommandCenter data={payload} />
      {openRow ? (
        <ReportsRequestModal
          row={openRow}
          assignableStaff={staff.map((member) => ({
            id: member.id,
            name: member.name,
            is_available: member.is_available,
          }))}
          closeHref={closeHref}
        />
      ) : null}
    </AuthenticatedPage>
  );
}
