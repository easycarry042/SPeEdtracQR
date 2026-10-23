import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * The storage layer on Supabase: the two Laravel disks are now the `private`
 * and `public` buckets, addressed by the same relative paths the rows carry.
 *
 * What matters is that a write lands in the right bucket (an upload in the
 * public one would be world-readable), that a missing object reads as null
 * rather than throwing, and that only the public disk ever yields a URL.
 */
const configured = vi.fn();
const upload = vi.fn();
const download = vi.fn();
const exists = vi.fn();
const getPublicUrl = vi.fn();
const from = vi.fn(() => ({ upload, download, exists, getPublicUrl }));

vi.mock('@/lib/supabase', () => ({
  supabaseConfigured: configured,
  supabase: () => ({ storage: { from } }),
}));

const storage = await import('@/lib/storage');

beforeEach(() => {
  vi.clearAllMocks();
  configured.mockReturnValue(true);
  upload.mockResolvedValue({ data: {}, error: null });
});

describe('writing', () => {
  it('puts QR images in the public bucket, overwriting a regenerated one', async () => {
    const png = Buffer.from('png');

    await storage.putPublic('qrcodes/SPD-20261022-ABCDEF.png', png, 'image/png');

    expect(from).toHaveBeenCalledWith('public');
    expect(upload).toHaveBeenCalledWith('qrcodes/SPD-20261022-ABCDEF.png', png, {
      contentType: 'image/png',
      upsert: true,
    });
  });

  it('stores uploads in the private bucket under a random name, never the original', async () => {
    const file = new File(['%PDF'], 'Juan Dela Cruz cedula.pdf', { type: 'application/pdf' });

    const relative = await storage.storePrivate(file, 'attachments');

    expect(from).toHaveBeenCalledWith('private');
    expect(relative).toMatch(/^attachments\/[0-9a-f]{40}\.pdf$/);
    expect(relative).not.toContain('Juan');
    expect(upload.mock.calls[0][2]).toEqual({ contentType: 'application/pdf', upsert: true });
  });

  it('surfaces a failed upload instead of pretending it was saved', async () => {
    upload.mockResolvedValue({ data: null, error: { message: 'Bucket not found' } });

    await expect(storage.putPrivate('a/b.png', Buffer.from('x'))).rejects.toThrow('Bucket not found');
  });
});

describe('reading', () => {
  it('returns the bytes of a private file', async () => {
    download.mockResolvedValue({ data: new Blob(['sig']), error: null });

    const bytes = await storage.readPrivate('signatures/1.png');

    expect(from).toHaveBeenCalledWith('private');
    expect(bytes?.toString()).toBe('sig');
  });

  it('reads a missing file as null', async () => {
    download.mockResolvedValue({ data: null, error: { message: 'Object not found' } });

    expect(await storage.readPrivate('signatures/gone.png')).toBeNull();
  });

  it('treats an empty path as no file without asking Supabase', async () => {
    expect(await storage.privateExists(null)).toBe(false);
    expect(await storage.privateExists('')).toBe(false);
    expect(exists).not.toHaveBeenCalled();
  });

  it('checks existence in the private bucket', async () => {
    exists.mockResolvedValue({ data: true, error: null });
    expect(await storage.privateExists('signatures/1.png')).toBe(true);

    exists.mockResolvedValue({ data: false, error: { message: 'nope' } });
    expect(await storage.privateExists('signatures/2.png')).toBe(false);
  });
});

describe('public URLs', () => {
  it('points at the public bucket', () => {
    getPublicUrl.mockReturnValue({ data: { publicUrl: 'https://x.supabase.co/storage/v1/object/public/public/qrcodes/a.png' } });

    expect(storage.publicObjectUrl('qrcodes/a.png')).toBe(
      'https://x.supabase.co/storage/v1/object/public/public/qrcodes/a.png',
    );
    expect(from).toHaveBeenCalledWith('public');
  });

  it('has none on the local disk, where the /storage route streams the file', () => {
    configured.mockReturnValue(false);

    expect(storage.publicObjectUrl('qrcodes/a.png')).toBeNull();
  });
});
