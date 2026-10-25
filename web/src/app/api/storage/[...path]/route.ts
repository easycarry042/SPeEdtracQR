import { NextResponse, type NextRequest } from 'next/server';
import { publicObjectUrl, readPublic } from '@/lib/storage';

/**
 * The public disk, at the URLs Laravel served it from (`/storage/qrcodes/…`,
 * rewritten here by next.config). Printed receipts and old emails carry those
 * links, so they must keep working.
 *
 * With Supabase the file is in the `public` bucket and the browser is sent
 * straight there; on the local disk it is streamed from `web/storage/app/public`.
 * Only the public disk is reachable — uploads live on the private one and go
 * through the attachment route's office check.
 */
export const dynamic = 'force-dynamic';

const TYPES: Record<string, string> = {
  png: 'image/png',
  jpg: 'image/jpeg',
  jpeg: 'image/jpeg',
  webp: 'image/webp',
  svg: 'image/svg+xml',
  pdf: 'application/pdf',
};

export async function GET(
  _request: NextRequest,
  { params }: { params: Promise<{ path: string[] }> },
): Promise<Response> {
  const segments = (await params).path.map((segment) => decodeURIComponent(segment));

  // A decoded `/` or `\` inside a segment would let `..` back in.
  const unsafe = (segment: string): boolean =>
    segment === '' || segment === '.' || segment === '..' || /[\\/]/.test(segment);

  if (segments.length === 0 || segments.some(unsafe)) {
    return new Response('Not found', { status: 404 });
  }

  const relative = segments.join('/');
  const remote = publicObjectUrl(relative);

  if (remote) {
    return NextResponse.redirect(remote, {
      status: 302,
      headers: { 'Cache-Control': 'public, max-age=300' },
    });
  }

  const contents = await readPublic(relative);

  if (!contents) {
    return new Response('Not found', { status: 404 });
  }

  const extension = /\.([a-z0-9]+)$/i.exec(relative)?.[1]?.toLowerCase() ?? '';

  return new Response(new Uint8Array(contents), {
    headers: {
      'Content-Type': TYPES[extension] ?? 'application/octet-stream',
      'Cache-Control': 'public, max-age=300',
    },
  });
}
