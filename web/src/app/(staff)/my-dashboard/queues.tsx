'use client';

import { useEffect, useState } from 'react';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import type { ReviewRow } from '@/lib/domain/request-review';
import { ReviewModal } from '@/components/review/review-modal';
import { RequestTable } from '@/components/review/request-table';
import { Pager } from '@/components/review/pager';
import { useCompletedHistory } from '@/components/review/use-completed-history';

type Tab = 'triage' | 'active' | 'completed';

/**
 * The staff cockpit's queues.
 *
 * "To accept" leads, because an unaccepted assignment blocks everything after
 * it — the stage gate for →In Progress will not pass until the assignee has
 * accepted, so nothing can move while it sits there. Completed is everything
 * this person has finished, fetched a page at a time.
 */
export function StaffQueues({
  triage,
  active,
  completedCount,
  activeTab,
  openTracking,
}: {
  triage: ReviewRow[];
  active: ReviewRow[];
  completedCount: number;
  activeTab: Tab;
  openTracking: string | null;
}) {
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();

  const [tab, setTab] = useState<Tab>(activeTab);
  const [open, setOpen] = useState<ReviewRow | null>(null);

  const history = useCompletedHistory(tab === 'completed', '');
  const completed = history.data?.rows ?? [];
  const rows = tab === 'triage' ? triage : tab === 'active' ? active : completed;

  useEffect(() => {
    if (!openTracking) {
      return;
    }

    const found = [...triage, ...active, ...completed].find(
      (row) => row.trackingNumber === openTracking,
    );

    if (found) {
      setOpen(found);
    }
  }, [openTracking, triage, active, completed]);

  // Keep the open row fresh after an action re-renders the page beneath it.
  useEffect(() => {
    if (!open) {
      return;
    }

    const refreshed = [...triage, ...active, ...completed].find((row) => row.id === open.id);

    if (refreshed && refreshed !== open) {
      setOpen(refreshed);
    }
  }, [triage, active, completed, open]);

  return (
    <>
      <div className="st-toolbar">
        <div className="st-tabs" role="tablist" aria-label="My requests">
          {(
            [
              ['triage', 'To accept', triage.length],
              ['active', 'In progress', active.length],
              ['completed', 'Completed', completedCount],
            ] as const
          ).map(([key, label, count]) => (
            <button
              key={key}
              type="button"
              role="tab"
              aria-selected={tab === key}
              className={`st-tab${tab === key ? ' on' : ''}`}
              onClick={() => {
                setTab(key);
                const next = new URLSearchParams(params.toString());
                next.set('tab', key);
                router.replace(`${pathname}?${next.toString()}`);
              }}
            >
              {label}
              <span className="st-tab-count">{count}</span>
            </button>
          ))}
        </div>
      </div>

      {tab === 'completed' && history.loading && !history.data ? (
        <p className="text-sm text-ink-soft">Loading completed requests…</p>
      ) : null}

      <RequestTable
        rows={rows}
        onOpen={setOpen}
        showAssignee={false}
        emptyTitle={
          tab === 'triage'
            ? 'Nothing waiting on you'
            : tab === 'active'
              ? 'No requests in progress'
              : history.failed
                ? 'Completed requests could not be loaded'
                : 'Nothing completed yet'
        }
        emptyBody={
          tab === 'triage'
            ? 'New assignments appear here. Accepting one is what lets it move past Pending.'
            : tab === 'active'
              ? 'Accept an assignment to start work on it.'
              : history.failed
                ? 'Check your connection, then open the tab again.'
                : 'Requests you finish land here.'
        }
      />

      {tab === 'completed' && history.data ? (
        <Pager
          page={history.data.page}
          lastPage={history.data.lastPage}
          perPage={history.data.perPage}
          total={history.data.total}
          onPage={(page) => void history.load(page)}
          disabled={history.loading}
        />
      ) : null}

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
    </>
  );
}
