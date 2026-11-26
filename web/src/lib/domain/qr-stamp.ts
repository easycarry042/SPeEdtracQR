import sharp from 'sharp';
import {
  PDFArray,
  PDFContentStream,
  PDFDict,
  PDFDocument,
  PDFName,
  PDFNumber,
  popGraphicsState,
  pushGraphicsState,
  rgb,
  type PDFPage,
} from 'pdf-lib';
import { db, id, toId } from '@/lib/db';
import { now } from '@/lib/time';
import { putPrivate, readPrivate } from '@/lib/storage';
import { renderPng } from '@/lib/domain/qr-service';

/**
 * Stamping the request's QR onto the signed paper scan.
 *
 * Port of `QrCodeService::stampQrOntoImage` (pictures, GD) and the browser-side
 * pdf-lib pass the Laravel confirmation screen ran for PDFs. Both now happen on
 * the server at filing time, so the stamped copy exists no matter what the
 * filer does after pressing File — the Next port had dropped this step
 * entirely, which is why an attached scan never carried its QR.
 *
 * The original upload is never touched: the stamped copy is a second
 * attachment, `{tracking}-qr-stamped.{ext}`, which the detail page labels
 * "QR-stamped copy" and the soft-copy receiver snips the QR from.
 */

export type StampPlacement = {
  /** Normalised top-left of the white box (0–1), as dragged on the filing form. */
  x: number | null;
  y: number | null;
  /** QR side as a fraction of the page's short edge (clamped 12%–40%). */
  size: number | null;
  /** 1-based page of a PDF to stamp. */
  page: number | null;
};

export const NO_PLACEMENT: StampPlacement = { x: null, y: null, size: null, page: null };

const STAMPED_MARKER = '-qr-stamped.';

const IMAGE_TYPES: Record<string, { format: 'jpeg' | 'png' | 'webp'; mime: string }> = {
  jpg: { format: 'jpeg', mime: 'image/jpeg' },
  jpeg: { format: 'jpeg', mime: 'image/jpeg' },
  png: { format: 'png', mime: 'image/png' },
  webp: { format: 'webp', mime: 'image/webp' },
};

function extensionOf(path: string): string {
  const match = /\.([A-Za-z0-9]+)$/.exec(path);

  return match ? match[1].toLowerCase() : '';
}

export function isStampable(path: string): boolean {
  const ext = extensionOf(path);

  return ext === 'pdf' || ext in IMAGE_TYPES;
}

export function isStampedPath(path: string): boolean {
  return path.includes(STAMPED_MARKER);
}

/** Read a normalised form number, or null when absent/out of range. */
export function placementNumber(value: unknown, min: number, max: number): number | null {
  if (value === null || value === undefined || value === '') {
    return null;
  }

  const number = Number(value);

  return Number.isFinite(number) && number >= min && number <= max ? number : null;
}

/**
 * The box geometry shared by both paths: QR at ~22% of the short edge by
 * default (readable to phone cameras without covering the text), a 6% white
 * pad so it scans on dark or busy paper, and a 10% margin from the corner.
 */
function geometry(width: number, height: number, placement: StampPlacement, minStamp: number) {
  const factor = placement.size !== null ? Math.max(0.12, Math.min(0.4, placement.size)) : 0.22;
  const stamp = Math.max(minStamp, Math.round(Math.min(width, height) * factor));
  const pad = Math.round(stamp * 0.06);
  const margin = Math.round(stamp * 0.1);
  const box = Math.min(stamp + pad * 2, Math.floor(Math.min(width, height)));

  let left: number;
  let top: number;

  if (placement.x !== null && placement.y !== null) {
    left = Math.round(placement.x * width);
    top = Math.round(placement.y * height);
  } else {
    left = width - box - margin;
    top = height - box - margin;
  }

  left = Math.max(0, Math.min(left, width - box));
  top = Math.max(0, Math.min(top, height - box));

  return { box, pad, stamp: box - pad * 2, left, top };
}

export async function stampImage(
  scan: Buffer,
  ext: string,
  qrPng: Buffer,
  placement: StampPlacement,
): Promise<Buffer> {
  const type = IMAGE_TYPES[ext];

  // Bake in the EXIF orientation first: the filing form placed the QR on the
  // picture as the browser showed it, which is already rotated upright.
  const upright = await sharp(scan, { failOn: 'none' }).rotate().toBuffer({ resolveWithObject: true });
  const { width, height } = upright.info;
  const { box, pad, stamp, left, top } = geometry(width, height, placement, 96);

  const qr = await sharp(qrPng)
    .flatten({ background: '#ffffff' })
    .removeAlpha()
    .resize(stamp, stamp, { kernel: 'nearest' })
    .png()
    .toBuffer();

  const composed = sharp(upright.data).composite([
    {
      input: { create: { width: box, height: box, channels: 3, background: '#ffffff' } },
      left,
      top,
    },
    { input: qr, left: left + pad, top: top + pad },
  ]);

  if (type.format === 'jpeg') {
    // 4:4:4 chroma subsampling keeps sharp black/white QR module edges crisp.
    return composed.jpeg({ quality: 96, chromaSubsampling: '4:4:4' }).toBuffer();
  }

  if (type.format === 'webp') {
    return composed.webp({ quality: 94 }).toBuffer();
  }

  return composed.png().toBuffer();
}

/**
 * Isolate existing page content streams inside `q ... Q` so an unbalanced
 * graphics state, transform matrix (`cm`), or open clipping path (`W`/`W*`)
 * in an uploaded PDF cannot clip, skew, or draw white bands across the QR.
 */
function isolatePageContentStreams(pdf: PDFDocument, page: PDFPage): void {
  page.node.normalize();
  const start = PDFContentStream.of(pdf.context.obj({}), [pushGraphicsState()]);
  const end = PDFContentStream.of(pdf.context.obj({}), [popGraphicsState()]);
  page.node.wrapContentStreams(pdf.context.register(start), pdf.context.register(end));
}

/**
 * Remove non-Link annotations (`/Ink`, `/Square`, `/FreeText`, `/Highlight`,
 * `/Redact`, etc.) whose `/Rect` intersects the QR box. In PDF, `/Annots` are
 * always painted ABOVE `/Contents`, so white-out or scanner cleanup annotations
 * on an uploaded PDF would otherwise paint white lines right over the QR code.
 */
function stripOverlappingAnnotations(
  pdf: PDFDocument,
  page: PDFPage,
  qrRect: { x: number; y: number; width: number; height: number },
): void {
  const annots = page.node.Annots();
  if (!annots) {
    return;
  }

  const qrRight = qrRect.x + qrRect.width;
  const qrTop = qrRect.y + qrRect.height;

  for (let i = annots.size() - 1; i >= 0; i -= 1) {
    const raw = pdf.context.lookup(annots.get(i));
    if (!(raw instanceof PDFDict)) {
      continue;
    }

    const subtype = raw.lookup(PDFName.of('Subtype'));
    if (subtype instanceof PDFName && subtype.asString() === '/Link') {
      continue;
    }

    const rectObj = raw.lookup(PDFName.of('Rect'));
    if (!(rectObj instanceof PDFArray) || rectObj.size() < 4) {
      continue;
    }

    const coords: number[] = [];
    for (let j = 0; j < 4; j += 1) {
      const n = pdf.context.lookup(rectObj.get(j));
      if (n instanceof PDFNumber) {
        coords.push(n.asNumber());
      }
    }
    if (coords.length < 4) {
      continue;
    }

    const minX = Math.min(coords[0], coords[2]);
    const maxX = Math.max(coords[0], coords[2]);
    const minY = Math.min(coords[1], coords[3]);
    const maxY = Math.max(coords[1], coords[3]);

    const overlaps = minX < qrRight && maxX > qrRect.x && minY < qrTop && maxY > qrRect.y;
    if (overlaps) {
      annots.remove(i);
    }
  }
}

export async function stampPdf(scan: Buffer, qrPng: Buffer, placement: StampPlacement): Promise<Buffer> {
  const pdf = await PDFDocument.load(scan, { ignoreEncryption: true });

  // Flatten interactive form fields into the content stream before isolating so
  // widget annotations cannot render on top of the stamped QR.
  try {
    pdf.getForm().flatten();
  } catch {
    // Documents without AcroForms or with malformed form trees still stamp fine.
  }

  const pages = pdf.getPages();

  if (pages.length === 0) {
    throw new Error('The PDF has no pages.');
  }

  const index = Math.max(0, Math.min((placement.page ?? 1) - 1, pages.length - 1));
  const page = pages[index];
  const crop = page.getCropBox();
  const { width, height } = crop.width > 0 && crop.height > 0 ? crop : page.getMediaBox();
  const originX = crop.width > 0 && crop.height > 0 ? crop.x : 0;
  const originY = crop.width > 0 && crop.height > 0 ? crop.y : 0;

  // 48pt ≈ 17mm: the smallest QR a phone reliably reads off A4.
  const { box, pad, stamp, left, top } = geometry(width, height, placement, 48);

  // PDF space starts bottom-left; the form's placement is from the top-left.
  const x = originX + left;
  const y = originY + height - top - box;

  stripOverlappingAnnotations(pdf, page, { x, y, width: box, height: box });
  isolatePageContentStreams(pdf, page);

  // Strip alpha channel so pdf-lib embeds a pure opaque RGB XObject (no /SMask).
  const opaqueQr = await sharp(qrPng)
    .flatten({ background: '#ffffff' })
    .removeAlpha()
    .png()
    .toBuffer();
  const image = await pdf.embedPng(opaqueQr);

  page.drawRectangle({ x, y, width: box, height: box, color: rgb(1, 1, 1) });
  page.drawImage(image, { x: x + pad, y: y + pad, width: stamp, height: stamp });

  return Buffer.from(await pdf.save());
}

/**
 * Make sure an internal request has its QR-stamped copy, creating it from the
 * first stampable attachment when it is missing. Returns the stored path, or
 * null when there is nothing to stamp.
 *
 * Never throws: a request without a stamped copy is still a valid request (it
 * can be received with the camera or the QR overlay), so a failure here is
 * logged rather than allowed to lose the filing.
 */
export async function ensureStampedCopy(
  documentId: number,
  placement: StampPlacement = NO_PLACEMENT,
  uploadedBy: number | null = null,
): Promise<string | null> {
  try {
    const document = await db.documents.findFirst({
      where: { id: id(documentId) },
      select: {
        id: true,
        tracking_number: true,
        created_by: true,
        document_attachments: {
          select: { file_path: true, sort_order: true },
          orderBy: [{ sort_order: 'asc' }, { id: 'asc' }],
        },
      },
    });

    if (!document) {
      return null;
    }

    const existing = document.document_attachments.find((row) => isStampedPath(row.file_path));

    if (existing) {
      return existing.file_path;
    }

    const original = document.document_attachments.find((row) => isStampable(row.file_path));

    if (!original) {
      return null;
    }

    const scan = await readPrivate(original.file_path);

    if (!scan) {
      return null;
    }

    const ext = extensionOf(original.file_path);
    const qrPng = await renderPng(document.tracking_number);
    const stamped =
      ext === 'pdf'
        ? await stampPdf(scan, qrPng, placement)
        : await stampImage(scan, ext, qrPng, placement);

    const path = `document-attachments/${document.tracking_number}${STAMPED_MARKER}${ext}`;

    await putPrivate(path, stamped, ext === 'pdf' ? 'application/pdf' : IMAGE_TYPES[ext].mime);

    const nextOrder =
      document.document_attachments.reduce((max, row) => Math.max(max, row.sort_order ?? 0), 0) + 1;
    const owner = uploadedBy ?? (document.created_by === null ? null : toId(document.created_by));

    await db.document_attachments.create({
      data: {
        document_id: document.id,
        file_path: path,
        uploaded_by: owner === null ? null : id(owner),
        sort_order: nextOrder,
        created_at: now(),
        updated_at: now(),
      },
    });

    return path;
  } catch (error) {
    console.error('[qr-stamp] stamping failed', {
      documentId,
      message: error instanceof Error ? error.message : String(error),
    });

    return null;
  }
}
