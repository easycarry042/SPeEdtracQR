import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import { formatNaive, now } from '@/lib/time';
import { requireActor } from '@/lib/auth/actor';
import { routes } from '@/lib/routes';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { desk, history, stock, tiles } from '@/lib/domain/inventory-desk';
import { ResourcesPage } from './resources-page';

export const metadata: Metadata = { title: 'Resources' };

/**
 * One Resources page for every role: the lending desk on top (what to approve,
 * what is overdue, what is waiting for pickup, what is out), then the stock —
 * facilities and their availability, equipment and its counts.
 *
 * Super admins get the same page plus the catalog controls; staff and
 * supervisors keep the counts, because they are the ones who hand the chairs
 * over and take them back, so they are the only people who know a count has
 * changed.
 */
export default async function InventoryPage() {
  const actor = await requireActor();

  if (!actor.can('manage inventory')) {
    redirect(routes.welcome());
  }

  const at = now();
  const [queues, inventory] = await Promise.all([desk(at), stock(at)]);
  const itemIds = [...inventory.facilities, ...inventory.equipment].map((row) => row.id);
  const itemHistory = await history(itemIds);

  const canEditCatalog = actor.can('manage system');
  const canManageBookings = actor.can('manage bookings');

  return (
    <AuthenticatedPage title="Resources">
      <ResourcesPage
        desk={queues}
        facilities={inventory.facilities}
        equipment={inventory.equipment}
        tiles={tiles(inventory.facilities, inventory.equipment, queues)}
        history={Object.fromEntries(itemHistory)}
        canEditCatalog={canEditCatalog}
        canManageBookings={canManageBookings}
        asOf={formatNaive(at, 'D j M Y, g:i A')}
      />
    </AuthenticatedPage>
  );
}
