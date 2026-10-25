'use client';

import type { ReviewRow } from '@/lib/domain/request-review';

export function requestTypeIcon(type: string | null | undefined): string {
  const needle = (type ?? '').toLowerCase();
  const rules: Array<[string[], string]> = [
    [['lei', 'flower', 'wreath'], 'color-triangle.svg'],
    [['plaza', 'hall', 'venue', 'court', 'gym', 'facility', 'building'], 'office-building.svg'],
    [['flag'], 'flag.png'],
    [['banner', 'tarpaulin', 'spider', 'streamer'], 'web.png'],
  ];

  for (const [keywords, file] of rules) {
    if (keywords.some((kw) => needle.includes(kw))) {
      return `/images/request-types/${file}`;
    }
  }

  return '/images/request-types/task-list-pen.svg';
}

/**
 * The shared request table (`dashboard.blade.php`, `my-dashboard.blade.php`,
 * `admin/requests/index.blade.php`).
 *
 * Renders `.st-scroll > table.st-table` inside the caller's `.st-card`.
 */
export function RequestTable({
  rows,
  onOpen,
  onClaim,
  emptyTitle,
  emptyBody,
  showAssignee = true,
  spansDepartments = false,
  showStatusPill = true,
  dateHeading = 'Date',
  actionLabel = 'View details',
  loading = false,
}: {
  rows: ReviewRow[];
  onOpen: (row: ReviewRow) => void;
  onClaim?: (row: ReviewRow) => void;
  emptyTitle: string;
  emptyBody: string;
  showAssignee?: boolean;
  spansDepartments?: boolean;
  showStatusPill?: boolean;
  dateHeading?: string;
  actionLabel?: string;
  loading?: boolean;
}) {
  const colSpan = 5 + (spansDepartments ? 1 : 0) + (showAssignee ? 1 : 0);

  return (
    <div className="st-scroll">
      <table className="st-table">
        <thead>
          <tr>
            <th scope="col">Request Type</th>
            <th scope="col">Tracking ID</th>
            {spansDepartments ? <th scope="col">Department</th> : null}
            <th scope="col">{dateHeading}</th>
            <th scope="col">Requested by</th>
            {showAssignee ? <th scope="col">Handled by</th> : null}
            <th scope="col">Action</th>
          </tr>
        </thead>
        <tbody>
          {loading && rows.length === 0 ? (
            <tr className="st-empty">
              <td colSpan={colSpan}>
                <p className="text-sm text-ink-soft">Loading completed requests…</p>
              </td>
            </tr>
          ) : rows.length === 0 ? (
            <tr className="st-empty">
              <td colSpan={colSpan}>
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
                        d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"
                      />
                    </svg>
                  </div>
                  <p className="mt-3 text-sm font-semibold text-ink">{emptyTitle}</p>
                  <p className="mt-1 text-sm text-ink-soft">{emptyBody}</p>
                </div>
              </td>
            </tr>
          ) : (
            rows.map((row) => (
              <tr key={row.id}>
                <td>
                  <div className="st-type">
                    <span className="st-disc" aria-hidden="true">
                      <img src="/images/staff/row-disc.svg" alt="" />
                      <img src={requestTypeIcon(row.documentType)} alt="" />
                    </span>
                    <div className="min-w-0">
                      <p className="st-name truncate">{row.documentType}</p>
                      {showStatusPill ? (
                        <div className="mt-1 flex flex-wrap items-center gap-1.5">
                          <span className={`pill inline-block ${toneFor(row.status)}`}>
                            {row.statusLabel}
                          </span>
                          {row.remarks ? (
                            <span
                              className="inline-flex items-center gap-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-semibold text-amber-850"
                              title={`Supervisor note: ${row.remarks}`}
                            >
                              <svg className="h-3 w-3 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                              </svg>
                              Needs Revision
                            </span>
                          ) : null}
                          {row.booking ? <span className="chip">{row.booking.item}</span> : null}
                          {row.holdOverdueDays !== null ? (
                            <span className="cc-tag cc-tag-late" title="Past its own hold deadline">
                              Stalled
                            </span>
                          ) : null}
                          {row.overSla ? (
                            <span className="cc-tag cc-tag-late" title="Past this stage's time">
                              +{row.overSla} late
                            </span>
                          ) : null}
                        </div>
                      ) : row.booking ? (
                        <div className="mt-1 flex flex-wrap items-center gap-1.5">
                          <span className="chip">{row.booking.item}</span>
                        </div>
                      ) : null}
                    </div>
                  </div>
                </td>
                <td>
                  <div className="inline-flex items-center gap-1.5">
                    <span className="mono">{row.trackingNumber}</span>
                    {row.unreadMessages > 0 ? (
                      <span className="st-filter-badge" title="Unread citizen message">
                        {row.unreadMessages}
                      </span>
                    ) : null}
                    {row.needsTriage ? <span className="chip">Not accepted</span> : null}
                  </div>
                </td>
                {spansDepartments ? (
                  <td>
                    <span>{row.department || 'Not yet routed'}</span>
                    {row.thedId ? (
                      <span className="ml-1 mono text-xs text-ink-soft">· {row.thedId}</span>
                    ) : null}
                  </td>
                ) : null}
                <td className="mono">{row.submittedAt ?? row.dateShort ?? '—'}</td>
                <td>{row.citizenName || '—'}</td>
                {showAssignee ? <td>{row.assignedStaff || '—'}</td> : null}
                <td className="whitespace-nowrap">
                  <div className="flex items-center gap-3 shrink-0">
                    {onClaim && !row.assignedTo ? (
                      <button
                        type="button"
                        onClick={(e) => {
                          e.stopPropagation();
                          onClaim(row);
                        }}
                        className="cr-btn cr-btn-primary shrink-0 !px-2.5 !py-1 !text-xs font-semibold"
                        title="Claim and start processing"
                      >
                        Claim
                      </button>
                    ) : null}
                    <button
                      type="button"
                      onClick={() => onOpen(row)}
                      className="st-action shrink-0"
                    >
                      <img src="/images/staff/icon-eye.svg" alt="" className="shrink-0" />
                      <span>{actionLabel}</span>
                    </button>
                  </div>
                </td>
              </tr>
            ))
          )}
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
