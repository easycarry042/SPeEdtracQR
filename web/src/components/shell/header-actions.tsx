'use client';

import { useEffect, useRef, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { routes } from '@/lib/routes';
import { ScanDialog } from './scan-dialog';
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
      {user.canFileInternal ? (
        <Link href={routes.requestsCreate()} className="header-action" title="File an internal request">
          <svg fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" d="M8 7h12m0 0-4-4m4 4-4 4M16 17H4m0 0 4 4m-4-4 4-4" />
          </svg>
          <span className="hidden lg:inline">File a request</span>
        </Link>
      ) : null}

      {user.canScan ? (
        <button type="button" className="header-action" onClick={() => setScanning(true)} aria-haspopup="dialog">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src="/images/staff/icon-scan.svg" alt="" aria-hidden="true" />
          <span className="hidden lg:inline">Scan QR Code</span>
        </button>
      ) : null}

      {scanning ? <ScanDialog onClose={() => setScanning(false)} /> : null}

      {/* Notifications */}
      <div className="relative">
        <button
          type="button"
          className="header-disc"
          aria-label={`Notifications${unread > 0 ? `, ${unread} unread` : ''}`}
          aria-expanded={open === 'bell'}
          onClick={() => setOpen(open === 'bell' ? null : 'bell')}
        >
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src="/images/staff/icon-bell.svg" alt="" aria-hidden="true" />
          {unread > 0 ? (
            <span className="header-badge" aria-hidden="true">
              {unread > 99 ? '99+' : unread}
            </span>
          ) : null}
        </button>

        {open === 'bell' ? (
          <div className="absolute right-0 z-50 mt-2 w-[320px] overflow-hidden rounded-xl border border-hairline bg-paper shadow-lg">
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
                <p className="px-4 py-6 text-center text-sm text-ink-soft">Nothing new.</p>
              ) : (
                notifications.map((notification) => (
                  <a
                    key={notification.id}
                    href={routes.notificationsOpen(notification.id)}
                    className={`block border-b border-hairline px-4 py-3 text-sm transition hover:bg-green-wash ${
                      notification.read ? 'opacity-70' : ''
                    }`}
                  >
                    <p className="font-semibold text-ink">{notification.title}</p>
                    {notification.body ? <p className="mt-0.5 text-ink-soft">{notification.body}</p> : null}
                    <p className="mt-1 text-[11px] text-ink-soft">{notification.time}</p>
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
            <span className="header-avatar avatar" aria-hidden="true">
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
          <div className="absolute right-0 z-50 mt-2 w-[220px] overflow-hidden rounded-xl border border-hairline bg-paper py-1 shadow-lg">
            <Link href={routes.staffProfile(user.id)} className="block px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-200/80">
              My Profile
            </Link>
            <Link href={routes.history()} className="block px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-200/80">
              History
            </Link>
            {user.isSuperAdmin ? (
              <Link href={routes.staffIndex()} className="block px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-200/80">
                Staff directory
              </Link>
            ) : null}
            <Link href={routes.profileEdit()} className="block px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-200/80">
              Settings
            </Link>
            <form action={logout}>
              <button type="submit" className="w-full px-4 py-2.5 text-left text-sm font-semibold text-red-600 transition hover:bg-red-50">
                Logout
              </button>
            </form>
          </div>
        ) : null}
      </div>
    </div>
  );
}
