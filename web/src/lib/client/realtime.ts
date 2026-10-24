'use client';

import { createClient, type SupabaseClient } from '@supabase/supabase-js';
import { routes } from '@/lib/routes';

/**
 * Browser side of live updates on the PUBLIC `documents.{tracking}` channel.
 *
 * With Supabase configured (NEXT_PUBLIC_SUPABASE_URL + anon key) it joins the
 * Supabase Realtime broadcast topic the server relays to; otherwise it opens
 * the SSE stream that local dev serves. Either way the caller gets the same
 * `ready` / `lost` / event callbacks.
 *
 * A public topic can be written to by anyone holding the anon key as well as
 * read, so an event here is only ever a reason to re-read the page from the
 * server — never something to render as fact.
 */
type Handlers = {
  onReady?: () => void;
  onLost?: () => void;
  on: Record<string, (payload: unknown) => void>;
};

let browserClient: SupabaseClient | null = null;

function supabaseBrowser(): SupabaseClient | null {
  const url = process.env.NEXT_PUBLIC_SUPABASE_URL;
  const anonKey = process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY;

  if (!url || !anonKey) {
    return null;
  }

  browserClient ??= createClient(url, anonKey, {
    auth: { persistSession: false, autoRefreshToken: false },
  });

  return browserClient;
}

export function subscribeDocument(trackingNumber: string, handlers: Handlers): () => void {
  const client = supabaseBrowser();

  if (!client) {
    return subscribeSse(trackingNumber, handlers);
  }

  let channel = client.channel(`documents.${trackingNumber}`);

  for (const [event, listener] of Object.entries(handlers.on)) {
    channel = channel.on('broadcast', { event }, ({ payload }) => listener(payload));
  }

  channel.subscribe((status) => {
    if (status === 'SUBSCRIBED') {
      handlers.onReady?.();
    } else {
      handlers.onLost?.();
    }
  });

  return () => {
    void client.removeChannel(channel);
  };
}

function subscribeSse(trackingNumber: string, handlers: Handlers): () => void {
  const source = new EventSource(routes.streamDocument(trackingNumber));

  source.addEventListener('ready', () => handlers.onReady?.());
  source.addEventListener('error', () => handlers.onLost?.());

  for (const [event, listener] of Object.entries(handlers.on)) {
    source.addEventListener(event, (message) =>
      listener(JSON.parse((message as MessageEvent<string>).data)),
    );
  }

  return () => source.close();
}
