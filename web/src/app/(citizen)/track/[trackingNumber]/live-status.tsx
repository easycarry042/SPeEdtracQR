'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { routes } from '@/lib/routes';

/**
 * Live status on the citizen tracking page.
 *
 * Replaces the Echo listener on the public `documents.{tracking}` channel with
 * an SSE connection carrying the same `moved` event. The payload is still
 * content-free of staff identity — it names the handling OFFICE only — because
 * anyone who knows a tracking number can open this page and this stream.
 *
 * On a move it refreshes the server component rather than patching the DOM by
 * id, so the stage rail, the hold banner and the pickup notice all re-render
 * from one source instead of three hand-patched elements drifting apart.
 */
type MovedPayload = {
  status: string;
  status_label: string;
  current_department: string | null;
  event: string;
  timestamp: string | null;
};

export function LiveStatus({ trackingNumber }: { trackingNumber: string }) {
  const router = useRouter();
  const [connected, setConnected] = useState(false);
  const [moved, setMoved] = useState<MovedPayload | null>(null);

  useEffect(() => {
    const source = new EventSource(routes.streamDocument(trackingNumber));

    source.addEventListener('ready', () => setConnected(true));
    source.addEventListener('error', () => setConnected(false));

    source.addEventListener('moved', (event) => {
      const payload = JSON.parse((event as MessageEvent<string>).data) as MovedPayload;

      setMoved(payload);
      // Let the banner show for a beat before the page re-renders under it.
      setTimeout(() => router.refresh(), 1_200);
    });

    return () => source.close();
  }, [router, trackingNumber]);

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
          Status updated to {moved.status_label} — refreshing…
        </div>
      ) : null}
    </>
  );
}
