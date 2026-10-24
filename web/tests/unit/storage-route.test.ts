import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * `/storage/*` — the legacy QR image URLs printed on receipts and in old
 * emails. It may only ever reach the public disk: a `..` segment that climbed
 * into `private/` would hand out citizens' uploads with no office check.
 */
const publicObjectUrl = vi.fn();
const readPublic = vi.fn();

vi.mock('@/lib/storage', () => ({ publicObjectUrl, readPublic }));

const { GET } = await import('@/app/api/storage/[...path]/route');

function get(...path: string[]): Promise<Response> {
  return GET(new Request('http://localhost/storage/x') as never, {
    params: Promise.resolve({ path }),
  });
}

beforeEach(() => {
  vi.clearAllMocks();
  publicObjectUrl.mockReturnValue(null);
});

describe('GET /storage/*', () => {
  it('sends the browser to the Supabase public bucket', async () => {
    publicObjectUrl.mockReturnValue('https://x.supabase.co/storage/v1/object/public/public/qrcodes/a.png');

    const response = await get('qrcodes', 'a.png');

    expect(response.status).toBe(302);
    expect(response.headers.get('location')).toBe(
      'https://x.supabase.co/storage/v1/object/public/public/qrcodes/a.png',
    );
    expect(publicObjectUrl).toHaveBeenCalledWith('qrcodes/a.png');
  });

  it('streams the file from the local disk with its type', async () => {
    readPublic.mockResolvedValue(Buffer.from('png'));

    const response = await get('qrcodes', 'a.png');

    expect(response.status).toBe(200);
    expect(response.headers.get('content-type')).toBe('image/png');
  });

  it('answers 404 for a missing file', async () => {
    readPublic.mockResolvedValue(null);

    expect((await get('qrcodes', 'gone.png')).status).toBe(404);
  });

  it.each([
    [['..', 'private', 'attachments', 'a.pdf']],
    [['qrcodes', '..', '..', 'private', 'a.pdf']],
    [['..%2Fprivate%2Fa.pdf']],
    [['qrcodes%5C..%5Ca.pdf']],
    [[]],
  ])('refuses %j without touching storage', async (path) => {
    expect((await get(...path)).status).toBe(404);
    expect(publicObjectUrl).not.toHaveBeenCalled();
    expect(readPublic).not.toHaveBeenCalled();
  });
});
