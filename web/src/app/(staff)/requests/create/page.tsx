import { redirect } from 'next/navigation';
import { routes } from '@/lib/routes';

/**
 * The filing form now opens in a modal over the Internal page
 * (`/requests?file=1`); this address stays so bookmarks and old links land
 * there.
 */
export default function FileInternalRequestPage() {
  redirect(routes.requestsIndex({ file: '1' }));
}
