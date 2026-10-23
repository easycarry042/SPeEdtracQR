import { redirect } from 'next/navigation';
import { routes } from '@/lib/routes';

/**
 * The super admin's resource catalog is the shared Resources page, not a page
 * of its own: a second list of the same items would disagree with the counts
 * staff keep. Old links land here, so they are sent on.
 */
export function GET(): never {
  redirect(routes.inventoryIndex());
}
