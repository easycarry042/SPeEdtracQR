import { EventEmitter } from 'node:events';
import { db, id, toId } from '@/lib/db';
import { formatNaive, now } from '@/lib/time';
import { DocumentStatus, fromLoose, label as statusLabel } from '@/lib/domain/document-status';

/**
 * Live updates, replacing Laravel Reverb + laravel-echo.
 *
 * Reverb was a separate WebSocket daemon; dropping Laravel drops it too, so the
 * browser now holds a Server-Sent Events connection instead (one-way is all the
 * old channels ever were). The event names are unchanged — `moved`, `thread`,
 * `comment` — so the client code reads the same way it did with Echo.
 *
 * Delivery is belt-and-braces, because a Next.js deployment may run more than
 * one Node process and an in-memory emitter only reaches subscribers in its own:
 *
 *   1. `hub` delivers instantly to streams held by THIS process;
 *   2. every stream also re-reads its subject on a short interval and emits on
 *      change, which covers subscribers in any other process.
 *
 * The poll is the floor, not the optimisation: correctness never depends on the
 * emitter, so no broker has to be deployed or kept alive.
 */
const POLL_INTERVAL_MS = 3_000;

type HubEvent =
  | { channel: string; event: 'moved'; data: StatusPayload }
  | { channel: string; event: 'thread'; data: { from: string } }
  | { channel: string; event: 'comment'; data: CommentPayload };

class Hub extends EventEmitter {}

// Cached on globalThis so Next's dev-mode module reloading does not orphan
// listeners from already-open streams.
const globalForHub = globalThis as unknown as { speedtraqrHub?: Hub };
const hub = globalForHub.speedtraqrHub ?? new Hub();
hub.setMaxListeners(0);

if (process.env.NODE_ENV !== 'production') {
  globalForHub.speedtraqrHub = hub;
}

export function publish(message: HubEvent): void {
  hub.emit(message.channel, message);
}

export function subscribe(channel: string, listener: (message: HubEvent) => void): () => void {
  hub.on(channel, listener);

  return () => hub.off(channel, listener);
}

/* ── Channel names ──────────────────────────────────────────────────────────── */

/**
 * PUBLIC. Anyone who knows a tracking number can listen, so it must never
 * carry what was said in the conversation or who handled the request.
 */
export function publicChannel(trackingNumber: string): string {
  return `documents.${trackingNumber}`;
}

/** PRIVATE staff channel — the collaboration feed, internal notes included. */
export function staffChannel(documentId: number): string {
  return `documents.${documentId}.staff`;
}

export function userChannel(userId: number): string {
  return `App.Models.User.${userId}`;
}

/* ── Payloads ───────────────────────────────────────────────────────────────── */

export type StatusPayload = {
  status: string;
  status_label: string;
  /**
   * The handling office, never a staff member's name — staff stay anonymous to
   * the public for legal reasons, and this rides a public channel.
   */
  current_department: string | null;
  event: string;
  timestamp: string | null;
};

export type CommentPayload = {
  id: number;
  author: string;
  author_type: string;
  body: string;
  visibility: string;
  parent_id: number | null;
  timestamp: string | null;
};

export function statusPayload(document: {
  status: string;
  status_changed_at: Date | null;
  departmentName: string | null;
}): StatusPayload {
  const stage = fromLoose(document.status);

  return {
    status: document.status,
    status_label: statusLabel(stage),
    current_department: document.departmentName,
    // Matches the server-rendered activity log wording.
    event: `Updated to ${statusLabel(stage)}`,
    timestamp: formatNaive(document.status_changed_at ?? now(), 'M d, Y h:i A'),
  };
}

/* ── Emitting ───────────────────────────────────────────────────────────────── */

/**
 * Fired whenever a document's stage changes (manual advance/revert/return).
 * Broadcasts under the same `moved` alias the citizen tracker already listens
 * for.
 */
export async function broadcastStatusUpdated(documentId: number): Promise<void> {
  const row = await db.documents.findUnique({
    where: { id: id(documentId) },
    select: {
      tracking_number: true,
      status: true,
      status_changed_at: true,
      department: { select: { name: true } },
    },
  });

  if (!row) {
    return;
  }

  publish({
    channel: publicChannel(row.tracking_number),
    event: 'moved',
    data: statusPayload({
      status: row.status,
      status_changed_at: row.status_changed_at,
      departmentName: row.department?.name ?? null,
    }),
  });
}

/**
 * "This request's conversation changed" — on the request's PUBLIC channel, with
 * nothing in it but who spoke.
 *
 * It must not carry what was said: a stranger holding the tracking number could
 * otherwise read the conversation live while the page itself was still asking
 * them to confirm who they were. A confirmed browser hears this and re-fetches
 * the thread through the gated endpoint.
 */
export function broadcastThreadChanged(trackingNumber: string, from: string): void {
  publish({ channel: publicChannel(trackingNumber), event: 'thread', data: { from } });
}

/** A new collaboration post, on the per-document PRIVATE staff channel. */
export function broadcastComment(documentId: number, comment: CommentPayload): void {
  publish({ channel: staffChannel(documentId), event: 'comment', data: comment });
}

/* ── SSE plumbing ───────────────────────────────────────────────────────────── */

export type StreamSource = {
  /** Hub channels this stream relays. */
  channels: string[];
  /**
   * Cross-process floor: re-read the subject and return events to emit, or an
   * empty array when nothing changed. Called every `POLL_INTERVAL_MS`.
   */
  poll?: () => Promise<Array<{ event: string; data: unknown }>>;
};

/**
 * Wraps a source in a `text/event-stream` Response. Handles the hub
 * subscription, the poll, the keep-alive comment that stops proxies from
 * closing an idle connection, and teardown when the browser goes away.
 */
export function eventStream(source: StreamSource, signal?: AbortSignal): Response {
  const encoder = new TextEncoder();

  // Held in the enclosing scope so both `cancel()` and the request's abort
  // signal can tear the stream down — whichever fires first.
  let teardown = (): void => {};

  const stream = new ReadableStream({
    start(controller) {
      let open = true;

      const send = (event: string, data: unknown): void => {
        if (!open) {
          return;
        }

        try {
          controller.enqueue(encoder.encode(`event: ${event}\ndata: ${JSON.stringify(data)}\n\n`));
        } catch {
          // The client vanished mid-write; teardown handles the rest.
          open = false;
        }
      };

      const unsubscribes = source.channels.map((channel) =>
        subscribe(channel, (message) => send(message.event, message.data)),
      );

      const poll = source.poll;
      const poller = poll
        ? setInterval(() => {
            void poll()
              .then((events) => {
                for (const { event, data } of events) {
                  send(event, data);
                }
              })
              .catch(() => {
                // A transient database error must not kill the stream; the next
                // tick tries again.
              });
          }, POLL_INTERVAL_MS)
        : null;

      // Comment frames keep proxies and load balancers from dropping an idle
      // connection, and give the client something to notice a dead socket on.
      const keepAlive = setInterval(() => {
        if (!open) {
          return;
        }

        try {
          controller.enqueue(encoder.encode(': keep-alive\n\n'));
        } catch {
          open = false;
        }
      }, 25_000);

      teardown = () => {
        if (!open) {
          return;
        }

        open = false;

        for (const unsubscribe of unsubscribes) {
          unsubscribe();
        }

        if (poller) {
          clearInterval(poller);
        }

        clearInterval(keepAlive);
      };

      signal?.addEventListener('abort', teardown, { once: true });
      send('ready', { at: now().toISOString() });
    },

    cancel() {
      teardown();
    },
  });

  return new Response(stream, {
    headers: {
      'Content-Type': 'text/event-stream; charset=utf-8',
      'Cache-Control': 'no-cache, no-transform',
      Connection: 'keep-alive',
      // Nginx buffers proxied responses by default, which breaks SSE.
      'X-Accel-Buffering': 'no',
    },
  });
}

/**
 * Poll source for one document's public stage. Emits `moved` only when the
 * stage actually differs from what this connection last saw.
 */
export function documentStatusPoll(trackingNumber: string): () => Promise<
  Array<{ event: string; data: unknown }>
> {
  let lastSeen: string | null = null;

  return async () => {
    const row = await db.documents.findFirst({
      where: { tracking_number: trackingNumber, deleted_at: null },
      select: {
        status: true,
        status_changed_at: true,
        department: { select: { name: true } },
      },
    });

    if (!row) {
      return [];
    }

    const fingerprint = `${row.status}|${row.status_changed_at?.getTime() ?? 0}`;

    if (lastSeen === null) {
      lastSeen = fingerprint;
      return [];
    }

    if (fingerprint === lastSeen) {
      return [];
    }

    lastSeen = fingerprint;

    return [
      {
        event: 'moved',
        data: statusPayload({
          status: row.status,
          status_changed_at: row.status_changed_at,
          departmentName: row.department?.name ?? null,
        }),
      },
    ];
  };
}

/**
 * Poll source for the staff collaboration feed: new comments since the last
 * id this connection saw.
 */
export function commentPoll(documentId: number): () => Promise<
  Array<{ event: string; data: unknown }>
> {
  let lastId: number | null = null;

  return async () => {
    const rows = await db.document_comments.findMany({
      where: {
        document_id: id(documentId),
        ...(lastId === null ? {} : { id: { gt: id(lastId) } }),
      },
      orderBy: { id: 'asc' },
      take: lastId === null ? 1 : 50,
      select: {
        id: true,
        author_type: true,
        author_name: true,
        body: true,
        visibility: true,
        parent_id: true,
        created_at: true,
        author: { select: { name: true } },
      },
    });

    if (rows.length === 0) {
      return [];
    }

    const highest = toId(rows.at(-1)!.id);

    // First tick only establishes the cursor — do not replay history.
    if (lastId === null) {
      lastId = highest;
      return [];
    }

    lastId = highest;

    return rows.map((row) => ({
      event: 'comment',
      data: {
        id: toId(row.id),
        author: row.author?.name ?? row.author_name ?? 'System',
        author_type: row.author_type,
        body: row.body,
        visibility: row.visibility,
        parent_id: row.parent_id === null ? null : toId(row.parent_id),
        timestamp: row.created_at ? formatNaive(row.created_at, 'M d, Y h:i A') : null,
      } satisfies CommentPayload,
    }));
  };
}

export { DocumentStatus };
