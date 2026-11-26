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
  request: NextRequest,
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
  const searchParams = new URL(request.url, 'http://localhost').searchParams;
  const forceDownload = searchParams.get('download') === '1';
  const forceInline = searchParams.get('inline') === '1';
  const forceRegenerate = searchParams.get('regenerate') === '1';

  // Only redirect straight to Supabase public URLs when neither forced
  // attachment download, same-origin inline fetch, nor regeneration is requested.
  if (!forceDownload && !forceInline && !forceRegenerate) {
    const remote = publicObjectUrl(relative);

    if (remote) {
      return NextResponse.redirect(remote, {
        status: 302,
        headers: { 'Cache-Control': 'public, max-age=300' },
      });
    }
  }

  let contents = forceRegenerate ? null : await readPublic(relative);

  // Fallback for valid SPD-/INT- QR stickers if missing on disk/storage or
  // explicitly regenerated after a partial write / environment switch.
  if (!contents) {
    const qrMatch = /^qrcodes\/((?:SPD|INT)-\d{8}-[A-Z0-9]{6})\.png$/i.exec(relative);

    if (qrMatch) {
      const trackingNumber = qrMatch[1].toUpperCase();
      const [{ db }, { renderPng }] = await Promise.all([
        import('@/lib/db'),
        import('@/lib/domain/qr-service'),
      ]);
      const exists = await db.documents.findFirst({
        where: { tracking_number: trackingNumber, deleted_at: null },
        select: { id: true },
      });

      if (exists) {
        contents = await renderPng(trackingNumber);
      }
    }
  }

  if (!contents) {
    return new Response('Not found', { status: 404 });
  }

  const extension = /\.([a-z0-9]+)$/i.exec(relative)?.[1]?.toLowerCase() ?? '';
  const headers: Record<string, string> = {
    'Content-Type': TYPES[extension] ?? 'application/octet-stream',
    'Cache-Control': 'public, max-age=300',
  };

  if (forceDownload) {
    const requestedName = searchParams.get('filename')?.trim();
    const rawName =
      requestedName && /^[A-Za-z0-9._-]+$/.test(requestedName)
        ? requestedName
        : segments[segments.length - 1];
    const ascii = rawName.replace(/[^\x20-\x7e]/g, '_').replace(/["\\]/g, '');

    headers['Content-Disposition'] =
      `attachment; filename="${ascii}"; filename*=UTF-8''${encodeURIComponent(rawName)}`;
  }

  return new Response(new Uint8Array(contents), { headers });
}
