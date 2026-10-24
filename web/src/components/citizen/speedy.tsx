import { contextFor, inbox } from '@/lib/domain/ai/speedy-context';
import { findTrackable } from '@/lib/domain/citizen-thread';
import { accept } from '@/lib/domain/upload-rules';
import { SpeedyChat } from './speedy-chat';

/**
 * `<x-speedy>`: drop on every citizen page. A page about one request (the
 * tracking page, the receipt) passes its tracking number and Speedy starts
 * there. Internal requests never become the topic.
 *
 * No query unless this browser has confirmed a request or a request is in
 * view, so the landing page still renders without the database for nearly
 * every visitor.
 */
export async function Speedy({ trackingNumber = null }: { trackingNumber?: string | null }) {
  const document = trackingNumber ? await findTrackable(trackingNumber) : null;
  const [context, waiting] = await Promise.all([
    document ? contextFor(document) : Promise.resolve(null),
    inbox(),
  ]);

  return <SpeedyChat initialContext={context} initialInbox={waiting} accept={accept()} />;
}
