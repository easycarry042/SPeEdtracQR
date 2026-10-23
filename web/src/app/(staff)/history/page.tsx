import type { Metadata } from 'next';
import Link from 'next/link';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { requireActor } from '@/lib/auth/actor';
import { fromLoose, label as statusLabel } from '@/lib/domain/document-status';
import { PER_PAGE, historyPage, type HistoryFilters } from '@/lib/domain/history';
import { pageList } from '@/components/review/page-list';
import { routes } from '@/lib/routes';
import { formatNaive } from '@/lib/time';
import { HistoryFilterBar } from './filter-bar';

export const metadata: Metadata = { title: 'History' };

export const dynamic = 'force-dynamic';

/**
 * Port of `history/index.blade.php` — completed requests in the viewer's
 * scope, searchable and filterable, 15 a page, with a CSV export of the same
 * rows. (The dashboards' Completed tabs page the same history in the Review
 * modal's shape; this page is the archive and the export.)
 */
export default async function HistoryPage({
  searchParams,
}: {
  searchParams: Promise<HistoryFilters & { page?: string }>;
}) {
  const actor = await requireActor();
  const query = await searchParams;
  const filters: HistoryFilters = {
    search: query.search ?? '',
    document_type: query.document_type ?? '',
    from: query.from ?? '',
    to: query.to ?? '',
  };
  const data = await historyPage(actor, Number(query.page ?? 1), filters);
  const active = Object.fromEntries(Object.entries(filters).filter(([, value]) => value)) as Record<string, string>;
  const firstIndex = (data.page - 1) * PER_PAGE;

  return (
    <AuthenticatedPage title="History">
      <div className="page-shell page-shell-loose">
        <HistoryFilterBar filters={filters} documentTypes={data.documentTypes} exportUrl={routes.historyExport(active)} />

        <div className="panel">
          <div className="overflow-x-auto">
            <table className="reg min-w-full">
              <thead>
                <tr>
                  <th>#</th>
                  <th>File name</th>
                  <th>Tracking ID</th>
                  <th>Date</th>
                  <th>Category</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                {data.rows.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="py-10 text-center">
                      <p className="font-semibold text-ink">No records match</p>
                      <p className="mt-1 text-sm text-ink-soft">
                        History lists completed requests only. Nothing here matches the current search or filters —
                        try broadening your terms.
                      </p>
                    </td>
                  </tr>
                ) : null}
                {data.rows.map((row, index) => (
                  <tr key={row.id}>
                    <td>{firstIndex + index + 1}</td>
                    <td>
                      <Link
                        href={`/track/${encodeURIComponent(row.trackingNumber)}`}
                        className="font-semibold text-green hover:underline"
                      >
                        {row.fileName}
                      </Link>
                    </td>
                    <td className="mono">{row.trackingNumber}</td>
                    <td>{row.createdAt ? formatNaive(row.createdAt, 'M j, Y') : '—'}</td>
                    <td>{row.documentType}</td>
                    <td>
                      <span className="pill p-green">{statusLabel(fromLoose(row.status))}</span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {data.lastPage > 1 ? (
            <nav className="st-pager foot" aria-label="History pages">
              <span className="st-pager-info">
                {firstIndex + 1}–{Math.min(firstIndex + PER_PAGE, data.total)} of {data.total}
              </span>
              {pageList(data.page, data.lastPage).map((entry, index) =>
                entry === 'gap' ? (
                  <span key={`gap-${index}`} className="st-page" aria-hidden="true">
                    …
                  </span>
                ) : (
                  <Link
                    key={entry}
                    href={routes.history({ ...active, page: String(entry) })}
                    className={`st-page${entry === data.page ? ' on' : ''}`}
                    aria-current={entry === data.page ? 'page' : undefined}
                  >
                    {entry}
                  </Link>
                ),
              )}
            </nav>
          ) : null}
        </div>
      </div>
    </AuthenticatedPage>
  );
}
