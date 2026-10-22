import { redirect } from 'next/navigation';
import { routes } from '@/lib/routes';

/**
 * The old citizen portal page only redirects to the landing page now — the
 * portal's cards were a second copy of the landing page's actions.
 */
export function GET(): never {
  redirect(routes.welcome());
}
