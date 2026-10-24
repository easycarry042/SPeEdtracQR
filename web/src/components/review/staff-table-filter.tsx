'use client';

import { useEffect, useRef, useState } from 'react';

export type StaffFilterState = {
  type: string;
  from: string;
  to: string;
};

export const EMPTY_STAFF_FILTER: StaffFilterState = {
  type: '',
  from: '',
  to: '',
};

export function staffFilterCount(filter: StaffFilterState): number {
  return (filter.type ? 1 : 0) + (filter.from || filter.to ? 1 : 0);
}

export function passesStaffFilter(
  filter: StaffFilterState,
  type: string | null | undefined,
  day: string | null | undefined,
): boolean {
  if (filter.type && type !== filter.type) {
    return false;
  }
  if (filter.from && (!day || day < filter.from)) {
    return false;
  }
  if (filter.to && (!day || day > filter.to)) {
    return false;
  }
  return true;
}

function isoDate(date: Date): string {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(
    date.getDate(),
  ).padStart(2, '0')}`;
}

/**
 * Port of `partials/staff-table-filter.blade.php`.
 * Filter button + popover for `.st-card` tables: narrows rows to one request
 * type/office and/or a submitted-date range.
 */
export function StaffTableFilter({
  id,
  value,
  onChange,
  typeOptions,
  typeLabel = 'Request type',
  allTypesLabel = 'All request types',
}: {
  id: string;
  value: StaffFilterState;
  onChange: (next: StaffFilterState) => void;
  typeOptions: string[];
  typeLabel?: string;
  allTypesLabel?: string;
}) {
  const [open, setOpen] = useState(false);
  const rootRef = useRef<HTMLDivElement>(null);
  const count = staffFilterCount(value);

  useEffect(() => {
    if (!open) return;

    const onDown = (event: MouseEvent) => {
      if (rootRef.current && !rootRef.current.contains(event.target as Node)) {
        setOpen(false);
      }
    };
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setOpen(false);
      }
    };

    document.addEventListener('mousedown', onDown);
    window.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onDown);
      window.removeEventListener('keydown', onKey);
    };
  }, [open]);

  const presetDays = (daysBack: number) => {
    const today = new Date();
    const from = new Date(today.getFullYear(), today.getMonth(), today.getDate() - daysBack);
    onChange({ ...value, from: isoDate(from), to: isoDate(today) });
  };

  const presetThisMonth = () => {
    const today = new Date();
    const from = new Date(today.getFullYear(), today.getMonth(), 1);
    onChange({ ...value, from: isoDate(from), to: isoDate(today) });
  };

  const sortedOptions = [...new Set(typeOptions.filter(Boolean))].sort((a, b) =>
    a.localeCompare(b),
  );

  return (
    <div className="relative" ref={rootRef}>
      <button
        type="button"
        onClick={() => setOpen((prev) => !prev)}
        className={`st-tool-btn${count > 0 ? ' is-active' : ''}`}
        title="Filter"
        aria-label="Filter requests"
        aria-haspopup="dialog"
        aria-expanded={open}
      >
        <img src="/images/staff/icon-filter.svg" alt="" />
        {count > 0 ? <span className="st-filter-badge">{count}</span> : null}
      </button>

      {open ? (
        <div className="st-filter-pop" role="dialog" aria-label="Filter requests">
          <p className="st-filter-title">Filter requests</p>

          <label className="st-filter-label" htmlFor={`${id}-type`}>
            {typeLabel}
          </label>
          <select
            id={`${id}-type`}
            value={value.type}
            onChange={(e) => onChange({ ...value, type: e.target.value })}
            className="st-filter-field"
          >
            <option value="">{allTypesLabel}</option>
            {sortedOptions.map((opt) => (
              <option key={opt} value={opt}>
                {opt}
              </option>
            ))}
          </select>

          <p className="st-filter-label mt-4">Date submitted</p>
          <div className="grid grid-cols-2 gap-2">
            <div>
              <label className="st-filter-sub" htmlFor={`${id}-from`}>
                From
              </label>
              <input
                id={`${id}-from`}
                type="date"
                value={value.from}
                max={value.to || undefined}
                onChange={(e) => onChange({ ...value, from: e.target.value })}
                className="st-filter-field"
              />
            </div>
            <div>
              <label className="st-filter-sub" htmlFor={`${id}-to`}>
                To
              </label>
              <input
                id={`${id}-to`}
                type="date"
                value={value.to}
                min={value.from || undefined}
                onChange={(e) => onChange({ ...value, to: e.target.value })}
                className="st-filter-field"
              />
            </div>
          </div>

          <div className="mt-2 flex flex-wrap gap-1.5">
            <button type="button" className="st-filter-chip" onClick={() => presetDays(0)}>
              Today
            </button>
            <button type="button" className="st-filter-chip" onClick={() => presetDays(6)}>
              Last 7 days
            </button>
            <button type="button" className="st-filter-chip" onClick={() => presetDays(29)}>
              Last 30 days
            </button>
            <button type="button" className="st-filter-chip" onClick={presetThisMonth}>
              This month
            </button>
          </div>

          <div className="mt-4 flex items-center justify-between border-t border-hairline pt-3">
            <button
              type="button"
              onClick={() => onChange(EMPTY_STAFF_FILTER)}
              className="text-[length:var(--fs-small)] font-semibold text-ink-soft hover:text-green-deep"
            >
              Reset
            </button>
            <button
              type="button"
              onClick={() => setOpen(false)}
              className="cr-btn cr-btn-primary cr-btn-sm"
            >
              Done
            </button>
          </div>
        </div>
      ) : null}
    </div>
  );
}
