import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Actor } from '@/lib/auth/actor';
import {
  SIGNATURE_MISMATCH_MESSAGE,
  verifyFileSignature,
} from '@/lib/domain/file-signatures';
import { signUploadToken, verifyUploadToken } from '@/lib/domain/upload-token';

const { currentActorMock, throttleMock, createSignedMock } = vi.hoisted(() => ({
  currentActorMock: vi.fn(),
  throttleMock: vi.fn(),
  createSignedMock: vi.fn(),
}));

vi.mock('@/lib/auth/actor', () => ({
  currentActor: currentActorMock,
}));

vi.mock('@/lib/rate-limit', async (original) => ({
  ...(await original<typeof import('@/lib/rate-limit')>()),
  throttle: throttleMock,
}));

vi.mock('@/lib/storage', () => ({
  createSignedPrivateUploadUrl: createSignedMock,
}));

const presignRoute = await import('@/app/api/uploads/presign/route');

function bytesOf(header: number[], totalLen = 64): Uint8Array {
  const buf = new Uint8Array(totalLen);
  buf.set(header);
  return buf;
}

describe('file-signatures.verifyFileSignature', () => {
  it('accepts genuine PDF, image, office document, and plain text headers', () => {
    expect(verifyFileSignature(new TextEncoder().encode('%PDF-1.7\n'), 'letter.pdf')).toBeNull();
    expect(
      verifyFileSignature(bytesOf([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]), 'scan.png'),
    ).toBeNull();
    expect(verifyFileSignature(bytesOf([0xff, 0xd8, 0xff, 0xe0]), 'photo.jpg')).toBeNull();
    expect(
      verifyFileSignature(
        bytesOf([0x52, 0x49, 0x46, 0x46, 0x24, 0, 0, 0, 0x57, 0x45, 0x42, 0x50]),
        'photo.webp',
      ),
    ).toBeNull();
    expect(
      verifyFileSignature(
        bytesOf([0, 0, 0, 0x18, 0x66, 0x74, 0x79, 0x70, 0x68, 0x65, 0x69, 0x63]),
        'iphone.heic',
      ),
    ).toBeNull();
    expect(verifyFileSignature(bytesOf([0x50, 0x4b, 0x03, 0x04]), 'letter.docx')).toBeNull();
    expect(verifyFileSignature(bytesOf([0x50, 0x4b, 0x03, 0x04]), 'budget.xlsx')).toBeNull();
    expect(
      verifyFileSignature(bytesOf([0xd0, 0xcf, 0x11, 0xe0, 0xa1, 0xb1, 0x1a, 0xe1]), 'legacy.doc'),
    ).toBeNull();
    expect(verifyFileSignature(new TextEncoder().encode('{\\rtf1\\ansi hello}'), 'note.rtf')).toBeNull();
    expect(verifyFileSignature(new TextEncoder().encode('col1,col2\n1,2\n'), 'list.csv')).toBeNull();
    expect(verifyFileSignature(new TextEncoder().encode('Plain text note\r\n'), 'readme.txt')).toBeNull();
  });

  it('rejects spoofed executables and mismatched magic bytes', () => {
    const mz = bytesOf([0x4d, 0x5a, 0x90, 0x00]);
    expect(verifyFileSignature(mz, 'letter.pdf')).toBe(SIGNATURE_MISMATCH_MESSAGE);
    expect(verifyFileSignature(mz, 'id.jpg')).toBe(SIGNATURE_MISMATCH_MESSAGE);
    expect(verifyFileSignature(mz, 'notes.txt')).toBe(SIGNATURE_MISMATCH_MESSAGE);

    const elf = bytesOf([0x7f, 0x45, 0x4c, 0x46]);
    expect(verifyFileSignature(elf, 'scan.png')).toBe(SIGNATURE_MISMATCH_MESSAGE);

    const garbage = new TextEncoder().encode('not-actually-a-real-file');
    expect(verifyFileSignature(garbage, 'fake.pdf')).toBe(SIGNATURE_MISMATCH_MESSAGE);
    expect(verifyFileSignature(garbage, 'fake.png')).toBe(SIGNATURE_MISMATCH_MESSAGE);
    expect(verifyFileSignature(garbage, 'fake.docx')).toBe(SIGNATURE_MISMATCH_MESSAGE);

    expect(verifyFileSignature(bytesOf([0x41, 0x42, 0x00, 0x43]), 'binary.csv')).toBe(
      SIGNATURE_MISMATCH_MESSAGE,
    );
  });
});

describe('upload-token.signUploadToken & verifyUploadToken', () => {
  it('verifies a matching scope and rejects tampering, expiry, wrong scope, and path traversal', () => {
    const token = signUploadToken({
      path: 'document-requirements/abc123.pdf',
      scope: 'citizen-requirement',
      filename: 'letter.pdf',
    });

    expect(verifyUploadToken(token, 'citizen-requirement')).toEqual({
      path: 'document-requirements/abc123.pdf',
      filename: 'letter.pdf',
    });
    expect(verifyUploadToken(token, 'citizen-supporting')).toBeNull();

    const [body, sig] = token.split('.');
    expect(verifyUploadToken(`${body}.${sig.slice(0, -2)}XX`, 'citizen-requirement')).toBeNull();

    const expired = signUploadToken({
      path: 'document-requirements/abc123.pdf',
      scope: 'citizen-requirement',
      filename: 'letter.pdf',
      exp: Date.now() - 1000,
    });
    expect(verifyUploadToken(expired, 'citizen-requirement')).toBeNull();

    const traversal = signUploadToken({
      path: 'document-requirements/../secret.pdf',
      scope: 'citizen-requirement',
      filename: 'letter.pdf',
    });
    expect(verifyUploadToken(traversal, 'citizen-requirement')).toBeNull();
  });
});

describe('POST /api/uploads/presign', () => {
  const makeRequest = (
    body: unknown,
    headers: Record<string, string> = {},
  ) =>
    new Request('https://speedtraqr.local/api/uploads/presign', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        host: 'speedtraqr.local',
        origin: 'https://speedtraqr.local',
        ...headers,
      },
      body: JSON.stringify(body),
    }) as never;

  beforeEach(() => {
    vi.clearAllMocks();
    throttleMock.mockResolvedValue(true);
    currentActorMock.mockResolvedValue(null);
    createSignedMock.mockResolvedValue(null);
  });

  it('returns { mode: "server" } when Supabase storage is unconfigured', async () => {
    const res = await presignRoute.POST(
      makeRequest({ filename: 'letter.pdf', size: 1024, scope: 'citizen-requirement' }),
    );
    expect(res.status).toBe(200);
    expect(await res.json()).toEqual({ mode: 'server' });
  });

  it('returns signedUrl and verified receipt when Supabase is configured', async () => {
    createSignedMock.mockResolvedValue({
      path: 'document-attachments/0123456789abcdef.pdf',
      signedUrl: 'https://proj.supabase.co/storage/v1/object/upload/sign/private/0123456789abcdef.pdf?token=t1',
      token: 't1',
    });

    const res = await presignRoute.POST(
      makeRequest({ filename: 'supporting.pdf', size: 6_000_000, scope: 'citizen-supporting' }),
    );
    expect(res.status).toBe(200);
    const json = (await res.json()) as { mode: string; signedUrl: string; receipt: string; path: string };
    expect(json.mode).toBe('direct');
    expect(json.signedUrl).toContain('supabase.co');
    expect(verifyUploadToken(json.receipt, 'citizen-supporting')?.path).toBe(json.path);
  });

  it('rejects disallowed extensions, unauthenticated internal-paper, and cross-site posts', async () => {
    const disallowed = await presignRoute.POST(
      makeRequest({ filename: 'malware.exe', size: 1024, scope: 'citizen-requirement' }),
    );
    expect(disallowed.status).toBe(422);

    const unauthedInternal = await presignRoute.POST(
      makeRequest({ filename: 'scan.pdf', size: 1024, scope: 'internal-paper' }),
    );
    expect(unauthedInternal.status).toBe(403);

    currentActorMock.mockResolvedValue({
      id: 4,
      can: (perm: string) => perm === 'create internal requests',
    } as unknown as Actor);
    const authedInternal = await presignRoute.POST(
      makeRequest({ filename: 'scan.pdf', size: 1024, scope: 'internal-paper' }),
    );
    expect(authedInternal.status).toBe(200);

    const xsite = await presignRoute.POST(
      makeRequest(
        { filename: 'letter.pdf', size: 1024, scope: 'citizen-requirement' },
        { origin: 'https://evil.example', 'sec-fetch-site': 'cross-site' },
      ),
    );
    expect(xsite.status).toBe(403);
  });
});
