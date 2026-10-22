'use client';

import type { ReviewRow } from '@/lib/domain/request-review';

/**
 * The shared request table. Every staff queue draws rows this way — supervisor
 * dashboard, staff cockpit, the super admin's Requests page — so a request
 * looks the same wherever it is found.
 *
 * A row is a button, not a link: opening it opens the Review modal in place
 * rather than navigating away from the queue that was being worked.
 */
export function RequestTable({
  rows,
  onOpen,
  emptyTitle,
  emptyBody,
  showAssignee = true,
}: {
  rows: ReviewRow[];
  onOpen: (row: ReviewRow) => void;
  emptyTitle: string;
  emptyBody: string;
  showAssignee?: boolean;
}) {
  if (rows.length === 0) {
    return (
      <div className="cr-empty">
        <p className="cr-empty-title">{emptyTitle}</p>
        <p className="cr-empty-msg">{emptyBody}</p>
      </div>
    );
  }

  return (
    <div className="table-wrap">
      <table className="st-table">
        <thead>
          <tr>
            <th scope="col">Tracking number</th>
            <th scope="col">Request</th>
            <th scope="col">Requested by</th>
            {showAssignee ? <th scope="col">Assigned to</th> : null}
            <th scope="col">Status</th>
            <th scope="col">Filed</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.id}>
              <td>
                <button type="button" className="st-action" onClick={() => onOpen(row)}>
                  <span className="id-chip">{row.trackingNumber}</span>
                  {/* Two things a supervisor must not have to open a row to see:
                      a citizen waiting on a reply, and an assignment nobody has
                      accepted yet. */}
                  {row.unreadMessages > 0 ? (
                    <span className="st-filter-badge" title="Unread citizen message">
                      {row.unreadMessages}
                    </span>
                  ) : null}
                  {row.needsTriage ? <span className="chip">Not accepted</span> : null}
                </button>
              </td>
              <td>
                <span className="st-type">{row.documentType}</span>
                {row.booking ? <span className="chip">{row.booking.item}</span> : null}
              </td>
              <td>{row.citizenName ?? '—'}</td>
              {showAssignee ? <td>{row.assignedStaff ?? 'Unassigned'}</td> : null}
              <td>
                <span className={`pill ${toneFor(row.status)}`}>{row.statusLabel}</span>
                {row.holdOverdueDays !== null ? (
                  <span className="cc-tag cc-tag-late" title="Past its own hold deadline">
                    Stalled
                  </span>
                ) : null}
                {/* How late, without opening the row — the one thing the
                    oversight queues are scanned for. */}
                {row.overSla ? (
                  <span className="cc-tag cc-tag-late" title="Past this stage's time">
                    +{row.overSla} late
                  </span>
                ) : null}
              </td>
              <td className="stamp">{row.dateShort ?? '—'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function toneFor(status: string): string {
  switch (status) {
    case 'completed':
    case 'approved':
      return 'p-green';
    case 'pending':
      return 'p-muted';
    case 'returned':
    case 'denied':
      return 'p-red';
    case 'on_hold':
      return 'p-hold';
    default:
      return 'p-amber';
  }
}
