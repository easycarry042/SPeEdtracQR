import { createClient, type SupabaseClient } from '@supabase/supabase-js';

/**
 * Server-side Supabase client, signed in with the service-role key.
 *
 * The app reads and writes its tables through Prisma, so this client is only
 * for the two Supabase services Prisma can't reach: Storage (uploads and QR
 * images) and Realtime (live updates). The service-role key bypasses every
 * policy, so it must never be imported into a client component — the browser
 * gets the anon key through `lib/client/realtime`.
 */
let client: SupabaseClient | null = null;

/** Whether this deployment has Supabase configured (Vercel always should). */
export function supabaseConfigured(): boolean {
  return Boolean(process.env.SUPABASE_URL && process.env.SUPABASE_SERVICE_ROLE_KEY);
}

export function supabase(): SupabaseClient {
  if (!supabaseConfigured()) {
    throw new Error('SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY must be set.');
  }

  client ??= createClient(process.env.SUPABASE_URL!, process.env.SUPABASE_SERVICE_ROLE_KEY!, {
    auth: { persistSession: false, autoRefreshToken: false },
  });

  return client;
}
