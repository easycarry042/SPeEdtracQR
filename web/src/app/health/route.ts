import { NextResponse } from 'next/server';
import { db } from '@/lib/db';
import { currentActor } from '@/lib/auth/actor';
import { supabase, supabaseConfigured } from '@/lib/supabase';

/**
 * `health` — the system admin's view of whether this deployment can do its
 * job, replacing Spatie's health dashboard. Restricted to `manage system`: it
 * reports operational state, and is not an uptime ping.
 *
 * Each check answers ok / warn / fail with one line of why.
 */
export const dynamic = 'force-dynamic';

type Check = { status: 'ok' | 'warn' | 'fail'; detail: string };

async function database(): Promise<Check> {
  try {
    const started = Date.now();
    await db.$queryRaw`SELECT 1`;

    return { status: 'ok', detail: `Postgres answered in ${Date.now() - started} ms.` };
  } catch (error) {
    return { status: 'fail', detail: error instanceof Error ? error.message : 'Unreachable.' };
  }
}

async function storage(): Promise<Check> {
  if (!supabaseConfigured()) {
    return { status: 'warn', detail: 'SUPABASE_URL is not set: files go to the local disk, which Vercel cannot write.' };
  }

  const { data, error } = await supabase().storage.listBuckets();

  if (error) {
    return { status: 'fail', detail: error.message };
  }

  const names = new Set(data.map((bucket) => bucket.name));
  const missing = ['private', 'public'].filter((name) => !names.has(name));

  return missing.length === 0
    ? { status: 'ok', detail: 'Buckets private and public exist.' }
    : { status: 'fail', detail: `Missing bucket(s): ${missing.join(', ')}. Run npm run migrate:from-mysql -- --files-only.` };
}

function configured(name: string, ok: boolean, missing: string): Check {
  return ok ? { status: 'ok', detail: `${name} is configured.` } : { status: 'warn', detail: missing };
}

export async function GET(): Promise<Response> {
  const actor = await currentActor();

  if (!actor?.can('manage system')) {
    return NextResponse.json({ message: 'Forbidden.' }, { status: 403 });
  }

  const checks: Record<string, Check> = {
    database: await database(),
    storage: await storage(),
    realtime: configured(
      'Realtime',
      Boolean(process.env.NEXT_PUBLIC_SUPABASE_URL && process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY),
      'NEXT_PUBLIC_SUPABASE_URL / NEXT_PUBLIC_SUPABASE_ANON_KEY are not set: citizen pages fall back to SSE.',
    ),
    mail: configured('Mail', Boolean(process.env.MAIL_HOST), 'MAIL_HOST is not set: no email is sent.'),
    cron: configured(
      'Cron',
      (process.env.CRON_SECRET ?? '').length >= 16,
      'CRON_SECRET is missing or shorter than 16 characters: the SLA, hold and loan sweeps refuse to run.',
    ),
    signing: configured(
      'QR signing',
      Boolean(process.env.APP_KEY || process.env.QR_SIGNING_KEY),
      'APP_KEY is not set: QR codes and document seals cannot be signed or verified.',
    ),
  };

  const worst = Object.values(checks).some((c) => c.status === 'fail')
    ? 'fail'
    : Object.values(checks).some((c) => c.status === 'warn')
      ? 'warn'
      : 'ok';

  return NextResponse.json({ status: worst, checks }, { status: worst === 'fail' ? 503 : 200, headers: { 'Cache-Control': 'no-store' } });
}
