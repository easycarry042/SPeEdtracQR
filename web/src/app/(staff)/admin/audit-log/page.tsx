import type { Metadata } from 'next';
import Link from 'next/link';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { requirePermission } from '@/lib/auth/actor';
import { auditPage, type AuditFilters, type AuditRow } from '@/lib/domain/audit-log';
import { pageList } from '@/components/review/page-list';
import { routes } from '@/lib/routes';
import { formatNaive } from '@/lib/time';

export const metadata: Metadata = { title: 'Audit Log' };

export const dynamic = 'force-dynamic';

/**
 * Port of `admin/audit-log/index.blade.php` — every create, edit, status change
 * and sign-in, for accountability. Rows are worded by ActivityLine (tracking
 * numbers, not "Document #19"), the same as the command center's strip.
 */
const EVENT_LABELS: Record<string, string> = { created: 'Created', updated: 'Edited', deleted: 'Deleted' };

function EventPill({ row }: { row: AuditRow }) {
  if (row.isAuth) {
    const login = row.description.toLowerCase().includes('logged in');

    return (
      <span
        className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ${login ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600'}`}
      >
        <span className={`h-1.5 w-1.5 rounded-full ${login ? 'bg-emerald-500' : 'bg-gray-400'}`} />
        {login ? 'Logged In' : 'Logged Out'}
      </span>
    );
  }

  return (
    <span className="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
      {EVENT_LABELS[row.event ?? ''] ?? 'Action'}
    </span>
  );
}

export default async function AuditLogPage({
  searchParams,
}: {
  searchParams: Promise<AuditFilters & { page?: string }>;
}) {
  await requirePermission('manage system');

  const query = await searchParams;
  const filters: AuditFilters = { user: query.user ?? '', log: query.log ?? '', date: query.date ?? '' };
  const data = await auditPage(Number(query.page ?? 1), filters);
  const filtered = Boolean(filters.user || filters.log || filters.date);
  const pageUrl = (page: number) =>
    routes.adminAuditLog({ ...Object.fromEntries(Object.entries(filters).filter(([, v]) => v)), page: String(page) });

  return (
    <AuthenticatedPage title="Audit Log">
      <div className="page-shell page-shell-loose">
        <form method="GET" className="flex flex-wrap gap-3">
          <label className="sr-only" htmlFor="audit-user">
            User
          </label>
          <select id="audit-user" name="user" defaultValue={filters.user} className="field h-ctl w-64">
            <option value="">All Users</option>
            {data.users.map((user) => (
              <option key={user.id} value={user.id}>
                {user.name}
              </option>
            ))}
          </select>
          <label className="sr-only" htmlFor="audit-log">
            Event kind
          </label>
          <select id="audit-log" name="log" defaultValue={filters.log} className="field h-ctl w-56">
            <option value="">All Events</option>
            <option value="auth">Auth (Login / Logout)</option>
            <option value="default">Document Changes</option>
          </select>
          <label className="sr-only" htmlFor="audit-date">
            Day
          </label>
          <input id="audit-date" type="date" name="date" defaultValue={filters.date} className="field h-ctl" />
          <button type="submit" className="h-ctl rounded-xl bg-gray-800 px-4 text-sm font-semibold text-white hover:bg-gray-900">
            Filter
          </button>
          {filtered ? (
            <Link
              href={routes.adminAuditLog()}
              className="inline-flex h-ctl items-center rounded-xl border border-gray-200 bg-white px-4 text-sm font-semibold text-gray-600 hover:bg-gray-50"
            >
              Clear
            </Link>
          ) : null}
        </form>

        <div className="panel">
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  {['Date & Time', 'User', 'Event', 'Details', 'IP Address'].map((heading) => (
                    <th key={heading} className="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-500">
                      {heading}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 bg-white">
                {data.rows.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="px-4 py-10 text-center">
                      <p className="font-semibold text-ink">No activity logged yet</p>
                      <p className="mt-1 text-sm text-ink-soft">
                        Every create, edit, scan, and status change is recorded here for accountability.
                      </p>
                    </td>
                  </tr>
                ) : null}
                {data.rows.map((row) => (
                  <tr key={row.id} className="transition hover:bg-gray-50/60">
                    <td className="whitespace-nowrap px-4 py-3.5 text-sm text-gray-700">
                      {row.createdAt ? (
                        <>
                          <div>{formatNaive(row.createdAt, 'M d, Y')}</div>
                          <div className="text-xs text-gray-400">{formatNaive(row.createdAt, 'h:i:s A')}</div>
                        </>
                      ) : (
                        '—'
                      )}
                    </td>
                    <td className="px-4 py-3.5">
                      {row.causerName ? (
                        <>
                          <div className="text-sm font-medium text-gray-800">{row.causerName}</div>
                          <div className="text-xs text-gray-400">{row.causerEmail}</div>
                        </>
                      ) : (
                        <span className="text-sm text-gray-400">System</span>
                      )}
                    </td>
                    <td className="px-4 py-3.5">
                      <EventPill row={row} />
                    </td>
                    <td className="px-4 py-3.5 text-sm text-gray-600">
                      {row.subject ? <span className="mr-1 font-mono text-xs text-gray-500">{row.subject}</span> : null}
                      {row.action}
                      {row.detail ? <span className="font-medium text-gray-800"> {row.detail}</span> : null}
                    </td>
                    <td className="px-4 py-3.5 font-mono text-xs text-gray-500">{row.ip ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {data.lastPage > 1 ? (
            <nav className="st-pager border-t border-gray-100 px-4 py-3" aria-label="Audit log pages">
              <span className="st-pager-info">
                Page {data.page} of {data.lastPage} · {data.total} entries
              </span>
              {pageList(data.page, data.lastPage).map((entry, index) =>
                entry === 'gap' ? (
                  <span key={`gap-${index}`} className="st-page" aria-hidden="true">
                    …
                  </span>
                ) : (
                  <Link
                    key={entry}
                    href={pageUrl(entry)}
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
