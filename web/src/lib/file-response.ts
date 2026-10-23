import path from 'node:path';

/**
 * Turning stored bytes into an HTTP response — what Laravel's
 * `$disk->response()` / `$disk->download()` did for the file routes.
 */
const TYPES: Record<string, string> = {
  png: 'image/png',
  jpg: 'image/jpeg',
  jpeg: 'image/jpeg',
  gif: 'image/gif',
  webp: 'image/webp',
  svg: 'image/svg+xml',
  pdf: 'application/pdf',
  doc: 'application/msword',
  docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
};

export function contentTypeFor(relative: string): string {
  return TYPES[path.extname(relative).slice(1).toLowerCase()] ?? 'application/octet-stream';
}

/**
 * Inline by default, so images render in `<img>` and PDFs preview in the
 * browser; `download` forces a "Save as". Private files are never cached by a
 * shared cache — they are behind an office check.
 */
export function fileResponse(
  relative: string,
  contents: Buffer,
  options: { download?: boolean; cache?: 'private' | 'public' } = {},
): Response {
  const type = contentTypeFor(relative);
  const headers: Record<string, string> = {
    'Content-Type': type,
    'Cache-Control': options.cache === 'public' ? 'public, max-age=300' : 'private, no-store',
  };

  // An SVG can carry script, which would run on this origin if opened directly.
  // (Not applied to everything: Chrome refuses to show a sandboxed PDF inline.)
  if (type === 'image/svg+xml') {
    headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; sandbox";
  }

  if (options.download) {
    headers['Content-Disposition'] = `attachment; filename="${path.basename(relative).replace(/"/g, '')}"`;
  }

  return new Response(new Uint8Array(contents), { headers });
}

export const notFound = (): Response => new Response('Not found', { status: 404 });
export const forbidden = (): Response => new Response('Forbidden', { status: 403 });

/** Route params arrive as strings; anything but a positive integer is a 404. */
export function positiveId(value: string): number | null {
  const parsed = Number(value);

  return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
}
