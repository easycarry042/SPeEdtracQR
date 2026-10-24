'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState, useTransition } from 'react';
import { routes } from '@/lib/routes';
import { removeRouteTemplate, toggleRouteTemplateActive } from '@/app/actions/admin';

type TemplateRow = {
  id: number;
  name: string;
  description: string | null;
  isActive: boolean;
  steps: number;
};

/** The template list, with the toggle and a delete that confirms in the row. */
export function TemplateTable({
  templates,
  flash,
}: {
  templates: TemplateRow[];
  flash: string | null;
}) {
  const router = useRouter();
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
                <th scope="col">Template</th>
                <th scope="col">Steps</th>
                <th scope="col">Status</th>
                <th scope="col" />
              </tr>
            </thead>
            <tbody>
              {templates.length === 0 ? (
                <tr>
                  <td colSpan={4} className="py-10 text-center text-[length:var(--fs-small)] text-ink-soft">
                    No route templates yet. Offices can still file internal requests and name each
                    office by hand; a template just saves them doing it every time.
                  </td>
                </tr>
              ) : (
                templates.map((template) => (
                  <tr key={template.id}>
                    <td>
                      <p className="nm text-ink">{template.name}</p>
                      {template.description ? (
                        <p className="dir-sub max-w-md truncate">{template.description}</p>
                      ) : null}
                    </td>
                    <td className="muted">{template.steps}</td>
                    <td>
                      <span className={template.isActive ? 'pill p-green' : 'pill p-muted'}>
                        {template.isActive ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td>
                      {confirming === template.id ? (
                        <div className="flex flex-wrap items-center justify-end gap-2">
                          <span className="text-[length:var(--fs-caption)] text-ink-soft">
                            Delete {template.name}? Requests already filed keep their chains.
                          </span>
                          <button
                            type="button"
                            className="cr-btn cr-btn-sm cr-btn-danger"
                            disabled={pending}
                            onClick={() => run(() => removeRouteTemplate(template.id))}
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
                            href={routes.adminRouteTemplatesEdit(template.id)}
                            className="cr-btn cr-btn-sm"
                          >
                            Edit
                          </Link>
                          <button
                            type="button"
                            className="cr-btn cr-btn-sm"
                            disabled={pending}
                            onClick={() => run(() => toggleRouteTemplateActive(template.id))}
                          >
                            {template.isActive ? 'Deactivate' : 'Activate'}
                          </button>
                          <button
                            type="button"
                            className="cr-btn cr-btn-sm"
                            disabled={pending}
                            onClick={() => setConfirming(template.id)}
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
