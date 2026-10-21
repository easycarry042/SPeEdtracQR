'use client';

import { useEffect, useState } from 'react';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import type { ReviewRow } from '@/lib/domain/request-review';
import { ReviewModal } from '@/components/review/review-modal';
import { RequestTable } from '@/components/review/request-table';

/**
 * The supervisor desk's three queues.
 *
 * Unassigned / In Progress / Completed replaced the Assignments page, which
 * listed the same work but could not act on it. Opening a row opens the shared
 * Review modal, which assigns, reassigns, denies and drives the stage.
 */
type Tab = 'pending' | 'inprogress' | 'completed';

export function DashboardQueues({
  pending,
  active,
  completed,
  assignableStaff,
  activeTab,
  search,
  openTracking,
  officeName,
}: {
  pending: ReviewRow[];
  active: ReviewRow[];
  completed: ReviewRow[];
  assignableStaff: Array<{ id: number; name: string }>;
  activeTab: Tab;
  search: string;
  openTracking: string | null;
  officeName: string | null;
}) {
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();

  const [tab, setTab] = useState<Tab>(activeTab);
  const [query, setQuery] = useState(search);
  const [open, setOpen] = useState<ReviewRow | null>(null);

  const rows = tab === 'pending' ? pending : tab === 'inprogress' ? active : completed;

  // A request link can deep-link a row open (?open=SPD-…), which is how the
  // bell and the emailed assignment notice land someone on the right request.
  useEffect(() => {
    if (!openTracking) {
      return;
    }

    const found = [...pending, ...active, ...completed].find(
      (row) => row.trackingNumber === openTracking,
    );

    if (found) {
      setOpen(found);
    }
  }, [openTracking, pending, active, completed]);

  // Keep the open row's data fresh after an action re-renders the server page,
  // so the modal shows the new stage instead of the one it opened with.
  useEffect(() => {
    if (!open) {
      return;
    }

    const refreshed = [...pending, ...active, ...completed].find((row) => row.id === open.id);

    if (refreshed && refreshed !== open) {
      setOpen(refreshed);
    }
  }, [pending, active, completed, open]);

  const applySearch = (value: string): void => {
    const next = new URLSearchParams(params.toString());

    if (value.trim() === '') {
      next.delete('q');
    } else {
      next.set('q', value.trim());
    }

    next.set('tab', tab);
    router.replace(`${pathname}?${next.toString()}`);
  };

  return (
    <>
      <div className="st-toolbar">
        <div className="st-tabs" role="tablist" aria-label="Request queues">
          {(
            [
              ['pending', 'Unassigned', pending.length],
              ['inprogress', 'In Progress', active.length],
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

        <form
          className="st-search"
          onSubmit={(event) => {
            event.preventDefault();
            applySearch(query);
          }}
        >
          <input
            type="search"
            className="field"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Tracking number, type or name"
            aria-label="Search requests"
          />
          <button type="submit" className="cr-btn cr-btn-ghost cr-btn-sm">
            Search
          </button>
        </form>
      </div>

      <RequestTable
        rows={rows}
        onOpen={setOpen}
        emptyTitle={
          tab === 'pending'
            ? 'Nothing waiting to be assigned'
            : tab === 'inprogress'
              ? 'No work in flight'
              : 'Nothing completed in the last 90 days'
        }
        emptyBody={
          tab === 'pending'
            ? `New citizen requests for ${officeName ?? 'your office'} land here, along with any that reached no office yet.`
            : tab === 'inprogress'
              ? 'Requests appear here once you assign them to a staff member.'
              : 'Older completed work is in Reports.'
        }
        showAssignee={tab !== 'pending'}
      />

      {open ? (
        <ReviewModal
          row={open}
          mode="supervisor"
          assignableStaff={assignableStaff}
          onClose={() => setOpen(null)}
        />
      ) : null}
    </>
  );
}
