import { createHmac, timingSafeEqual } from 'node:crypto';
import * as uploadRules from '@/lib/domain/upload-rules';
import { verifyFileSignature } from '@/lib/domain/file-signatures';

/**
 * Signed upload receipts for direct-to-Supabase browser uploads.
 *
 * A client uploads directly to a presigned storage path and submits the opaque
 * receipt string instead of raw file bytes; the server action verifies the
 * HMAC, reads the stored object, validates size/extension/magic-bytes, and
 * deletes rejected objects immediately.
 */

export type UploadScope = 'citizen-requirement' | 'citizen-supporting' | 'internal-paper';

export const SCOPE_CONFIG: Record<
  UploadScope,
  { directory: string; readonly allowed: readonly string[] }
> = {
  'citizen-requirement': {
    directory: 'document-requirements',
    get allowed() {
      return uploadRules.extensions();
    },
  },
  'citizen-supporting': {
    directory: 'document-attachments',
    get allowed() {
      return uploadRules.extensions();
    },
  },
  'internal-paper': {
    directory: 'document-attachments',
    get allowed() {
      return uploadRules.PAPER_SCAN_EXTENSIONS;
    },
  },
};

const TOKEN_TTL_MS = 15 * 60_000;

type TokenPayload = {
  path: string;
  scope: UploadScope;
  filename: string;
  exp: number;
};

function signingSecret(): string {
  return process.env.QR_SIGNING_KEY || process.env.APP_KEY || 'speedtraqr-dev-upload-key';
}

function hmacFor(bodyB64: string): Buffer {
  return createHmac('sha256', signingSecret()).update(`upload-token|${bodyB64}`).digest();
}

export function isUploadScope(value: unknown): value is UploadScope {
  return typeof value === 'string' && value in SCOPE_CONFIG;
}

export function signUploadToken(payload: {
  path: string;
  scope: UploadScope;
  filename: string;
  exp?: number;
}): string {
  const normalized: TokenPayload = {
    path: payload.path,
    scope: payload.scope,
    filename: payload.filename,
    exp: payload.exp ?? Date.now() + TOKEN_TTL_MS,
  };
  const body = Buffer.from(JSON.stringify(normalized), 'utf8').toString('base64url');
  const sig = hmacFor(body).toString('base64url');
  return `${body}.${sig}`;
}

export function verifyUploadToken(
  token: string,
  expectedScope: UploadScope,
): { path: string; filename: string } | null {
  const parts = token.split('.');
  if (parts.length !== 2) {
    return null;
  }

  const [body, sigStr] = parts;
  if (!body || !sigStr) {
    return null;
  }

  let providedSig: Buffer;
  try {
    providedSig = Buffer.from(sigStr, 'base64url');
  } catch {
    return null;
  }

  const expectedSig = hmacFor(body);
  if (
    providedSig.length !== expectedSig.length ||
    !timingSafeEqual(providedSig, expectedSig)
  ) {
    return null;
  }

  let parsed: unknown;
  try {
    parsed = JSON.parse(Buffer.from(body, 'base64url').toString('utf8'));
  } catch {
    return null;
  }

  if (!parsed || typeof parsed !== 'object') {
    return null;
  }

  const record = parsed as Partial<TokenPayload>;
  if (
    typeof record.path !== 'string' ||
    typeof record.filename !== 'string' ||
    typeof record.exp !== 'number' ||
    record.scope !== expectedScope ||
    Date.now() > record.exp ||
    record.path.includes('..') ||
    !record.path.startsWith(`${SCOPE_CONFIG[expectedScope].directory}/`)
  ) {
    return null;
  }

  return { path: record.path, filename: record.filename };
}

export async function consumeDirectUpload(
  token: string,
  expectedScope: UploadScope,
): Promise<{ ok: true; path: string } | { ok: false; error: string }> {
  const verified = verifyUploadToken(token, expectedScope);
  if (!verified) {
    return { ok: false, error: 'Your uploaded file expired. Please choose the file again.' };
  }

  // Dynamically load storage so unit tests that mock storePrivate-only on standard
  // FormData paths don't require extra unused exports.
  const { readPrivate, deletePrivate } = await import('@/lib/storage');
  const bytes = await readPrivate(verified.path);
  if (!bytes) {
    return {
      ok: false,
      error: 'Uploaded file could not be found. Please try uploading again.',
    };
  }

  const problem =
    uploadRules.check(
      { name: verified.filename, size: bytes.byteLength },
      { required: true, allowed: SCOPE_CONFIG[expectedScope].allowed },
    ) ?? verifyFileSignature(bytes, verified.filename);

  if (problem) {
    await deletePrivate(verified.path).catch(() => {});
    return { ok: false, error: problem };
  }

  return { ok: true, path: verified.path };
}
