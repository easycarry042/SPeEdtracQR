import { redirect } from 'next/navigation';
import { currentActor, touchLastActive } from '@/lib/auth/actor';
import { landingFor, routes } from '@/lib/routes';

/**
 * Resolves where a signed-in user belongs and sends them there.
 *
 * The middleware runs on the Edge with no database, so it cannot read roles; it
 * redirects here instead and this decides. Guests go to the landing page.
 */
export async function GET(): Promise<never> {
  const actor = await currentActor();

  if (!actor) {
    redirect(routes.welcome());
  }

  // Drives the "Active now" marker on the staff directory and profile.
  await touchLastActive(actor.id);

  redirect(landingFor(actor));
}
