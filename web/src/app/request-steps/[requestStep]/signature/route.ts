import { NextResponse } from 'next/server';
import { db, id } from '@/lib/db';
import { currentActor } from '@/lib/auth/actor';
import { readPrivate } from '@/lib/storage';

/**
 * A hop's frozen e-signature, served to signed-in staff for the audit view.
 *
 * The file is the copy taken at the moment of approval, not the signer's
 * current signature — re-registering later must never rewrite what was affixed
 * to a past endorsement. It lives on the private disk, so this route is the
 * only way to see it.
 */
export async function GET(
  _request: Request,
  { params }: { params: Promise<{ requestStep: string }> },
) {
  const actor = await currentActor();

  if (!actor) {
    return new NextResponse('Forbidden', { status: 403 });
  }

  const { requestStep } = await params;
  const stepId = Number(requestStep);

  if (!Number.isInteger(stepId) || stepId <= 0) {
    return new NextResponse('Not found', { status: 404 });
  }

  const step = await db.request_steps.findFirst({
    where: { id: id(stepId) },
    select: { signature_path: true },
  });

  const bytes = step?.signature_path ? await readPrivate(step.signature_path) : null;

  if (!bytes) {
    return new NextResponse('Not found', { status: 404 });
  }

  return new NextResponse(new Uint8Array(bytes), {
    headers: { 'Content-Type': 'image/png', 'Cache-Control': 'no-store' },
  });
}
