'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState, useTransition } from 'react';
import type { RequestTypeRow } from '@/lib/domain/admin-catalog';
import { routes } from '@/lib/routes';
import { removeRequestType, toggleRequestTypeActive } from '@/app/actions/admin';
import { useConfirm } from '@/components/confirm-dialog';

/**
 * The catalog table.
 *
 * Delete asks in the row and says what survives it: requests already submitted
 * keep their own snapshot of each requirement, so deleting a type edits the
 * menu rather than rewriting history.
 */
export function RequestTypeTable({
  types,
  flash,
}: {
  types: RequestTypeRow[];
  flash: string | null;
}) {
  const router = useRouter();
  const confirm = useConfirm();
  const [pending, startTransition] = useTransition();
  const [confirming, setConfirming] = useState<number | null>(null);
  const [notice, setNotice] = useState<{ ok: boolean; message: string } | null>(
    flash ? { ok: true, message: flash } : null,
  );

  const run = (action: () => Promise<{ ok: boolean; message: string }>): void => {
    setConfirming(null);

    startTransition(async () => {
      const result = await action();
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
                <th scope="col">Request type</th>
                <th scope="col">Handling office</th>
                <th scope="col">Kind</th>
                <th scope="col">Requirements</th>
                <th scope="col">Status</th>
                <th scope="col" />
              </tr>
            </thead>
            <tbody>
              {types.length === 0 ? (
                <tr>
                  <td colSpan={6} className="py-10 text-center text-[length:var(--fs-small)] text-ink-soft">
                    No request types yet. Add the first one above — until then the public request
                    form has nothing to offer.
                  </td>
                </tr>
              ) : (
                types.map((type) => (
                  <tr key={type.id}>
                    <td>
                      <p className="nm text-ink">{type.name}</p>
                      {type.description ? (
                        <p className="dir-sub max-w-md truncate">{type.description}</p>
                      ) : null}
                      {type.resource ? <span className="chip">{type.resource}</span> : null}
                    </td>

                    <td>
                      {type.office ? (
                        <span className="text-[length:var(--fs-small)] text-ink">{type.office}</span>
                      ) : (
                        /* The gap the command center's unrouted alert reports. */
                        <span className="pill p-amber">No office — requests go unrouted</span>
                      )}
                      {type.handlers.length > 0 ? (
                        <p className="text-[length:var(--fs-caption)] text-ink-soft">
                          Usually {type.handlers.join(' or ')}
                        </p>
                      ) : null}
                    </td>

                    <td>
                      <span className={type.kind === 'booking' ? 'pill p-amber' : 'pill p-muted'}>
                        {type.kind.charAt(0).toUpperCase() + type.kind.slice(1)}
                      </span>
                    </td>

                    <td className="muted">{type.requirements}</td>

                    <td>
                      <span className={type.isActive ? 'pill p-green' : 'pill p-muted'}>
                        {type.isActive ? 'Active' : 'Inactive'}
                      </span>
                    </td>

                    <td>
                      {confirming === type.id ? (
                        <div className="flex flex-wrap items-center justify-end gap-2">
                          <span className="text-[length:var(--fs-caption)] text-ink-soft">
                            Delete {type.name}? Requests already filed keep their requirements.
                          </span>
                          <button
                            type="button"
                            className="cr-btn cr-btn-sm cr-btn-danger"
                            disabled={pending}
                            onClick={() => run(() => removeRequestType(type.id))}
                          >
                            Delete
                          </button>
                          <button
                            type="button"
                            className="cr-btn cr-btn-sm"
                            onClick={() => setConfirming(null)}
                          >
                            Keep
                          </button>
                        </div>
                      ) : (
                        <div className="flex items-center justify-end gap-2">
                          <Link
                            href={routes.adminRequestTypesEdit(type.id)}
                            className="cr-btn cr-btn-sm"
                          >
                            Edit
                          </Link>
                          <button
                            type="button"
                            className="cr-btn cr-btn-sm"
                            disabled={pending}
                            onClick={async () => {
                              const confirmed = await confirm(
                                type.isActive
                                  ? {
                                      title: `Deactivate ${type.name}?`,
                                      message: 'Citizens can no longer file it. Requests already filed carry on.',
                                      confirmLabel: 'Deactivate',
                                      tone: 'danger',
                                    }
                                  : { title: `Activate ${type.name}?`, confirmLabel: 'Activate' },
                              );

                              if (confirmed) {
                                run(() => toggleRequestTypeActive(type.id));
                              }
                            }}
                          >
                            {type.isActive ? 'Deactivate' : 'Activate'}
                          </button>
                          <button
                            type="button"
                            className="cr-btn cr-btn-sm"
                            disabled={pending}
                            onClick={() => setConfirming(type.id)}
                          >
                            Delete
                          </button>
                        </div>
                      )}
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
