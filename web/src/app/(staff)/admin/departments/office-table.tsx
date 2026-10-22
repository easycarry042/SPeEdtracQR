'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState, useTransition } from 'react';
import type { DepartmentRow } from '@/lib/domain/admin-departments';
import { fromHours } from '@/lib/domain/duration';
import { routes } from '@/lib/routes';
import { toggleDepartmentActive } from '@/app/actions/admin';

/**
 * The office table, with Activate / Deactivate in the row it belongs to.
 *
 * The toggle is the only write on this page — renaming an office opens its own
 * form — so the refusal is shown above the table rather than in a dialog.
 */
export function OfficeTable({
  offices,
  flash,
}: {
  offices: DepartmentRow[];
  flash: string | null;
}) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [notice, setNotice] = useState<{ ok: boolean; message: string } | null>(
    flash ? { ok: true, message: flash } : null,
  );

  const toggle = (office: DepartmentRow): void => {
    startTransition(async () => {
      const result = await toggleDepartmentActive(office.id);
      setNotice(result);

      if (result.ok) {
        router.refresh();
      }
    });
  };

  return (
    <>
      {notice ? (
        <p className={notice.ok ? 'callout p-green mt-4' : 'callout p-amber mt-4'} role="status">
          {notice.message}
        </p>
      ) : null}

      <section className="panel mt-4">
        <div className="table-wrap">
          <table className="reg">
            <thead>
              <tr>
                <th scope="col">Department</th>
                <th scope="col">Code</th>
                <th scope="col">Supervisor</th>
                <th scope="col">Members</th>
                <th scope="col">Citizen services</th>
                <th scope="col">Turnaround</th>
                <th scope="col">Status</th>
                <th scope="col" />
              </tr>
            </thead>
            <tbody>
              {offices.length === 0 ? (
                <tr>
                  <td colSpan={8} className="py-10 text-center text-[length:var(--fs-small)] text-ink-soft">
                    No departments yet. Add the first one above.
                  </td>
                </tr>
              ) : (
                offices.map((office) => (
                  <tr key={office.id}>
                    <td className="nm text-ink">{office.name}</td>
                    <td>
                      <span className="id-chip">{office.code}</span>
                    </td>
                    <td>
                      {office.supervisors.length === 0 ? (
                        /* Requests routed here bell no one but the super admins. */
                        <span className="pill p-amber">No supervisor</span>
                      ) : (
                        office.supervisors.map((name) => (
                          <span key={name} className="block text-[length:var(--fs-small)] text-ink">
                            {name}
                          </span>
                        ))
                      )}
                    </td>
                    <td className="muted">{office.users}</td>
                    <td className="muted">
                      {office.services === 0 ? 'None (internal only)' : office.services}
                    </td>
                    <td className="muted">{fromHours(office.internalStepHours)}</td>
                    <td>
                      <span className={office.isActive ? 'pill p-green' : 'pill p-muted'}>
                        {office.isActive ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td>
                      <div className="flex items-center justify-end gap-2">
                        <Link
                          href={routes.adminDepartmentsEdit(office.id)}
                          className="cr-btn cr-btn-sm"
                        >
                          Edit
                        </Link>
                        <button
                          type="button"
                          className="cr-btn cr-btn-sm"
                          disabled={pending}
                          onClick={() => toggle(office)}
                        >
                          {office.isActive ? 'Deactivate' : 'Activate'}
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </section>
    </>
  );
}
