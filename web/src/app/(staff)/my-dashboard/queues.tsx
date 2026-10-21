'use client';

import { useEffect, useState } from 'react';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import type { ReviewRow } from '@/lib/domain/request-review';
import { ReviewModal } from '@/components/review/review-modal';
import { RequestTable } from '@/components/review/request-table';

type Tab = 'triage' | 'active' | 'completed';

/**
 * The staff cockpit's queues.
 *
 * "To accept" leads, because an unaccepted assignment blocks everything after
 * it — the stage gate for →In Progress will not pass until the assignee has
 * accepted, so nothing can move while it sits there.
 */
export function StaffQueues({
  triage,
  active,
  completed,
  activeTab,
  openTracking,
}: {
  triage: ReviewRow[];
  active: ReviewRow[];
  completed: ReviewRow[];
  activeTab: Tab;
  openTracking: string | null;
}) {
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();

  const [tab, setTab] = useState<Tab>(activeTab);
  const [open, setOpen] = useState<ReviewRow | null>(null);

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
              ['completed', 'Completed', completed.length],
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

      <RequestTable
        rows={rows}
        onOpen={setOpen}
        showAssignee={false}
        emptyTitle={
          tab === 'triage'
            ? 'Nothing waiting on you'
            : tab === 'active'
              ? 'No requests in progress'
              : 'Nothing completed in the last 90 days'
        }
        emptyBody={
          tab === 'triage'
            ? 'New assignments appear here. Accepting one is what lets it move past Pending.'
            : tab === 'active'
              ? 'Accept an assignment to start work on it.'
              : 'Older completed work is in History.'
        }
      />

      {open ? (
        <ReviewModal row={open} mode="staff" assignableStaff={[]} onClose={() => setOpen(null)} />
      ) : null}
    </>
  );
}
