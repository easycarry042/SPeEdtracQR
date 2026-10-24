import { db } from '@/lib/db';
import { DocumentStatus, label as statusLabel } from '@/lib/domain/document-status';
import { citizenTrackingUrl, statusOf } from '@/lib/domain/document';
import { canBeVerified, isVerified, verifiedTrackingNumbers } from '@/lib/domain/citizen-thread-access';
import { unreadCount, type TrackableDocument } from '@/lib/domain/citizen-thread';
import { ORIGIN_INTERNAL } from '@/lib/domain/scope';

/**
 * Port of `App\Support\Ai\SpeedyContext` — the request Speedy is talking
 * about, as the chat panel needs it. Only what the public tracking page
 * already shows.
 */
export type SpeedyContext = {
  tracking: string;
  type: string;
  status: string;
  url: string;
  suggestions: string[];
  /** Whether this browser confirmed the citizen; only then are messages shown. */
  verified: boolean;
  unread: number;
  canVerify: boolean;
};

export type SpeedyInbox = {
  total: number;
  threads: Array<{ tracking: string; type: string; unread: number }>;
};

export async function contextFor(document: TrackableDocument): Promise<SpeedyContext> {
  const type = document.document_type || 'document';
  const verified = await isVerified(document.tracking_number);

  // The questions the tracking page does NOT answer at a glance.
  const suggestions =
    statusOf(document) === DocumentStatus.Completed
      ? [`How do I claim my ${type}?`, `Is my ${type} authentic?`, 'What do I bring for pickup?']
      : [`When will my ${type} be ready?`, `What are the requirements for ${type}?`, 'What happens next?'];

  return {
    tracking: document.tracking_number,
    type,
    status: statusLabel(statusOf(document)),
    url: citizenTrackingUrl(document.tracking_number),
    suggestions,
    verified,
    unread: verified ? await unreadCount(document.id) : 0,
    canVerify: canBeVerified(document),
  };
}

/**
 * Unread office messages across every request this browser has confirmed, for
 * the badge on Speedy's launcher. No query for a browser that confirmed none —
 * nearly every visitor — so the landing page stays database-free for them.
 */
export async function inbox(): Promise<SpeedyInbox> {
  const numbers = await verifiedTrackingNumbers();

  if (numbers.length === 0) {
    return { total: 0, threads: [] };
  }

  const documents = await db.documents.findMany({
    where: { tracking_number: { in: numbers }, origin: { not: ORIGIN_INTERNAL }, deleted_at: null },
    select: { id: true, tracking_number: true, document_type: true },
  });

  const threads = await Promise.all(
    documents.map(async (document) => ({
      tracking: document.tracking_number,
      type: document.document_type || 'request',
      unread: await unreadCount(document.id),
    })),
  );

  return { total: threads.reduce((sum, thread) => sum + thread.unread, 0), threads };
}
