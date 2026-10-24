'use client';

import { pageList } from './page-list';

/**
 * Port of `partials/staff-table-pager.blade.php`.
 * Always shown, pinned under the rows inside `.st-card`, so the card keeps one
 * fixed shape whatever the row count.
 */
export function Pager({
  page,
  lastPage,
  perPage,
  total,
  onPage,
  disabled = false,
  label = 'Request pages',
  noun = 'requests',
}: {
  page: number;
  lastPage: number;
  perPage: number;
  total: number;
  onPage: (page: number) => void;
  disabled?: boolean;
  label?: string;
  noun?: string;
}) {
  const safeLast = Math.max(1, lastPage);
  const safePage = Math.min(Math.max(1, page), safeLast);

  return (
    <nav className="st-pager" aria-label={label}>
      <p className="st-pager-info" aria-live="polite">
        {total === 0
          ? `No ${noun}`
          : `Showing ${(safePage - 1) * perPage + 1}–${Math.min(safePage * perPage, total)} of ${total}`}
      </p>

      <button
        type="button"
        className="st-page-step"
        disabled={disabled || safePage <= 1}
        onClick={() => onPage(safePage - 1)}
        aria-label="Previous page"
      >
        <svg
          className="h-4 w-4"
          fill="none"
          stroke="currentColor"
          strokeWidth="2.5"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
      </button>

      {pageList(safePage, safeLast).map((entry, index) =>
        entry === 'gap' ? (
          <span key={`gap-${index}`} className="px-1 text-ink-soft" aria-hidden="true">
            …
          </span>
        ) : (
          <button
            key={entry}
            type="button"
            className={`st-page${entry === safePage ? ' on' : ''}`}
            aria-current={entry === safePage ? 'page' : undefined}
            disabled={disabled}
            onClick={() => onPage(entry)}
          >
            {entry}
          </button>
        ),
      )}

      <button
        type="button"
        className="st-page-step"
        disabled={disabled || safePage >= safeLast}
        onClick={() => onPage(safePage + 1)}
        aria-label="Next page"
      >
        <svg
          className="h-4 w-4"
          fill="none"
          stroke="currentColor"
          strokeWidth="2.5"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
        </svg>
      </button>
    </nav>
  );
}
