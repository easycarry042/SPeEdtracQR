import { createHmac, timingSafeEqual } from 'node:crypto';
import { app } from '@/lib/config';
import { routes } from '@/lib/routes';

/**
 * Digital seal for issued documents: an HMAC over the document's immutable
 * identity, keyed by APP_KEY. The signature rides in the verification QR/URL so
 * anyone (a bank, another office) can confirm a permit is genuine without an
 * account — and a forged or altered code fails verification.
 *
 * Like the tracking QR, the key is the raw `APP_KEY` string (`base64:` prefix
 * included) that `config('app.key')` handed to `hash_hmac`, so seals already
 * printed on issued documents keep verifying.
 */
export type SealedDocument = {
  tracking_number: string;
  document_type: string;
  created_at: Date | null;
};

export function signature(document: SealedDocument): string {
  const payload = [
    document.tracking_number,
    document.document_type,
    // PHP's `->timestamp` on the Manila-naive value: seconds since the epoch of
    // the stored reading, which is what the original seal was computed over.
    document.created_at === null ? '' : String(Math.floor(document.created_at.getTime() / 1000)),
  ].join('|');

  // 20 hex chars keeps the QR payload compact while leaving ~80 bits of
  // signature strength — far beyond guessable.
  return createHmac('sha256', process.env.APP_KEY ?? '')
    .update(payload)
    .digest('hex')
    .slice(0, 20);
}

export function verify(document: SealedDocument, provided: string | null | undefined): boolean {
  if (typeof provided !== 'string' || provided === '') {
    return false;
  }

  const expected = Buffer.from(signature(document));
  const given = Buffer.from(provided);

  return expected.length === given.length && timingSafeEqual(expected, given);
}

/** Absolute public verification URL (this is what the seal QR encodes). */
export function url(document: SealedDocument): string {
  return `${app.url}${routes.verifyShow(document.tracking_number)}?sig=${signature(document)}`;
}
