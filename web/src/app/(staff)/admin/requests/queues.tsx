'use client';

import { useEffect, useMemo, useState } from 'react';
import type { ReviewRow } from '@/lib/domain/request-review';
import {
  OVERSIGHT_QUEUES,
  type OversightQueue,
  inQueue,
  isClosed,
  matchesSearch,
} from '@/lib/domain/request-oversight';
import { ReviewModal } from '@/components/review/review-modal';
import { RequestTable } from '@/components/review/request-table';
import { Pager } from '@/components/review/pager';

/**
 * The super admin's five queues over every citizen request: Open, Unassigned,
 * Overdue, Completed, Denied.
 *
 * The same table and the same Review modal as the supervisor dashboard, so one
 * request reads the same on every screen — but in oversight mode, which offers
 * Notify instead of assign and advance.
 *
 * Tabs, search and paging all run on the one payload the page loaded: the queue
 * counts have to agree with each other, and they cannot if each tab is its own
 * query over a moving table.
 */
const PER_PAGE = 15;

export function OversightQueues({
  requests,
  openTracking,
}: {
  requests: ReviewRow[];
  openTracking: string | null;
}) {
  const [tab, setTab] = useState<OversightQueue>('open');
  const [query, setQuery] = useState('');
  const [page, setPage] = useState(1);
  const [open, setOpen] = useState<ReviewRow | null>(null);

  const matches = useMemo(
    () => requests.filter((row) => inQueue(row, tab) && matchesSearch(row, query)),
    [requests, tab, query],
  );

  const pageCount = Math.max(1, Math.ceil(matches.length / PER_PAGE));
  const current = Math.min(page, pageCount);
  const pageItems = matches.slice((current - 1) * PER_PAGE, current * PER_PAGE);

  // A link from the command center, the unrouted alert or a tracking page opens
  // that one request — including a finished one, which lives behind its own tab.
  useEffect(() => {
    if (!openTracking) {
      return;
    }

    const found = requests.find((row) => row.trackingNumber === openTracking);

    if (!found) {
      return;
    }

    if (isClosed(found)) {
      setTab(found.status === 'denied' ? 'denied' : 'completed');
    }

    setOpen(found);
  }, [openTracking, requests]);

  // Keep the open row fresh after a Notify re-renders the page, so the modal
  // never shows a stage the request has already left.
  useEffect(() => {
    if (!open) {
      return;
    }

    const refreshed = requests.find((row) => row.id === open.id);

    if (refreshed && refreshed !== open) {
      setOpen(refreshed);
    }
  }, [requests, open]);

  const switchTab = (next: OversightQueue): void => {
    setTab(next);
    setPage(1);
  };

  return (
    <>
      <div className="st-toolbar">
        <div className="st-tabs" role="tablist" aria-label="Request queues">
          {OVERSIGHT_QUEUES.map((queue) => (
            <button
              key={queue.key}
              type="button"
              role="tab"
              aria-selected={tab === queue.key}
              className={`st-tab${tab === queue.key ? ' on' : ''}`}
              onClick={() => switchTab(queue.key)}
            >
              {queue.label}
              <span className="st-tab-count">
                {requests.filter((row) => inQueue(row, queue.key)).length}
              </span>
            </button>
          ))}
        </div>

        <div className="st-search">
          <label className="sr-only" htmlFor="oversightSearch">
            Search requests
          </label>
          <input
            id="oversightSearch"
            type="search"
            className="field"
            value={query}
            onChange={(event) => {
              setQuery(event.target.value);
              setPage(1);
            }}
            placeholder="Search name, tracking #, office or staff"
          />
        </div>
      </div>

      <div className="st-card">
        <RequestTable
          rows={pageItems}
          onOpen={setOpen}
          emptyTitle={query ? 'No requests match' : 'Nothing here'}
          emptyBody={
            query
              ? 'Try another search.'
              : 'Citizen requests in this state will appear here.'
          }
        />

        <Pager
          page={current}
          lastPage={pageCount}
          perPage={PER_PAGE}
          total={matches.length}
          onPage={setPage}
        />
      </div>

      {open ? (
        <ReviewModal
          row={open}
          mode="oversight"
          assignableStaff={[]}
          onClose={() => setOpen(null)}
        />
      ) : null}
    </>
  );
}
