'use client';

import { useMemo, useState } from 'react';
import Link from 'next/link';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import type { Inbox, InboxRow } from '@/lib/domain/internal-inbox';
import { Pager } from '@/components/review/pager';
import {
  EMPTY_STAFF_FILTER,
  StaffTableFilter,
  passesStaffFilter,
  staffFilterCount,
  type StaffFilterState,
} from '@/components/review/staff-table-filter';

type Tab = 'awaiting' | 'filed' | 'closed';

const PER_PAGE = 10;

function bandClass(band: string): string {
  switch (band) {
    case 'green':
      return 'p-green';
    case 'red':
      return 'p-red';
    case 'returned':
      return 'p-orange';
    case 'muted':
      return 'p-muted';
    default:
      return 'p-amber';
  }
}

/**
 * Port of `requests/partials/inbox.blade.php`.
 * Uses `.st-card > .st-scroll > table.st-table` with `.st-disc` icons,
 * `<StaffTableFilter>` ("Requesting office"), and 10-per-page pagination.
 */
export function InternalQueues({ inbox, activeTab }: { inbox: Inbox; activeTab: Tab }) {
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();

  const [tab, setTab] = useState<Tab>(activeTab);
  const [query, setQuery] = useState(inbox.search);
  const [filter, setFilter] = useState<StaffFilterState>(EMPTY_STAFF_FILTER);
  const [page, setPage] = useState(1);

  const rawRows =
    tab === 'awaiting' ? inbox.awaiting : tab === 'filed' ? inbox.filed : inbox.closed;

  const typeOptions = useMemo(() => {
    const all = [...inbox.awaiting, ...inbox.filed, ...inbox.closed];
    return all.map((r) => r.filedBy).filter((v): v is string => Boolean(v));
  }, [inbox]);

  const matches = useMemo(() => {
    const needle = query.trim().toLowerCase();
    return rawRows.filter((row) => {
      if (!passesStaffFilter(filter, row.filedBy, row.filedDay)) {
        return false;
      }
      if (!needle) {
        return true;
      }
      return [row.tracking, row.purpose, row.filedBy, row.filedByCode, row.filedByPerson].some(
        (field) => (field ?? '').toLowerCase().includes(needle),
      );
    });
  }, [rawRows, filter, query]);

  const pageCount = Math.max(1, Math.ceil(matches.length / PER_PAGE));
  const current = Math.min(page, pageCount);
  const pageItems = matches.slice((current - 1) * PER_PAGE, current * PER_PAGE);
  const hasFilter = Boolean(query.trim() || staffFilterCount(filter) > 0);

  // A row opens in the request modal over this queue (`?open=`), keeping the tab.
  const openHref = (id: number): string => {
    const next = new URLSearchParams(params.toString());
    next.set('tab', tab);
    next.set('open', String(id));

    return `${pathname}?${next.toString()}`;
  };

  const go = (next: URLSearchParams): void =>
    router.replace(`${pathname}?${next.toString()}`, { scroll: false });

  const tabs = inbox.departmentName
    ? ([
        ['awaiting', 'Awaiting my office', inbox.awaiting.length],
        ['filed', 'Filed by my office', inbox.filed.length],
        ['closed', 'Closed', inbox.closed.length],
      ] as const)
    : ([
        ['awaiting', 'Active', inbox.awaiting.length],
        ['closed', 'Closed', inbox.closed.length],
      ] as const);

  return (
    <div className="page-shell page-shell-loose">
      <section className="flex min-h-0 flex-1 flex-col">
        <div className="st-toolbar mb-4">
          <div className="st-tabs mr-auto" role="tablist" aria-label="Request queues">
            {tabs.map(([key, label, count]) => (
              <button
                key={key}
                type="button"
                role="tab"
                aria-selected={tab === key}
                className={tab === key ? 'on' : ''}
                onClick={() => {
                  setTab(key);
                  setPage(1);
                  const next = new URLSearchParams(params.toString());
                  next.set('tab', key);
                  go(next);
                }}
              >
                {label} <span className="st-tab-count">{count}</span>
              </button>
            ))}
          </div>

          <form
            method="GET"
            className="st-search"
            role="search"
            onSubmit={(event) => {
              event.preventDefault();
              const next = new URLSearchParams(params.toString());
              if (query.trim() === '') {
                next.delete('q');
              } else {
                next.set('q', query.trim());
              }
              next.set('tab', tab);
              go(next);
            }}
          >
            <svg
              fill="none"
              stroke="currentColor"
              strokeWidth="2.2"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <circle cx="11" cy="11" r="7" />
              <path strokeLinecap="round" d="m20 20-3.5-3.5" />
            </svg>
            <input
              type="search"
              name="q"
              value={query}
              onChange={(event) => {
                setQuery(event.target.value);
                setPage(1);
              }}
              placeholder="Search tracking # or request…"
              aria-label="Search internal requests"
            />
          </form>

          <StaffTableFilter
            id="internalFilter"
            value={filter}
            onChange={(next) => {
              setFilter(next);
              setPage(1);
            }}
            typeOptions={typeOptions}
            typeLabel="Requesting office"
            allTypesLabel="All offices"
          />
        </div>

        <div className="st-card">
          <div className="st-scroll">
            <table className="st-table">
              <thead>
                <tr>
                  <th>Request</th>
                  <th>From</th>
                  <th>Current stage</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                {pageItems.length === 0 ? (
                  <tr className="st-empty">
                    <td colSpan={5}>
                      <div className="mx-auto flex max-w-sm flex-col items-center">
                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-green-wash text-green-deep">
                          <svg
                            className="h-6 w-6"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                          >
                            <path
                              strokeLinecap="round"
                              strokeLinejoin="round"
                              d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
                            />
                          </svg>
                        </div>
                        <p className="mt-3 text-sm font-semibold text-ink">
                          {hasFilter && rawRows.length > 0
                            ? 'No requests match'
                            : tab === 'awaiting' && inbox.departmentName
                              ? `Nothing awaiting ${inbox.departmentName}`
                              : tab === 'filed'
                                ? 'No active requests in transit'
                                : 'Nothing here yet'}
                        </p>
                        <p className="mt-1 text-sm text-ink-soft">
                          {hasFilter && rawRows.length > 0
                            ? 'Loosen the filter to see more.'
                            : tab === 'awaiting' && inbox.departmentName
                              ? 'Requests routed to your office for action will appear here.'
                              : tab === 'filed'
                                ? "Requests your office has filed will show here until they're closed."
                                : 'Requests your office has closed will be archived here.'}
                        </p>
                      </div>
                    </td>
                  </tr>
                ) : (
                  pageItems.map((row) => (
                    <InternalRow key={row.id} row={row} href={openHref(row.id)} />
                  ))
                )}
              </tbody>
            </table>
          </div>

          <Pager
            page={current}
            lastPage={pageCount}
            perPage={PER_PAGE}
            total={matches.length}
            onPage={setPage}
            label="Internal request pages"
          />
        </div>
      </section>
    </div>
  );
}

function InternalRow({ row, href }: { row: InboxRow; href: string }) {
  return (
    <tr>
      <td>
        <div className="st-type">
          <span className="st-disc" aria-hidden="true">
            <img src="/images/staff/row-disc.svg" alt="" />
            <img src="/images/request-types/task-list-pen.svg" alt="" />
          </span>
          <div className="min-w-0">
            <Link
              href={href}
              scroll={false}
              className="st-name block truncate hover:text-green hover:underline"
            >
              {row.purpose || 'Internal Request'}
            </Link>
            <p className="st-sub mono">{row.tracking}</p>
          </div>
        </div>
      </td>
      <td>
        {row.filedByCode ?? row.filedBy ?? '—'}
        {row.filedByPerson ? <p className="st-sub">{row.filedByPerson}</p> : null}
      </td>
      <td>
        {row.atOffice ? (
          <>
            <p className="font-medium text-ink">{row.atOffice}</p>
            {row.stepAction || row.stepAging ? (
              <p className={`st-sub${row.stepStale ? ' font-semibold text-status-red' : ''}`}>
                {row.stepAction}
                {row.stepAging ? ` · ${row.stepAging}` : ''}
              </p>
            ) : null}
          </>
        ) : (
          '—'
        )}
      </td>
      <td>
        <span className={`pill ${bandClass(row.band)}`}>{row.statusLabel}</span>
      </td>
      <td>
        <Link href={href} scroll={false} className="st-action">
          <img src="/images/staff/icon-eye.svg" alt="" />
          Open
        </Link>
      </td>
    </tr>
  );
}
