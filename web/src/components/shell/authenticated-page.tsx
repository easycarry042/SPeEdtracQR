import { requireActor, touchLastActive, type Actor } from '@/lib/auth/actor';
import { badgesFor } from '@/lib/domain/nav-badges';
import { bellFor, unreadCountFor } from '@/lib/domain/notifications';
import { routes } from '@/lib/routes';
import { AppShell } from './app-shell';
import type { NavUser } from './sidebar-links';
import type { HeaderUser } from './header-actions';

/**
 * Server wrapper around `AppShell`: loads the signed-in user, their sidebar
 * counts and their bell, then renders the page inside the shell.
 *
 * Every authenticated page goes through this rather than composing the shell
 * itself, so the nav, the badges and the account menu cannot drift apart
 * between pages.
 */
function initialsOf(name: string): string {
  const parts = name.trim().split(/\s+/);

  return (
    parts.length >= 2 ? `${parts[0][0]}${parts[parts.length - 1][0]}` : name.slice(0, 2)
  ).toUpperCase();
}

function roleLabelOf(actor: Actor): string | null {
  const role = actor.roles[0];

  if (!role) {
    return null;
  }

  // "super_admin" → "Super Admin"
  return role
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

export async function AuthenticatedPage({
  title,
  pageActions,
  header,
  fixedHeight,
  children,
}: {
  title: string;
  pageActions?: React.ReactNode;
  header?: React.ReactNode;
  fixedHeight?: boolean;
  children: React.ReactNode;
}) {
  const actor = await requireActor();

  // Drives the "Active now" marker on the staff directory and profile.
  await touchLastActive(actor.id);

  const [badges, notifications, unread] = await Promise.all([
    badgesFor(actor),
    bellFor(actor.id),
    unreadCountFor(actor.id),
  ]);

  const navUser: NavUser = {
    id: actor.id,
    isSuperAdmin: actor.isSuperAdmin(),
    isSupervisor: actor.isSupervisor(),
    isClerk: actor.isClerk(),
    canManageUsers: actor.can('manage users'),
    canManageSystem: actor.can('manage system'),
    canManageBookings: actor.can('manage bookings'),
    canManageInventory: actor.can('manage inventory'),
    canViewReports: actor.can('view reports'),
    canTouchInternal:
      actor.can('act on internal requests') || actor.can('create internal requests'),
  };

  const headerUser: HeaderUser = {
    id: actor.id,
    name: actor.name,
    initials: initialsOf(actor.name),
    roleLabel: roleLabelOf(actor),
    departmentName: actor.departmentName,
    avatarUrl: actor.avatarPath ? routes.staffAvatar(actor.id) : null,
    canFileInternal: actor.can('create internal requests'),
    canScan: actor.can('scan documents') || actor.can('act on internal requests'),
    isSuperAdmin: actor.isSuperAdmin(),
    isAvailable: actor.isAvailable,
  };

  return (
    <AppShell
      title={title}
      navUser={navUser}
      headerUser={headerUser}
      badges={badges}
      notifications={notifications}
      unread={unread}
      pageActions={pageActions}
      header={header}
      fixedHeight={fixedHeight}
    >
      {children}
    </AppShell>
  );
}
