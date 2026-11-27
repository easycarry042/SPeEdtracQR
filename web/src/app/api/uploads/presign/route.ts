import { NextResponse, type NextRequest } from 'next/server';
import { currentActor } from '@/lib/auth/actor';
import * as uploadRules from '@/lib/domain/upload-rules';
import {
  isUploadScope,
  SCOPE_CONFIG,
  signUploadToken,
} from '@/lib/domain/upload-token';
import { crossSite, throttle, tooManyRequests } from '@/lib/rate-limit';
import { createSignedPrivateUploadUrl } from '@/lib/storage';

export const dynamic = 'force-dynamic';

export async function POST(request: NextRequest): Promise<Response> {
  if (crossSite(request)) {
    return NextResponse.json({ message: 'Forbidden.' }, { status: 403 });
  }

  let body: { filename?: unknown; size?: unknown; scope?: unknown };
  try {
    body = (await request.json()) as typeof body;
  } catch {
    return NextResponse.json({ message: 'Invalid upload request.' }, { status: 400 });
  }

  if (!isUploadScope(body.scope)) {
    return NextResponse.json({ message: 'Invalid upload scope.' }, { status: 422 });
  }

  const scope = body.scope;
  const filename = typeof body.filename === 'string' ? body.filename.trim() : '';
  const size = typeof body.size === 'number' ? body.size : NaN;

  if (filename === '' || !Number.isFinite(size) || size <= 0) {
    return NextResponse.json({ message: 'Provide a valid file name and size.' }, { status: 422 });
  }

  if (scope === 'internal-paper') {
    const actor = await currentActor();
    if (!actor || !actor.can('create internal requests')) {
      return NextResponse.json({ message: 'Unauthorized.' }, { status: 403 });
    }
  } else {
    if (!(await throttle('citizenUpload', request))) {
      return tooManyRequests();
    }
  }

  const problem = uploadRules.check(
    { name: filename, size },
    { required: true, allowed: SCOPE_CONFIG[scope].allowed },
  );
  if (problem) {
    return NextResponse.json({ message: problem }, { status: 422 });
  }

  try {
    const signed = await createSignedPrivateUploadUrl(SCOPE_CONFIG[scope].directory, filename);
    if (!signed) {
      return NextResponse.json(
        { mode: 'server' as const },
        { headers: { 'Cache-Control': 'no-store' } },
      );
    }

    const receipt = signUploadToken({ path: signed.path, scope, filename });

    return NextResponse.json(
      {
        mode: 'direct' as const,
        signedUrl: signed.signedUrl,
        path: signed.path,
        receipt,
      },
      { headers: { 'Cache-Control': 'no-store' } },
    );
  } catch (error) {
    console.error('[uploads/presign]', error);
    return NextResponse.json(
      { message: 'Could not prepare direct file upload.' },
      { status: 500 },
    );
  }
}
