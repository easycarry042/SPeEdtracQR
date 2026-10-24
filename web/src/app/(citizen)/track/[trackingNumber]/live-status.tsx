'use client';

import { useEffect, useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { subscribeDocument } from '@/lib/client/realtime';

/**
 * Live status on the citizen tracking page.
 *
 * Replaces the Echo listener on the public `documents.{tracking}` channel with
 * the same `moved` event over Supabase Realtime (SSE locally). The payload is
 * still content-free of staff identity — it names the handling OFFICE only —
 * because anyone who knows a tracking number can open this page and this
 * channel. And because anyone can also post to a public topic, the banner does
 * not repeat the payload's stage: it only announces the refresh, and the stage
 * shown comes from the server render.
 *
 * On a move it refreshes the server component rather than patching the DOM by
 * id, so the stage rail, the hold banner and the pickup notice all re-render
 * from one source instead of three hand-patched elements drifting apart.
 */
export function LiveStatus({ trackingNumber }: { trackingNumber: string }) {
  const router = useRouter();
  const [connected, setConnected] = useState(false);
  const [moved, setMoved] = useState(false);
  // One refresh at a time, so a burst of events (or someone spamming the
  // public topic) costs one re-render, not one per event.
  const refreshing = useRef(false);

  useEffect(
    () =>
      subscribeDocument(trackingNumber, {
        onReady: () => setConnected(true),
        onLost: () => setConnected(false),
        on: {
          moved: () => {
            if (refreshing.current) {
              return;
            }

            refreshing.current = true;
            setMoved(true);
            // Let the banner show for a beat before the page re-renders under it.
            setTimeout(() => {
              router.refresh();
              setMoved(false);
              refreshing.current = false;
            }, 1_200);
          },
        },
      }),
    [router, trackingNumber],
  );

  return (
    <>
      <div className="flex items-center gap-2 text-xs text-ink-soft" id="lastChecked">
        <span
          className={`live-dot${connected ? '' : ' opacity-30'}`}
          aria-hidden="true"
          title={connected ? 'Live' : 'Reconnecting…'}
        />
        <span id="lastCheckedText">{connected ? 'Updates live' : 'Reconnecting…'}</span>
      </div>

      {moved ? (
        <div
          className="mt-4 rounded-xl border border-hairline bg-green-wash px-4 py-3 text-sm font-medium text-green-deep"
          role="status"
        >
          Status updated — refreshing…
        </div>
      ) : null}
    </>
  );
}
