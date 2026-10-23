'use client';

/**
 * The request lists' pager: "11–20 of 34", ‹ 1 2 3 … 9 ›.
 */
export function Pager({
  page,
  lastPage,
  perPage,
  total,
  onPage,
  disabled = false,
}: {
  page: number;
  lastPage: number;
  perPage: number;
  total: number;
  onPage: (page: number) => void;
  disabled?: boolean;
}) {
  if (total <= perPage) {
    return null;
  }

  return (
    <nav className="st-pager" aria-label="Request pages">
      <span className="st-pager-info">
        {(page - 1) * perPage + 1}–{Math.min(page * perPage, total)} of {total}
      </span>

      <button
        type="button"
        className="st-page-step"
        disabled={disabled || page === 1}
        onClick={() => onPage(page - 1)}
        aria-label="Previous page"
      >
        ‹
      </button>

      {pageList(page, lastPage).map((entry, index) =>
        entry === 'gap' ? (
          <span key={`gap-${index}`} className="st-page" aria-hidden="true">
            …
          </span>
        ) : (
          <button
            key={entry}
            type="button"
            className={`st-page${entry === page ? ' on' : ''}`}
            aria-current={entry === page ? 'page' : undefined}
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
        disabled={disabled || page === lastPage}
        onClick={() => onPage(page + 1)}
        aria-label="Next page"
      >
        ›
      </button>
    </nav>
  );
}

/** 1…n up to six pages, then first / around-current / last with gaps. */
export function pageList(current: number, last: number): Array<number | 'gap'> {
  if (last <= 6) {
    return Array.from({ length: last }, (_, index) => index + 1);
  }

  if (current > 5) {
    return ([1, 'gap', current - 1, current, current + 1, 'gap', last] as Array<number | 'gap'>).filter(
      (entry) => entry === 'gap' || (entry >= 1 && entry <= last),
    );
  }

  return [1, 2, 3, 4, 5, 'gap', last];
}
