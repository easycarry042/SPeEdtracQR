'use client';

import { useEffect, useMemo, useState } from 'react';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import type { ReviewRow } from '@/lib/domain/request-review';
import { ReviewModal } from '@/components/review/review-modal';
import { RequestTable } from '@/components/review/request-table';
import { Pager } from '@/components/review/pager';
import {
  EMPTY_STAFF_FILTER,
  StaffTableFilter,
  passesStaffFilter,
  staffFilterCount,
  type StaffFilterState,
} from '@/components/review/staff-table-filter';
import { useCompletedHistory } from '@/components/review/use-completed-history';

/**
 * The supervisor desk's three queues (`dashboard.blade.php` lines 124–338).
 *
 * Unassigned / In Progress / Completed replaced the Assignments page, which
 * listed the same work but could not act on it. Opening a row opens the shared
 * Review modal, which assigns, reassigns, denies and drives the stage.
 * Completed is the office's whole history, fetched a page at a time; only its
 * count rides in the page.
 */
type Tab = 'pending' | 'inprogress' | 'clearance' | 'completed';

const PER_PAGE = 10;

export function DashboardQueues({
  pending,
  active,
  clearance,
  completedCount,
  assignableStaff,
  activeTab,
  search,
  openTracking,
  officeName,
  spansDepartments = false,
}: {
  pending: ReviewRow[];
  active: ReviewRow[];
  clearance: ReviewRow[];
  completedCount: number;
  assignableStaff: Array<{ id: number; name: string; is_available?: boolean }>;
  activeTab: Tab;
  search: string;
  openTracking: string | null;
  officeName: string | null;
  spansDepartments?: boolean;
}) {
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();

  const [tab, setTab] = useState<Tab>(activeTab);
  const [query, setQuery] = useState(search);
  const [filter, setFilter] = useState<StaffFilterState>(EMPTY_STAFF_FILTER);
  const [oversightFilter, setOversightFilter] = useState<'all' | 'breached'>('all');
  const [page, setPage] = useState(1);
  const [open, setOpen] = useState<ReviewRow | null>(null);

  const history = useCompletedHistory(tab === 'completed', query);
  const completed = history.data?.rows ?? [];

  const breachedCount = useMemo(
    () => active.filter((r) => r.overSla !== null || r.holdOverdueDays !== null).length,
    [active],
  );

  const allLocal = useMemo(() => [...pending, ...active, ...clearance], [pending, active, clearance]);
  const typeOptions = useMemo(() => {
    const source = tab === 'completed' ? completed : allLocal;
    return source.map((r) => r.documentType);
  }, [tab, completed, allLocal]);

  const matches = useMemo(() => {
    let base =
      tab === 'clearance'
        ? clearance
        : tab === 'inprogress'
          ? active
          : tab === 'pending'
            ? pending
            : completed;

    if (tab === 'inprogress' && oversightFilter === 'breached') {
      base = base.filter((r) => r.overSla !== null || r.holdOverdueDays !== null);
    }
    const needle = query.trim().toLowerCase();

    return base.filter((r) => {
      if (!passesStaffFilter(filter, r.documentType, r.submittedOn)) {
        return false;
      }
      if (tab === 'completed') {
        return true;
      }
      if (!needle) {
        return true;
      }
      return [r.citizenName, r.trackingNumber, r.documentType, r.department].some((field) =>
        (field ?? '').toLowerCase().includes(needle),
      );
    });
  }, [tab, pending, active, clearance, completed, filter, query, oversightFilter]);

  const pageCount =
    tab === 'completed'
      ? (history.data?.lastPage ?? 1)
      : Math.max(1, Math.ceil(matches.length / PER_PAGE));
  const total = tab === 'completed' ? (history.data?.total ?? matches.length) : matches.length;
  const current =
    tab === 'completed' ? (history.data?.page ?? 1) : Math.min(page, pageCount);
  const pageItems =
    tab === 'completed'
      ? matches
      : matches.slice((current - 1) * PER_PAGE, current * PER_PAGE);

  // A request link can deep-link a row open (?open=SPD-…), which is how the
  // bell and the emailed assignment notice land someone on the right request.
  useEffect(() => {
    if (!openTracking) {
      return;
    }

    const found = [...pending, ...active, ...clearance, ...completed].find(
      (row) => row.trackingNumber === openTracking,
    );

    if (found) {
      setOpen(found);
    }
  }, [openTracking, pending, active, clearance, completed]);

  // Keep the open row's data fresh after an action re-renders the server page,
  // so the modal shows the new stage instead of the one it opened with.
  useEffect(() => {
    if (!open) {
      return;
    }

    const refreshed = [...pending, ...active, ...clearance, ...completed].find((row) => row.id === open.id);

    if (refreshed && refreshed !== open) {
      setOpen(refreshed);
    }
  }, [pending, active, clearance, completed, open]);

  const hasFilter = Boolean(query.trim() || staffFilterCount(filter) > 0);

  return (
    <section>
      <div className="st-toolbar mb-4 flex-wrap justify-between gap-3">
        <div className="st-tabs" role="tablist" aria-label="Request queues">
          {(
            [
              ['clearance', 'Review & Sign-Off', clearance.length],
              ['inprogress', 'Department Oversight', active.length],
              ['pending', 'Unassigned', pending.length],
              ['completed', 'Completed', completedCount],
            ] as const
          ).map(([key, label, count]) => (
            <button
              key={key}
              type="button"
              role="tab"
              aria-selected={tab === key}
              className={tab === key ? 'on' : ''}
              onClick={() => {
                setTab(key as Tab);
                setPage(1);
                const next = new URLSearchParams(params.toString());
                next.set('tab', key);
                router.replace(`${pathname}?${next.toString()}`, { scroll: false });
              }}
            >
              {label} <span className="st-tab-count">{count}</span>
            </button>
          ))}
        </div>

        <div className="flex min-w-0 flex-1 items-center justify-end gap-3">
          <label htmlFor="requestSearch" className="sr-only">
            Search requests
          </label>
          <div className="st-search">
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
              id="requestSearch"
              type="search"
              value={query}
              onChange={(event) => {
                setQuery(event.target.value);
                setPage(1);
              }}
              data-kbd-search
              placeholder="Search"
            />
          </div>

          <StaffTableFilter
            id="supervisorFilter"
            value={filter}
            onChange={(next) => {
              setFilter(next);
              setPage(1);
            }}
            typeOptions={typeOptions}
          />
        </div>
      </div>

      {tab === 'inprogress' ? (
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-hairline bg-gray-50/80 px-4 py-2.5 text-xs">
          <div className="flex flex-wrap items-center gap-2">
            <span className="font-bold text-ink-soft uppercase tracking-wider">Oversight Filter:</span>
            <button
              type="button"
              className={`pill text-xs cursor-pointer transition-colors ${oversightFilter === 'all' ? 'p-green font-bold' : 'p-muted'}`}
              onClick={() => {
                setOversightFilter('all');
                setPage(1);
              }}
            >
              All Ongoing ({active.length})
            </button>
            <button
              type="button"
              className={`pill text-xs cursor-pointer transition-colors ${oversightFilter === 'breached' ? 'p-red font-bold' : 'p-muted'}`}
              onClick={() => {
                setOversightFilter('breached');
                setPage(1);
              }}
              title="Filter to requests that exceeded stage SLA"
            >
              SLA Breached ({breachedCount})
            </button>
          </div>
          <span className="text-ink-soft hidden md:inline">
            Supervisors can review and reassign requests if the assigned staff member is delayed or absent.
          </span>
        </div>
      ) : null}

      <div className="st-card">
        <RequestTable
          rows={pageItems}
          onOpen={setOpen}
          spansDepartments={spansDepartments}
          showStatusPill={tab === 'inprogress' || tab === 'clearance'}
          dateHeading={tab === 'completed' ? 'Completed' : 'Date'}
          actionLabel={
            tab === 'clearance'
              ? 'Review & Sign-Off'
              : tab === 'inprogress'
                ? 'Oversight / Reassign'
                : tab === 'pending'
                  ? 'Review & Assign'
                  : 'View details'
          }
          showAssignee={tab !== 'pending'}
          loading={tab === 'completed' && history.loading && !history.data}
          emptyTitle={
            tab === 'completed' && history.failed
              ? 'Completed requests could not be loaded'
              : hasFilter
                ? 'No requests match'
                : tab === 'clearance'
                  ? 'No submissions awaiting review & sign-off'
                  : tab === 'inprogress'
                    ? oversightFilter === 'breached'
                      ? 'No ongoing requests have breached SLA'
                      : 'No ongoing requests in department'
                    : tab === 'completed'
                      ? 'Nothing completed yet'
                      : 'No unassigned requests'
          }
          emptyBody={
            tab === 'completed' && history.failed
              ? 'Check your connection and open the tab again.'
              : hasFilter
                ? 'Try another search, or loosen the filter.'
                : tab === 'clearance'
                  ? 'Completed staff submissions awaiting supervisor confirmation and release will appear here.'
                  : tab === 'inprogress'
                    ? oversightFilter === 'breached'
                      ? 'All active requests in your office are currently within their SLA budgets.'
                      : 'Requests your staff are working on appear here.'
                    : tab === 'completed'
                      ? `Every request ${officeName ?? 'your office'} finishes appears here, newest first.`
                      : 'New citizen submissions awaiting approval will appear here.'
          }
        />

        <Pager
          page={current}
          lastPage={pageCount}
          perPage={tab === 'completed' ? (history.data?.perPage ?? PER_PAGE) : PER_PAGE}
          total={total}
          onPage={(target) => {
            if (tab === 'completed') {
              void history.load(target);
            } else {
              setPage(target);
            }
          }}
          disabled={tab === 'completed' && history.loading}
        />
      </div>

      {open ? (
        <ReviewModal
          row={open}
          mode="supervisor"
          assignableStaff={assignableStaff}
          onClose={() => {
            setOpen(null);

            if (tab === 'completed') {
              void history.reload();
            }
          }}
        />
      ) : null}
    </section>
  );
}
