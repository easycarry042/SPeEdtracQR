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
import { claimDocument } from '@/app/actions/document';

type Tab = 'pool' | 'active' | 'returned' | 'triage' | 'completed';

const PER_PAGE = 10;

/**
 * The staff cockpit's queues.
 *
 * "Available Pool" displays unassigned requests for their office with one-click "Claim" actions.
 * "My Active" displays requests they are currently handling.
 * "Returned for Revision" highlights requests sent back by the supervisor with notes.
 */
export function StaffQueues({
  openRequests,
  triage,
  active,
  returned,
  completedCount,
  activeTab,
  openTracking,
}: {
  openRequests: ReviewRow[];
  triage: ReviewRow[];
  active: ReviewRow[];
  returned: ReviewRow[];
  completedCount: number;
  activeTab: Tab;
  openTracking: string | null;
}) {
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();

  const [tab, setTab] = useState<Tab>(activeTab);
  const [query, setQuery] = useState('');
  const [filter, setFilter] = useState<StaffFilterState>(EMPTY_STAFF_FILTER);
  const [page, setPage] = useState(1);
  const [open, setOpen] = useState<ReviewRow | null>(null);
  const [claimingId, setClaimingId] = useState<number | null>(null);

  const history = useCompletedHistory(tab === 'completed', query);
  const completed = history.data?.rows ?? [];

  const allLocal = useMemo(() => [...openRequests, ...triage, ...active], [openRequests, triage, active]);
  const typeOptions = useMemo(() => {
    const source = tab === 'completed' ? completed : allLocal;
    return source.map((r) => r.documentType);
  }, [tab, completed, allLocal]);

  const matches = useMemo(() => {
    const base =
      tab === 'pool'
        ? openRequests
        : tab === 'triage'
          ? triage
          : tab === 'returned'
            ? returned
            : tab === 'active'
              ? active
              : completed;
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
      return [r.citizenName, r.trackingNumber, r.documentType].some((field) =>
        (field ?? '').toLowerCase().includes(needle),
      );
    });
  }, [tab, openRequests, triage, returned, active, completed, filter, query]);

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

  useEffect(() => {
    if (!openTracking) {
      return;
    }

    const found = [...openRequests, ...triage, ...active, ...completed].find(
      (row) => row.trackingNumber === openTracking,
    );

    if (found) {
      setOpen(found);
    }
  }, [openTracking, openRequests, triage, active, completed]);

  // Keep the open row fresh after an action re-renders the page beneath it.
  useEffect(() => {
    if (!open) {
      return;
    }

    const refreshed = [...openRequests, ...triage, ...active, ...completed].find((row) => row.id === open.id);

    if (refreshed && refreshed !== open) {
      setOpen(refreshed);
    }
  }, [openRequests, triage, active, completed, open]);

  const handleClaim = async (row: ReviewRow) => {
    if (claimingId) return;
    setClaimingId(row.id);
    try {
      const res = await claimDocument(row.id);
      if (res.ok) {
        router.refresh();
      } else {
        alert(res.message);
      }
    } finally {
      setClaimingId(null);
    }
  };

  const hasFilter = Boolean(query.trim() || staffFilterCount(filter) > 0);

  return (
    <section>
      <div className="st-toolbar mb-4 flex-wrap justify-between gap-3">
        <div className="st-tabs" role="tablist" aria-label="My requests">
          {(
            [
              ['pool', 'Available Pool', openRequests.length],
              ['active', 'My Active', active.length],
              ['returned', 'Returned for Revision', returned.length],
              ['triage', 'To Accept', triage.length],
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
          <label htmlFor="staffSearch" className="sr-only">
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
              id="staffSearch"
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
            id="staffFilter"
            value={filter}
            onChange={(next) => {
              setFilter(next);
              setPage(1);
            }}
            typeOptions={typeOptions}
          />
        </div>
      </div>

      <div className="st-card">
        <RequestTable
          rows={pageItems}
          onOpen={setOpen}
          onClaim={tab === 'pool' ? handleClaim : undefined}
          showAssignee={false}
          showStatusPill={tab === 'active' || tab === 'returned'}
          dateHeading={tab === 'completed' ? 'Completed' : 'Date'}
          actionLabel={
            tab === 'pool'
              ? 'Claim / Review'
              : tab === 'returned'
                ? 'Fix & Resubmit'
                : tab === 'triage'
                  ? 'Review'
                  : 'View details'
          }
          loading={tab === 'completed' && history.loading && !history.data}
          emptyTitle={
            tab === 'completed' && history.failed
              ? 'Completed requests could not be loaded'
              : hasFilter
                ? 'No requests match'
                : tab === 'pool'
                  ? 'No unassigned requests in available pool'
                  : tab === 'returned'
                    ? 'No requests returned for revision'
                    : tab === 'triage'
                      ? 'Nothing waiting on you'
                      : tab === 'active'
                        ? 'No active requests in progress'
                        : 'Nothing completed yet'
          }
          emptyBody={
            tab === 'completed' && history.failed
              ? 'Check your connection, then open the tab again.'
              : hasFilter
                ? 'Try another search, or loosen the filter.'
                : tab === 'pool'
                  ? 'New unassigned requests in your office will appear here with one-click Claim actions.'
                  : tab === 'returned'
                    ? 'Requests sent back by your supervisor with revision notes will appear here.'
                    : tab === 'triage'
                      ? 'New assignments appear here. Accepting one is what lets it move past Pending.'
                      : tab === 'active'
                        ? 'Accept an assignment or claim an open request to start work on it.'
                        : 'Requests you finish land here.'
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
          mode="staff"
          assignableStaff={[]}
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
