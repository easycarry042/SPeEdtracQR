import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import QRCode from 'qrcode';
import { db, id } from '@/lib/db';
import { requireActor } from '@/lib/auth/actor';
import * as trackingQr from '@/lib/domain/tracking-qr';
import { PrintOnLoad } from './print-on-load';

export const metadata: Metadata = { title: 'QR Sticker' };

/**
 * Port of `DocumentWebController@printSticker` — the 62 × 34 mm folder sticker:
 * the signed QR, the tracking number, the type and the requester. Opens the
 * print dialog by itself, as the Blade page did.
 */
export default async function StickerPage({ params }: { params: Promise<{ document: string }> }) {
  await requireActor();

  const documentId = Number((await params).document);
  const document = Number.isInteger(documentId) && documentId > 0
    ? await db.documents.findFirst({
        where: { id: id(documentId), deleted_at: null },
        select: { tracking_number: true, document_type: true, citizen_name: true },
      })
    : null;

  if (!document) {
    notFound();
  }

  const qrSvg = await QRCode.toString(trackingQr.payload(document.tracking_number), {
    type: 'svg',
    errorCorrectionLevel: 'M',
    margin: 0,
    width: 200,
  });

  return (
    <>
      <style>{`
        @page { size: 62mm 34mm; margin: 1.5mm; }
        html, body { margin: 0; padding: 0; background: #fff !important; font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .skip-link { display: none !important; }
        .ticket { width: 59mm; max-width: 100%; border: 0.35mm solid #1a3d1a; border-radius: 1.5mm; padding: 1.5mm 2mm; display: flex; flex-direction: column; gap: 1mm; box-sizing: border-box; }
        .hdr { font-size: 6.5pt; font-weight: 700; text-align: center; color: #14532d; line-height: 1.1; }
        .row { display: flex; align-items: flex-start; gap: 2mm; }
        .qr-wrap { flex: 0 0 19mm; width: 19mm; height: 19mm; display: flex; align-items: center; justify-content: center; background: #fff; }
        .qr-wrap svg { width: 19mm !important; height: 19mm !important; display: block; }
        .meta { flex: 1; min-width: 0; font-size: 6.5pt; line-height: 1.25; color: #111; }
        .tracking { font-family: ui-monospace, monospace; font-weight: 800; font-size: 7.5pt; word-break: break-all; color: #14532d; }
      `}</style>
      <PrintOnLoad />
      <div className="ticket">
        <div className="hdr">Municipality of San Pedro</div>
        <div className="row">
          {/* Rendered by `qrcode` from our own signed payload — no user input. */}
          <div className="qr-wrap" dangerouslySetInnerHTML={{ __html: qrSvg }} />
          <div className="meta">
            <div className="tracking">{document.tracking_number}</div>
            <div>{document.document_type}</div>
            <div>{document.citizen_name ?? 'N/A'}</div>
          </div>
        </div>
      </div>
    </>
  );
}
