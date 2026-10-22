'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState, useTransition } from 'react';
import { DEFAULT_INTERNAL_STEP_HOURS } from '@/lib/domain/department';
import { routes } from '@/lib/routes';
import { saveDepartment } from '@/app/actions/admin';

/**
 * Add / edit an office — one form for both, as the Blade partial was.
 *
 * The code is typed and stored upper-case because it is read as an identifier
 * wherever it appears (office chips, the endorsement chain, the printed slip),
 * and `acc` beside `ACC` reads as two different offices.
 */
export function DepartmentForm({
  department,
}: {
  department: { id: number; name: string; code: string; internalStepHours: number } | null;
}) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [name, setName] = useState(department?.name ?? '');
  const [code, setCode] = useState(department?.code ?? '');
  const [hours, setHours] = useState(
    String(department?.internalStepHours ?? DEFAULT_INTERNAL_STEP_HOURS),
  );
  const [error, setError] = useState<string | null>(null);

  const submit = (event: React.FormEvent): void => {
    event.preventDefault();

    startTransition(async () => {
      const result = await saveDepartment(department?.id ?? null, {
        name,
        code,
        internalStepHours: hours.trim() === '' ? null : Number(hours),
      });

      if (!result.ok) {
        setError(result.message);

        return;
      }

      router.push(
        routes.adminDepartmentsIndex({ saved: department === null ? 'created' : 'updated' }),
      );
    });
  };

  return (
    <section className="panel">
      <div className="ph">
        <h2>{department === null ? 'New department' : 'Department details'}</h2>
      </div>

      <form onSubmit={submit} className="pb space-y-5 p-6">
        {error ? (
          <p className="callout p-amber" role="alert">
            {error}
          </p>
        ) : null}

        <div>
          <label className="inv-field-label" htmlFor="deptName">
            Department name <span className="text-status-red">*</span>
          </label>
          <input
            id="deptName"
            type="text"
            className="field w-full"
            value={name}
            onChange={(event) => setName(event.target.value)}
            maxLength={255}
            required
            autoFocus
            placeholder="e.g. Municipal Budget Office"
          />
        </div>

        <div className="sm:w-1/2">
          <label className="inv-field-label" htmlFor="deptCode">
            Short code <span className="text-status-red">*</span>
          </label>
          <input
            id="deptCode"
            type="text"
            className="field w-full font-mono uppercase"
            value={code}
            onChange={(event) => setCode(event.target.value.toUpperCase())}
            maxLength={10}
            required
            placeholder="e.g. BO"
          />
          <p className="mt-1 text-[length:var(--fs-caption)] text-ink-soft">
            Letters and numbers only, up to 10 characters. Shown on badges and routing chains.
          </p>
        </div>

        <div className="sm:w-1/2">
          <label className="inv-field-label" htmlFor="deptHours">
            Internal request turnaround (hours)
          </label>
          <input
            id="deptHours"
            type="number"
            className="field w-full"
            value={hours}
            onChange={(event) => setHours(event.target.value)}
            min={1}
            max={720}
            step={1}
          />
          <p className="mt-1 text-[length:var(--fs-caption)] text-ink-soft">
            How long this office has to act on an internal request once it reaches them. Past this,
            the command center counts it overdue. {DEFAULT_INTERNAL_STEP_HOURS} hours is three days.
          </p>
        </div>

        <div className="flex items-center justify-end gap-3 border-t border-hairline pt-4">
          <Link href={routes.adminDepartmentsIndex()} className="cr-btn">
            Cancel
          </Link>
          <button type="submit" className="cr-btn cr-btn-primary" disabled={pending}>
            {department === null ? 'Create department' : 'Save changes'}
          </button>
        </div>
      </form>
    </section>
  );
}
