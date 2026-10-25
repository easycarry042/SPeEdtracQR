'use client';

import { useEffect, useRef, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { routes } from '@/lib/routes';
import { ScanDialog } from './scan-dialog';
import { DutyStatusToggle } from './duty-status-toggle';
import { logout } from '@/app/(auth)/login/actions';

export type HeaderUser = {
  id: number;
  name: string;
  initials: string;
  roleLabel: string | null;
  departmentName: string | null;
  avatarUrl: string | null;
  canFileInternal: boolean;
  canScan: boolean;
  isSuperAdmin: boolean;
  isAvailable: boolean;
};

export type BellNotification = {
  id: string;
  title: string;
  body: string | null;
  time: string;
  read: boolean;
};

/**
 * The right-hand side of the page bar: file an internal request, scan a QR,
 * the notification bell, and the account menu.
 *
 * The account menu is where History, the Staff directory and Settings live —
 * the sidebar is for the work, not for the person using it.
 */
export function HeaderActions({
  user,
  notifications,
  unread,
}: {
  user: HeaderUser;
  notifications: BellNotification[];
  unread: number;
}) {
  const router = useRouter();
  const [open, setOpen] = useState<'bell' | 'account' | null>(null);
  const [scanning, setScanning] = useState(false);
  const container = useRef<HTMLDivElement>(null);

  // Click-away and Escape, which Alpine's `@click.outside` gave the Blade menus.
  useEffect(() => {
    const onPointerDown = (event: PointerEvent): void => {
      if (!container.current?.contains(event.target as Node)) {
        setOpen(null);
      }
    };

    const onKey = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') {
        setOpen(null);
      }
    };

    document.addEventListener('pointerdown', onPointerDown);
    document.addEventListener('keydown', onKey);

    return () => {
      document.removeEventListener('pointerdown', onPointerDown);
      document.removeEventListener('keydown', onKey);
    };
  }, []);

  return (
    <div ref={container} className="flex items-center gap-3">
      {/* Daily Availability Toggle: On Duty / On Leave */}
      <div className="hidden sm:flex items-center">
        <DutyStatusToggle isAvailable={user.isAvailable} />
      </div>

      {user.canScan ? (
        <button type="button" className="header-action" onClick={() => setScanning(true)} aria-haspopup="dialog">
          <svg fill="none" stroke="currentColor" strokeWidth={1.9} viewBox="0 0 24 24" aria-hidden="true">
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              d="M4 8V6a2 2 0 0 1 2-2h2M16 4h2a2 2 0 0 1 2 2v2M20 16v2a2 2 0 0 1-2 2h-2M8 20H6a2 2 0 0 1-2-2v-2"
            />
            <path strokeLinecap="round" d="M7 12h10" />
          </svg>
          <span>Scan QR Code</span>
        </button>
      ) : null}

      {scanning ? <ScanDialog onClose={() => setScanning(false)} /> : null}

      {/* Notifications */}
      <div className="relative">
        <button
          type="button"
          className="header-disc"
          title="Notifications"
          aria-label={`Notifications${unread > 0 ? `, ${unread} unread` : ''}`}
          aria-expanded={open === 'bell'}
          onClick={() => setOpen(open === 'bell' ? null : 'bell')}
        >
          <svg fill="none" stroke="currentColor" strokeWidth={1.9} viewBox="0 0 24 24" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 16V11a6 6 0 1 1 12 0v5l1.5 2h-15L6 16Z" />
            <path strokeLinecap="round" d="M10 20.5a2.2 2.2 0 0 0 4 0" />
          </svg>
          {unread > 0 ? (
            <span className="header-badge" aria-hidden="true">
              {unread > 99 ? '99+' : unread}
            </span>
          ) : null}
        </button>

        {open === 'bell' ? (
          <div className="absolute right-0 z-50 mt-2 w-[320px] max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-hairline bg-paper shadow-lg">
            <div className="flex items-center justify-between border-b border-hairline px-4 py-2.5">
              <p className="text-xs font-bold uppercase tracking-wide text-ink-soft">Notifications</p>
              {unread > 0 ? (
                <button
                  type="button"
                  className="text-[11px] font-semibold text-green hover:underline"
                  onClick={async () => {
                    await fetch(routes.notificationsReadAll(), { method: 'POST' });
                    router.refresh();
                  }}
                >
                  Mark all as read
                </button>
              ) : null}
            </div>

            <div className="max-h-[360px] overflow-y-auto">
              {notifications.length === 0 ? (
                <p className="px-4 py-6 text-center text-sm text-ink-soft">
                  You&apos;re all caught up — no new notifications.
                </p>
              ) : (
                notifications.map((notification) => (
                  <a
                    key={notification.id}
                    href={routes.notificationsOpen(notification.id)}
                    className={`block border-b border-hairline px-4 py-3 text-sm transition hover:bg-green-wash ${
                      notification.read ? 'opacity-70' : ''
                    }`}
                  >
                    <div className="flex items-baseline justify-between gap-2">
                      <p className="font-semibold text-ink">{notification.title}</p>
                      <span className="shrink-0 text-[11px] text-ink-soft">{notification.time}</span>
                    </div>
                    {notification.body ? <p className="mt-0.5 line-clamp-2 text-xs text-ink-soft">{notification.body}</p> : null}
                  </a>
                ))
              )}
            </div>
          </div>
        ) : null}
      </div>

      {/* Account */}
      <div className="relative">
        <button
          type="button"
          className="header-chip"
          aria-label={`Account menu for ${user.name}`}
          aria-expanded={open === 'account'}
          onClick={() => setOpen(open === 'account' ? null : 'account')}
        >
          {user.avatarUrl ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={user.avatarUrl} alt="" className="header-avatar" />
          ) : (
            <span className="header-avatar" aria-hidden="true">
              {user.initials}
            </span>
          )}
          <span className="header-who hidden sm:block">
            <span className="header-who-name">{user.name}</span>
            <span className="header-who-meta">
              {[user.roleLabel, user.departmentName].filter(Boolean).join(' · ')}
            </span>
          </span>
          <svg className="header-caret" fill="none" stroke="currentColor" strokeWidth={2.2} viewBox="0 0 24 24" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" d="m6 9 6 6 6-6" />
          </svg>
        </button>

        {open === 'account' ? (
          <div className="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-xl border border-hairline bg-paper py-1 shadow-xl">
            <div className="border-b border-hairline px-4 py-3">
              <p className="truncate text-sm font-semibold text-ink">{user.name}</p>
              {user.roleLabel ? <p className="text-xs text-ink-soft">{user.roleLabel}</p> : null}
            </div>
            <div className="flex items-center justify-between border-b border-hairline px-4 py-2.5 bg-green-wash/40">
              <span className="text-xs font-semibold text-ink-soft">Shift status:</span>
              <DutyStatusToggle isAvailable={user.isAvailable} size="sm" />
            </div>
            <Link
              href={routes.staffProfile(user.id)}
              onClick={() => setOpen(null)}
              className="block px-4 py-2.5 text-sm font-medium text-ink transition hover:bg-green-wash"
            >
              My Profile
            </Link>
            <Link
              href={routes.history()}
              onClick={() => setOpen(null)}
              className="block px-4 py-2.5 text-sm font-medium text-ink transition hover:bg-green-wash"
            >
              History
            </Link>
            {!user.isSuperAdmin ? (
              <Link
                href={routes.staffIndex()}
                onClick={() => setOpen(null)}
                className="block px-4 py-2.5 text-sm font-medium text-ink transition hover:bg-green-wash"
              >
                Staff directory
              </Link>
            ) : null}
            <div className="my-1 border-t border-hairline" />
            <Link
              href={routes.profileEdit()}
              onClick={() => setOpen(null)}
              className="block px-4 py-2.5 text-sm font-medium text-ink transition hover:bg-green-wash"
            >
              Settings
            </Link>
            <form action={logout}>
              <button
                type="submit"
                className="w-full px-4 py-2.5 text-left text-sm font-semibold text-status-red transition hover:bg-status-red-wash"
              >
                Logout
              </button>
            </form>
          </div>
        ) : null}
      </div>
    </div>
  );
}
