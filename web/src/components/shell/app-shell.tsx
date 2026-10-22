'use client';

import { useState } from 'react';
import Link from 'next/link';
import { app } from '@/lib/config';
import type { Badges } from '@/lib/domain/nav-badges';
import { SidebarLinks, type NavUser } from './sidebar-links';
import { HeaderActions, type BellNotification, type HeaderUser } from './header-actions';

/**
 * One shell for every signed-in role: a pinned sidebar whose link set is chosen
 * by role, and a page bar carrying the title and the account actions.
 *
 * `fixedHeight` is for pages that fill the viewport and never scroll (their own
 * panels scroll internally) — the lock has to reach the root element, which is
 * why it is a class on the outer wrapper rather than on `<main>`.
 */
export function AppShell({
  title,
  navUser,
  headerUser,
  badges,
  notifications,
  unread,
  pageActions,
  header,
  fixedHeight = false,
  children,
}: {
  title: string;
  navUser: NavUser;
  headerUser: HeaderUser;
  badges: Badges;
  notifications: BellNotification[];
  unread: number;
  pageActions?: React.ReactNode;
  header?: React.ReactNode;
  fixedHeight?: boolean;
  children: React.ReactNode;
}) {
  const [mobileNav, setMobileNav] = useState(false);
  const homeRoute = navUser.isSuperAdmin
    ? '/admin/dashboard'
    : navUser.isSupervisor
      ? '/dashboard'
      : '/my-dashboard';

  return (
    <div
      className={`admin-shell flex min-h-screen ${mobileNav ? 'mobile-nav-open' : ''} ${
        fixedHeight ? 'app-fixed-height' : ''
      }`}
    >
      {mobileNav ? (
        <div className="sidebar-backdrop lg:hidden" onClick={() => setMobileNav(false)} />
      ) : null}

      <aside
        onClick={(event) => {
          if ((event.target as HTMLElement).closest('a')) {
            setMobileNav(false);
          }
        }}
        className="sidebar-pinned sticky top-0 z-40 flex h-screen w-[232px] shrink-0 flex-col overflow-hidden nav-bar xl:w-[297px]"
      >
        {/* Brand: the white mark on deep green, linking home. Brand, section
            label, nav icons and the Accessibility row share one left edge. */}
        <Link href={homeRoute} className="sb-brand nav-brand" aria-label={`${app.name} home`}>
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src="/images/staff/logo-white.png" alt="" className="sb-brand-mark" />
          <span className="nav-text truncate whitespace-nowrap tracking-tight text-white">
            <span className="font-display font-black text-[#f9fff9]">SPeED</span>{' '}
            <span className="font-sans font-medium">TraQR</span>
          </span>
        </Link>

        <p className="sb-section" id="sb-menu-label">
          Menu
        </p>

        <nav className="nav-scroll sb-nav" aria-labelledby="sb-menu-label">
          <SidebarLinks user={navUser} badges={badges} />
        </nav>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        {/* No rule under the bar: it fades from solid white into the page,
            which is what separates it from what scrolls beneath. */}
        <header className="sticky top-0 z-30 bg-gradient-to-b from-white via-white/85 to-transparent pb-6 pt-3">
          <div className="app-frame flex items-center justify-between gap-3">
            <div className="flex min-w-0 items-center gap-3">
              <button
                type="button"
                onClick={() => setMobileNav((open) => !open)}
                className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-green-deep transition hover:bg-green-wash focus:outline-none focus-visible:ring-2 focus-visible:ring-green lg:hidden"
                aria-label="Open navigation"
              >
                <svg className="h-6 w-6" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
              </button>

              {/* City and Tourism Office seals lead the page bar; they are
                  branding, so the title carries the alt text. */}
              <span className="hidden shrink-0 items-center gap-[5px] sm:flex">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src="/images/staff/seal-1.png" alt="" className="h-[40px] w-[40px] object-contain" />
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src="/images/staff/seal-2.png" alt="" className="h-[42.5px] w-[42.5px] object-contain" />
              </span>
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img
                src="/images/staff/title-divider.svg"
                alt=""
                aria-hidden="true"
                className="hidden h-[40px] w-[5px] shrink-0 sm:block"
              />

              {/* No role badge here: the identity chip on the right already
                  names the role and the desk. */}
              <h1 className="layout-title" tabIndex={-1}>
                {title}
              </h1>
            </div>

            <div className="flex items-center gap-3">
              <div className="flex min-w-0 shrink items-center gap-3 empty:hidden">{pageActions}</div>
              <HeaderActions user={headerUser} notifications={notifications} unread={unread} />
            </div>
          </div>
        </header>

        {header ? (
          <div className="border-b border-transparent">
            <div className="app-frame pb-2 pt-4">{header}</div>
          </div>
        ) : null}

        <main id="main" className={`flex-1 pb-10 pt-2 ${fixedHeight ? 'main-fixed' : ''}`}>
          <div className="app-frame">{children}</div>
        </main>

        {/* No site footer inside the signed-in shell. The footer is wayfinding
            for visitors; on a work queue it is only padding plus a dark plate
            between the user and the bottom of their list. */}
      </div>
    </div>
  );
}
