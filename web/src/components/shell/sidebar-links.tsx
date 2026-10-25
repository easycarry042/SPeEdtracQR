'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { Badges } from '@/lib/domain/nav-badges';
import { routes } from '@/lib/routes';
import { NavBadge } from './nav-badge';

/**
 * Sidebar navigation, shared by every signed-in role. Super admins get the
 * configuration set; everyone else gets the operational set.
 *
 * The active row takes a lighter panel plus a brass marker flush to the
 * sidebar's edge (`.sb-row.is-active`), so the current page reads at a glance
 * and never by colour alone, and is named to screen readers with `aria-current`.
 */
const GLYPH = 'sb-glyph';
const LABEL = 'nav-text whitespace-nowrap';

export type NavUser = {
  id: number;
  isSuperAdmin: boolean;
  isSupervisor: boolean;
  /** Action Mamamayan's Clerk: internal requests only, so no citizen Dashboard. */
  isClerk: boolean;
  canManageUsers: boolean;
  canManageSystem: boolean;
  canManageBookings: boolean;
  canManageInventory: boolean;
  canViewReports: boolean;
  canTouchInternal: boolean;
};

function Row({
  href,
  active,
  children,
}: {
  href: string;
  active: boolean;
  children: React.ReactNode;
}) {
  return (
    <Link
      href={href}
      className={`nav-link sb-row${active ? ' is-active' : ''}`}
      aria-current={active ? 'page' : undefined}
    >
      {children}
    </Link>
  );
}

export function SidebarLinks({ user, badges }: { user: NavUser; badges: Badges }) {
  const pathname = usePathname();
  const on = (...prefixes: string[]): boolean =>
    prefixes.some((prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`));

  if (user.isSuperAdmin) {
    return (
      <>
        <Row href={routes.adminDashboard()} active={on('/admin/dashboard')}>
          <span className={GLYPH}>
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src="/images/staff/nav-home.svg" alt="" className="sb-icon" />
          </span>
          <span className={LABEL}>Dashboard</span>
        </Row>

        {/* Every citizen request and where it stands. Analytics is folded into
            the command-center Dashboard; History lives in the account menu; the
            Staff directory was folded into Users. */}
        <Row href={routes.adminRequestsIndex()} active={on('/admin/requests')}>
          <span className={GLYPH}>
            <svg className="sb-icon" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M9 12h6M9 16h4"
              />
            </svg>
          </span>
          <span className={LABEL}>Requests</span>
        </Row>

        {user.canManageUsers ? (
          <Row href={routes.adminUsersIndex()} active={on('/admin/users')}>
            <span className={GLYPH}>
              <svg className="sb-icon" fill="currentColor" viewBox="0 0 24 24">
                <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z" />
              </svg>
            </span>
            <span className={LABEL}>Users</span>
          </Row>
        ) : null}

        {user.canManageSystem ? (
          <>
            <Row href={routes.requestsIndex()} active={on('/requests')}>
              <span className={GLYPH}>
                <svg className="sb-icon" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M8 7h12m0 0-4-4m4 4-4 4M16 17H4m0 0 4 4m-4-4 4-4" />
                </svg>
              </span>
              <span className={LABEL}>Internal Requests</span>
            </Row>

            {/* The same Resources page staff and supervisors run, plus the
                catalog controls only a super admin has. */}
            <Row href={routes.inventoryIndex()} active={on('/inventory', '/admin/resources')}>
              <span className={GLYPH}>
                <svg className="sb-icon" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
              </span>
              <span className={LABEL}>Resources</span>
            </Row>

            <Row href={routes.adminAuditLog()} active={on('/admin/audit-log')}>
              <span className={GLYPH}>
                <svg className="sb-icon" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M9 12h6M9 16h6M7 4H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V6a2 2 0 00-2-2h-2M9 4a2 2 0 002 2h2a2 2 0 002-2M9 4a2 2 0 012-2h2a2 2 0 012 2"
                  />
                </svg>
              </span>
              <span className={LABEL}>Audit Log</span>
            </Row>

            {/* Configuration, visited when something is being set up rather
                than every day — grouped under its own heading. */}
            <p className="sb-section sb-subsection">Setup</p>

            <Row href={routes.adminDepartmentsIndex()} active={on('/admin/departments')}>
              <span className={GLYPH}>
                <svg className="sb-icon" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M3 21h18M4 21V7l8-4 8 4v14M9 9h1m4 0h1M9 13h1m4 0h1M9 17h1m4 0h1" />
                </svg>
              </span>
              <span className={LABEL}>Departments</span>
            </Row>

            <Row href={routes.adminRouteTemplatesIndex()} active={on('/admin/route-templates')}>
              <span className={GLYPH}>
                <svg className="sb-icon" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M7 5a2 2 0 11-4 0 2 2 0 014 0zm14 14a2 2 0 11-4 0 2 2 0 014 0zM7 5h9a3 3 0 013 3v1m-2 10H8a3 3 0 01-3-3v-1m0-4v2m14-6v2"
                  />
                </svg>
              </span>
              <span className={LABEL}>Route Templates</span>
            </Row>

            <Row href={routes.adminRequestTypesIndex()} active={on('/admin/request-types')}>
              <span className={GLYPH}>
                <svg className="sb-icon" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
                  />
                </svg>
              </span>
              <span className={LABEL}>Request Types</span>
            </Row>
          </>
        ) : null}
      </>
    );
  }

  const homeRoute = user.isSupervisor ? routes.dashboard() : routes.staffDashboard();
  const homeActive = user.isSupervisor ? on('/dashboard') : on('/my-dashboard');

  return (
    <>
      {!user.isClerk ? (
        <Row href={homeRoute} active={homeActive}>
          <span className={GLYPH}>
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src="/images/staff/nav-home.svg" alt="" className="sb-icon" />
          </span>
          <span className={LABEL}>Dashboard</span>
          <NavBadge name="home" initial={badges.home} what="new" />
        </Row>
      ) : null}

      {/* No "Look Up" row: the desk it opened was a second copy of this role's
          own dashboard queue. The QR hub it also served is still a click away
          on the header's Scan QR Code button. */}

      {user.canTouchInternal ? (
        <Row href={routes.requestsIndex()} active={on('/requests')}>
          <span className={GLYPH}>
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src="/images/staff/nav-internal.svg" alt="" className="sb-icon" />
          </span>
          <span className={LABEL}>Internal</span>
          <NavBadge name="internal" initial={badges.internal} what="waiting" />
        </Row>
      ) : null}

      {user.canManageBookings ? (
        <Row href={routes.bookingsIndex()} active={on('/bookings')}>
          <span className={GLYPH}>
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src="/images/staff/nav-calendar.svg" alt="" className="sb-icon" />
          </span>
          <span className={LABEL}>Booking</span>
        </Row>
      ) : null}

      {/* Resources, next to Booking: the calendar says when a thing is taken,
          this says how much of it there is. */}
      {user.canManageInventory ? (
        <Row href={routes.inventoryIndex()} active={on('/inventory')}>
          <span className={GLYPH}>
            <svg className="sb-icon" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
          </span>
          <span className={LABEL}>Resources</span>
          <NavBadge name="resources" initial={badges.resources} what="waiting" />
        </Row>
      ) : null}

      {/* One Reports row: the office's numbers on the command-center page.
          It replaced Analytics and the Services report, whose figures
          disagreed with the dashboard. */}
      {user.canViewReports ? (
        <Row href={routes.reportsIndex()} active={on('/reports', '/analytics')}>
          <span className={GLYPH}>
            <svg className="sb-icon" fill="currentColor" viewBox="0 0 24 24">
              <rect x="3" y="11" width="4" height="10" rx="1" />
              <rect x="10" y="6" width="4" height="15" rx="1" />
              <rect x="17" y="3" width="4" height="18" rx="1" />
            </svg>
          </span>
          <span className={LABEL}>Reports</span>
        </Row>
      ) : null}
    </>
  );
}
