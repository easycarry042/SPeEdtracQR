'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useEffect, useRef, useState, useTransition } from 'react';
import type { UserListing, UserRow } from '@/lib/domain/admin-users';
import type { BulkAction } from '@/lib/domain/admin-users';
import { DutyStatusToggle } from '@/components/shell/duty-status-toggle';
import { routes } from '@/lib/routes';
import { useConfirm, type ConfirmOptions } from '@/components/confirm-dialog';
import {
  archiveUserAccount,
  bulkUserAction,
  deleteUserAccount,
  restoreUserAccount,
  toggleUserActive,
} from '@/app/actions/admin';

/**
 * The accounts table: filters, the bulk bar, and each row's own actions.
 *
 * Filtering navigates rather than filtering in the browser, because the list is
 * paged on the server — a browser-side filter would search the twenty rows on
 * this page and quietly miss everyone else. Typing is debounced and replaces
 * the history entry, so the back button still leaves the page instead of
 * walking back through every keystroke.
 *
 * Archive and Delete confirm in the row itself. The Blade page used
 * `confirm()`, which cannot be styled, cannot be read by the live region, and
 * is suppressed outright in some embedded browsers — a destructive action must
 * not depend on a dialog that may never appear.
 */
type Pending = { id: number; action: 'archive' | 'delete' } | null;

const ROLE_LABELS: Record<string, string> = {
  staff: 'Staff',
  Supervisor: 'Supervisor',
  super_admin: 'Super Admin',
  receiving_staff: 'Receiving Staff',
  department_admin: 'Department Admin',
};

const roleLabel = (role: string): string =>
  ROLE_LABELS[role] ??
  role
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');

export function UserDirectory({
  listing,
  search,
  role,
  viewerId,
  flash,
}: {
  listing: UserListing;
  search: string;
  role: string;
  viewerId: number;
  flash: string | null;
}) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [term, setTerm] = useState(search);
  const [selected, setSelected] = useState<number[]>([]);
  const [confirming, setConfirming] = useState<Pending>(null);
  const [notice, setNotice] = useState<{ ok: boolean; message: string } | null>(
    flash ? { ok: true, message: flash } : null,
  );
  const typed = useRef(false);

  const go = (params: { search?: string; role?: string; page?: number }): void => {
    const next = {
      search: params.search ?? term,
      role: params.role ?? role,
      page: params.page ?? 1,
    };

    router.replace(
      routes.adminUsersIndex({
        search: next.search === '' ? undefined : next.search,
        role: next.role === '' ? undefined : next.role,
        page: next.page > 1 ? next.page : undefined,
      }),
    );
  };

  // Debounced search, the live-search behaviour the Blade page had.
  useEffect(() => {
    if (!typed.current || term === search) {
      return;
    }

    const timer = setTimeout(() => go({ search: term }), 300);

    return () => clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [term]);

  const confirm = useConfirm();

  const run = async (
    action: () => Promise<{ ok: boolean; message: string }>,
    ask?: ConfirmOptions,
  ): Promise<void> => {
    setConfirming(null);

    if (ask && !(await confirm(ask))) {
      return;
    }

    startTransition(async () => {
      const result = await action();
      setNotice(result);

      if (result.ok) {
        setSelected([]);
        router.refresh();
      }
    });
  };

  /** Archived rows and the viewer's own account are never bulk-selectable. */
  const selectable = listing.rows.filter((row) => !row.isArchived && row.id !== viewerId);
  const allSelected = selectable.length > 0 && selected.length === selectable.length;

  const toggleOne = (userId: number): void =>
    setSelected((current) =>
      current.includes(userId)
        ? current.filter((value) => value !== userId)
        : [...current, userId],
    );

  const filtered = search !== '' || role !== '';

  return (
    <>
      {/* ── Filters ────────────────────────────────────────────────────────── */}
      <div className="st-toolbar">
        <div className="st-search">
          <label className="sr-only" htmlFor="userSearch">
            Search name or email
          </label>
          <input
            id="userSearch"
            type="search"
            className="field"
            value={term}
            placeholder="Search name or email…"
            autoComplete="off"
            onChange={(event) => {
              typed.current = true;
              setTerm(event.target.value);
            }}
          />
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <label className="sr-only" htmlFor="userRole">
            Filter by role
          </label>
          <select
            id="userRole"
            className="field"
            value={role}
            onChange={(event) => go({ role: event.target.value })}
          >
            <option value="">All roles</option>
            {listing.roles.map((name) => (
              <option key={name} value={name}>
                {roleLabel(name)}
              </option>
            ))}
          </select>

          {filtered ? (
            <Link href={routes.adminUsersIndex()} className="cr-btn cr-btn-sm">
              Clear
            </Link>
          ) : null}
        </div>
      </div>

      {notice ? (
        <p className={notice.ok ? 'callout p-green mt-4' : 'callout p-amber mt-4'} role="status">
          {notice.message}
        </p>
      ) : null}

      {/* ── Bulk bar ───────────────────────────────────────────────────────── */}
      {selected.length > 0 ? (
        <div className="dm-panel-strong mt-4 flex flex-wrap items-center justify-between gap-3">
          <p className="text-[length:var(--fs-small)] font-semibold text-green-deep">
            {selected.length} selected
          </p>

          <div className="gate-actions">
            {(
              [
                ['activate', 'Activate'],
                ['deactivate', 'Deactivate'],
                ['archive', 'Archive'],
              ] as Array<[BulkAction, string]>
            ).map(([action, label]) => (
              <button
                key={action}
                type="button"
                className="cr-btn cr-btn-sm"
                disabled={pending}
                onClick={() =>
                  void run(() => bulkUserAction(action, selected), {
                    title: `${label} ${selected.length} account${selected.length === 1 ? '' : 's'}?`,
                    confirmLabel: label,
                    tone: action === 'activate' ? 'default' : 'danger',
                  })
                }
              >
                {label}
              </button>
            ))}
            <button
              type="button"
              className="cr-btn cr-btn-ghost cr-btn-sm"
              onClick={() => setSelected([])}
            >
              Clear
            </button>
          </div>
        </div>
      ) : null}

      {/* ── Table ──────────────────────────────────────────────────────────── */}
      <section className="panel mt-4" aria-busy={pending}>
        <div className="table-wrap">
          <table className="reg">
            <thead>
              <tr>
                <th scope="col" className="w-10">
                  <input
                    type="checkbox"
                    aria-label="Select every account on this page"
                    checked={allSelected}
                    disabled={selectable.length === 0}
                    onChange={(event) =>
                      setSelected(event.target.checked ? selectable.map((row) => row.id) : [])
                    }
                  />
                </th>
                <th scope="col">Name</th>
                <th scope="col">Office</th>
                <th scope="col">Role</th>
                {/* From the old staff directory: what each person is carrying. */}
                <th scope="col">Workload</th>
                <th scope="col">Status</th>
                <th scope="col">Duty</th>
                <th scope="col" />
              </tr>
            </thead>
            <tbody>
              {listing.rows.length === 0 ? (
                <tr>
                  <td colSpan={8} className="py-10 text-center">
                    <p className="cr-empty-title">
                      {filtered ? 'No users match your filters' : 'No staff accounts yet'}
                    </p>
                    <p className="cr-empty-msg">
                      {filtered
                        ? 'Try a different name, email or role — or clear the filters to see everyone.'
                        : 'Add the office’s staff so requests can be assigned to them.'}
                    </p>
                  </td>
                </tr>
              ) : (
                listing.rows.map((row) => (
                  <tr key={row.id} className={row.isArchived || !row.isActive ? 'opacity-60' : undefined}>
                    <td>
                      {row.isArchived || row.id === viewerId ? null : (
                        <input
                          type="checkbox"
                          aria-label={`Select ${row.name}`}
                          checked={selected.includes(row.id)}
                          onChange={() => toggleOne(row.id)}
                        />
                      )}
                    </td>

                    <td>
                      <div className="flex items-center gap-3">
                        <span className="avatar" aria-hidden="true">
                          {row.name.slice(0, 1).toUpperCase()}
                        </span>
                        <div className="min-w-0">
                          <Link href={routes.staffProfile(row.id)} className="cr-link">
                            {row.name}
                          </Link>
                          <div className="dir-sub truncate">{row.email}</div>
                        </div>
                      </div>
                    </td>

                    <td className="muted">{row.office ?? '—'}</td>

                    <td>{row.role ? <span className="chip">{roleLabel(row.role)}</span> : null}</td>

                    <td>
                      <Workload load={row.load} />
                    </td>

                    <td>
                      <span
                        className={
                          row.isArchived ? 'pill p-amber' : row.isActive ? 'pill p-green' : 'pill p-muted'
                        }
                      >
                        {row.isArchived ? 'Archived' : row.isActive ? 'Active' : 'Inactive'}
                      </span>
                    </td>

                    <td>
                      {row.isArchived ? (
                        <span className="muted">—</span>
                      ) : (
                        <DutyStatusToggle
                          userId={row.id}
                          isAvailable={row.isAvailable}
                          size="sm"
                        />
                      )}
                    </td>

                    <td>
                      <RowActions
                        row={row}
                        isSelf={row.id === viewerId}
                        pending={pending}
                        confirming={confirming}
                        onConfirm={setConfirming}
                        onRun={run}
                      />
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {listing.pages > 1 ? (
          <nav className="st-pager" aria-label="Account pages">
            <span className="st-pager-info">
              Page {listing.page} of {listing.pages} · {listing.total}{' '}
              {listing.total === 1 ? 'account' : 'accounts'}
            </span>

            <button
              type="button"
              className="st-page-step"
              disabled={listing.page === 1}
              onClick={() => go({ page: listing.page - 1 })}
              aria-label="Previous page"
            >
              ‹
            </button>
            <button
              type="button"
              className="st-page-step"
              disabled={listing.page >= listing.pages}
              onClick={() => go({ page: listing.page + 1 })}
              aria-label="Next page"
            >
              ›
            </button>
          </nav>
        ) : null}
      </section>
    </>
  );
}

/** Open / done / overdue, and when they were last at the desk. */
function Workload({ load }: { load: UserRow['load'] }) {
  if (!load) {
    return null;
  }

  return (
    <>
      <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-[length:var(--fs-small)]">
        <span>
          <b>{load.assigned}</b> open
        </span>
        <span className="text-ink-soft">
          <b className="text-ink">{load.completed}</b> done
        </span>
        {load.overdue > 0 ? <span className="cc-tag cc-tag-late">{load.overdue} overdue</span> : null}
      </div>
      <div className="dir-sub">
        {load.active_now
          ? 'Active now'
          : load.last_active
            ? `Active ${load.last_active}`
            : 'No activity yet'}
      </div>
    </>
  );
}

/**
 * One row's actions. An admin cannot deactivate, archive or delete their own
 * account — the rule lives in the domain too, this only hides the buttons so
 * nobody is invited to try.
 */
function RowActions({
  row,
  isSelf,
  pending,
  confirming,
  onConfirm,
  onRun,
}: {
  row: UserRow;
  isSelf: boolean;
  pending: boolean;
  confirming: Pending;
  onConfirm: (pending: Pending) => void;
  onRun: (action: () => Promise<{ ok: boolean; message: string }>, ask?: ConfirmOptions) => void;
}) {
  const asking = confirming?.id === row.id ? confirming.action : null;

  if (asking) {
    return (
      <div className="flex flex-wrap items-center justify-end gap-2">
        <span className="text-[length:var(--fs-caption)] text-ink-soft">
          {asking === 'archive'
            ? `Archive ${row.name}? They cannot sign in until restored.`
            : `Delete ${row.name} permanently? This cannot be undone.`}
        </span>
        <button
          type="button"
          className="cr-btn cr-btn-sm cr-btn-danger"
          disabled={pending}
          onClick={() =>
            onRun(() => (asking === 'archive' ? archiveUserAccount(row.id) : deleteUserAccount(row.id)))
          }
        >
          {asking === 'archive' ? 'Archive' : 'Delete'}
        </button>
        <button type="button" className="cr-btn cr-btn-sm" onClick={() => onConfirm(null)}>
          Keep
        </button>
      </div>
    );
  }

  return (
    <div className="flex flex-wrap items-center justify-end gap-2">
      {row.isArchived ? (
        <>
          <button
            type="button"
            className="cr-btn cr-btn-sm"
            disabled={pending}
            onClick={() =>
              onRun(() => restoreUserAccount(row.id), {
                title: `Restore ${row.name}'s account?`,
                confirmLabel: 'Restore',
              })
            }
          >
            Restore
          </button>
          <button
            type="button"
            className="cr-btn cr-btn-sm"
            disabled={pending}
            onClick={() => onConfirm({ id: row.id, action: 'delete' })}
          >
            Delete
          </button>
        </>
      ) : (
        <>
          <Link href={routes.adminUsersEdit(row.id)} className="cr-btn cr-btn-sm">
            Edit
          </Link>

          {isSelf ? null : (
            <>
              <button
                type="button"
                className="cr-btn cr-btn-sm"
                disabled={pending}
                onClick={() =>
                  onRun(
                    () => toggleUserActive(row.id),
                    row.isActive
                      ? {
                          title: `Deactivate ${row.name}?`,
                          message: 'They cannot sign in until the account is activated again.',
                          confirmLabel: 'Deactivate',
                          tone: 'danger',
                        }
                      : { title: `Activate ${row.name}?`, confirmLabel: 'Activate' },
                  )
                }
              >
                {row.isActive ? 'Deactivate' : 'Activate'}
              </button>
              <button
                type="button"
                className="cr-btn cr-btn-sm"
                disabled={pending}
                onClick={() => onConfirm({ id: row.id, action: 'archive' })}
              >
                Archive
              </button>
              <button
                type="button"
                className="cr-btn cr-btn-sm"
                disabled={pending}
                onClick={() => onConfirm({ id: row.id, action: 'delete' })}
              >
                Delete
              </button>
            </>
          )}
        </>
      )}
    </div>
  );
}
