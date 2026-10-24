'use client';

import { useEffect, useRef, useState } from 'react';
import { usePathname, useRouter } from 'next/navigation';
import type { HistoryFilters } from '@/lib/domain/history';

/**
 * History's search row and filter panel. Typing searches after a pause, and
 * every change rewrites the URL — so the page, its pager and the Export CSV
 * link all follow what is on screen.
 */
export function HistoryFilterBar({
  filters,
  documentTypes,
  exportUrl,
}: {
  filters: HistoryFilters;
  documentTypes: string[];
  exportUrl: string;
}) {
  const router = useRouter();
  const pathname = usePathname();
  const [values, setValues] = useState(filters);
  const [open, setOpen] = useState(Boolean(filters.document_type || filters.from || filters.to));
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const apply = (next: HistoryFilters): void => {
    const entries = Object.entries(next).filter(([, value]) => value) as Array<[string, string]>;
    const query = new URLSearchParams(entries).toString();

    router.replace(query ? `${pathname}?${query}` : pathname);
  };

  const change = (field: keyof HistoryFilters, value: string, delay = 0): void => {
    const next = { ...values, [field]: value };
    setValues(next);

    if (timer.current) {
      clearTimeout(timer.current);
    }

    timer.current = setTimeout(() => apply(next), delay);
  };

  useEffect(
    () => () => {
      if (timer.current) {
        clearTimeout(timer.current);
      }
    },
    [],
  );

  return (
    <form
      className="space-y-3"
      onSubmit={(event) => {
        event.preventDefault();
        apply(values);
      }}
    >
      <div className="flex flex-wrap items-center gap-3">
        <label htmlFor="historySearch" className="sr-only">
          Search history
        </label>
        <input
          type="text"
          id="historySearch"
          value={values.search ?? ''}
          onChange={(event) => change('search', event.target.value, 300)}
          autoComplete="off"
          placeholder="Search tracking #, citizen, or type…"
          className="h-ctl min-w-[200px] flex-1 rounded-xl border border-gray-200 bg-white px-4 text-sm shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30"
        />
        <button
          type="button"
          onClick={() => setOpen(!open)}
          aria-expanded={open}
          className={`inline-flex h-ctl items-center gap-2 rounded-xl border px-4 text-sm font-semibold shadow-sm transition hover:bg-gray-50 ${open ? 'border-emerald-300 bg-emerald-50 text-emerald-900' : 'border-gray-200 bg-white text-gray-600'}`}
        >
          <svg
            className="h-4 w-4"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"
            />
          </svg>
          Filters
        </button>
        <a
          href={exportUrl}
          className="inline-flex h-ctl items-center justify-center rounded-xl bg-emerald-800 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-900"
        >
          Export CSV
        </a>
      </div>

      {open ? (
        <div className="grid grid-cols-1 gap-3 rounded-2xl border border-gray-200/90 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
          <label className="sr-only" htmlFor="historyType">
            Category
          </label>
          <select
            id="historyType"
            value={values.document_type ?? ''}
            onChange={(event) => change('document_type', event.target.value)}
            className="h-ctl rounded-xl border border-gray-200 bg-white px-3 text-sm shadow-sm focus:border-emerald-400 focus:outline-none"
          >
            <option value="">All Categories</option>
            {documentTypes.map((type) => (
              <option key={type} value={type}>
                {type}
              </option>
            ))}
          </select>
          <input
            type="date"
            aria-label="From date"
            value={values.from ?? ''}
            onChange={(event) => change('from', event.target.value)}
            className="h-ctl rounded-xl border border-gray-200 bg-white px-3 text-sm shadow-sm focus:border-emerald-400 focus:outline-none"
          />
          <input
            type="date"
            aria-label="To date"
            value={values.to ?? ''}
            onChange={(event) => change('to', event.target.value)}
            className="h-ctl rounded-xl border border-gray-200 bg-white px-3 text-sm shadow-sm focus:border-emerald-400 focus:outline-none"
          />
          <div className="flex items-center gap-2">
            <button
              type="submit"
              className="h-ctl flex-1 rounded-xl bg-gray-800 px-4 text-sm font-semibold text-white hover:bg-gray-900"
            >
              Apply
            </button>
            <button
              type="button"
              onClick={() => {
                const cleared = { search: '', document_type: '', from: '', to: '' };
                setValues(cleared);
                apply(cleared);
              }}
              className="inline-flex h-ctl items-center justify-center rounded-xl border border-gray-200 bg-white px-4 text-sm font-semibold text-gray-600 hover:bg-gray-50"
            >
              Clear
            </button>
          </div>
        </div>
      ) : null}
    </form>
  );
}
