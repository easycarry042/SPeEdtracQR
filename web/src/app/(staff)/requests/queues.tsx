'use client';

import { useState } from 'react';
import Link from 'next/link';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import type { Inbox, InboxRow } from '@/lib/domain/internal-inbox';
import { routes } from '@/lib/routes';

type Tab = 'awaiting' | 'filed' | 'closed';

/**
 * The Internal page's three queues.
 *
 * "Awaiting you" leads because an internal request stops dead until someone at
 * the holding office receives it — a paper folder has to be scanned in, and a
 * soft copy picked up by the person it was sent to.
 */
export function InternalQueues({ inbox, activeTab }: { inbox: Inbox; activeTab: Tab }) {
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();

  const [tab, setTab] = useState<Tab>(activeTab);
  const [query, setQuery] = useState(inbox.search);

  const rows = tab === 'awaiting' ? inbox.awaiting : tab === 'filed' ? inbox.filed : inbox.closed;

  const go = (next: URLSearchParams): void => router.replace(`${pathname}?${next.toString()}`);

  return (
    <>
      <div className="st-toolbar">
        <div className="st-tabs" role="tablist" aria-label="Internal requests">
          {(
            [
              ['awaiting', inbox.departmentName ? 'At your office' : 'Open', inbox.awaiting.length],
              ['filed', 'Filed by you', inbox.filed.length],
              ['closed', 'Closed', inbox.closed.length],
            ] as const
          ).map(([key, label, count]) =>
            key === 'filed' && inbox.departmentName === null ? null : (
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
                  go(next);
                }}
              >
                {label}
                <span className="st-tab-count">{count}</span>
              </button>
            ),
          )}
        </div>

        <form
          className="st-search"
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
          <input
            type="search"
            className="field"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Request number or purpose"
            aria-label="Search internal requests"
          />
          <button type="submit" className="cr-btn cr-btn-ghost cr-btn-sm">
            Search
          </button>
        </form>
      </div>

      {rows.length === 0 ? (
        <div className="cr-empty">
          <p className="cr-empty-title">
            {tab === 'awaiting'
              ? 'Nothing waiting at your office'
              : tab === 'filed'
                ? 'You have not filed anything open'
                : 'Nothing closed yet'}
          </p>
          <p className="cr-empty-msg">
            {tab === 'awaiting'
              ? 'Requests another office sends you land here. A paper folder has to be scanned in before anyone can act on it.'
              : tab === 'filed'
                ? 'Requests your office files appear here until they close.'
                : 'Completed, denied and withdrawn requests end up here.'}
          </p>
        </div>
      ) : (
        <ul className="mvlist">
          {rows.map((row) => (
            <InternalRow key={row.id} row={row} />
          ))}
        </ul>
      )}
    </>
  );
}

function InternalRow({ row }: { row: InboxRow }) {
  return (
    <li className="mvrow">
      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-2">
          <Link href={routes.requestsShow(row.id)} className="id-chip">
            {row.tracking}
          </Link>

          {/* A soft copy has no folder to hand over, so it is sent to someone
              by name — worth saying on the row, because receiving it works
              differently. */}
          {row.isSoftCopy ? <span className="chip">Soft copy</span> : null}

          {/* Until someone at the holding office receives it, nothing moves. */}
          {!row.received && row.atOffice ? (
            <span className="pill p-amber">Awaiting receipt</span>
          ) : (
            <span className={`pill p-${row.band === 'returned' ? 'amber' : row.band}`}>
              {row.statusLabel}
            </span>
          )}
        </div>

        <p className="dir-sub mt-1">
          {row.purpose || 'No purpose given'}
        </p>

        <p className="dir-sub">
          Filed by {row.filedBy ?? 'an office'}
          {row.filedByPerson ? ` · ${row.filedByPerson}` : ''} · {row.filedOn}
          {row.atOffice ? ` · now at ${row.atOffice}` : ''}
          {row.recipient ? ` · for ${row.recipient}` : ''}
        </p>
      </div>

      <Link href={routes.requestsShow(row.id)} className="cr-btn cr-btn-ghost cr-btn-sm">
        Open
      </Link>
    </li>
  );
}
