import sharp from 'sharp';
import { PDFDocument, StandardFonts, rgb } from 'pdf-lib';
import { db, id, toId } from '@/lib/db';
import { formatNaive, now } from '@/lib/time';
import { putPrivate, readPrivate } from '@/lib/storage';
import { isStampable, type StampPlacement } from '@/lib/domain/qr-stamp';

/**
 * The RECEIVED stamp an office may put on an internal request's file when it
 * approves its hop — the digital version of the rubber stamp each office puts
 * on the paper: the office, RECEIVED, who received it, and the date and time.
 *
 * Optional: approving without a stamp is exactly as before. The stamp goes on
 * a NEW copy of the request's latest file, so every earlier stamp stays and the
 * files read as a trail; the original upload is never touched.
 *
 * Placement is the same normalised shape the filing form's QR uses: `x`/`y`
 * the stamp's top-left (0–1), `size` its width as a fraction of the page width,
 * `page` the 1-based PDF page.
 */
const OFFICE_STAMP_MARKER = '-office-stamp-';

/** Width as a fraction of the page: wide enough to read, small enough to fit a margin. */
const DEFAULT_WIDTH = 0.36;
const MIN_WIDTH = 0.2;
const MAX_WIDTH = 0.6;

/** Height : width of the stamp box. */
const ASPECT = 0.46;

/** Stamp ink: the blue of the rubber stamps on the paper memos. */
const INK = { hex: '#1d3f91', rgb: rgb(0x1d / 255, 0x3f / 255, 0x91 / 255) };

export type OfficeStampText = {
  city: string;
  office: string;
  received: string;
  by: string;
  when: string;
};

/** The words on the stamp. */
export function officeStampText(office: string, receivedBy: string, at: Date): OfficeStampText {
  return {
    city: 'CITY OF SAN PEDRO, LAGUNA',
    office: office.toUpperCase(),
    received: 'RECEIVED',
    by: `BY: ${receivedBy}`,
    when: `DATE: ${formatNaive(at, 'M j, Y')}   TIME: ${formatNaive(at, 'g:i A')}`,
  };
}

/** `{tracking}-office-stamp-{stepId}-{CODE}.{ext}` — one copy per stamped hop. */
export function officeStampPath(tracking: string, stepId: number, code: string, ext: string): string {
  return `document-attachments/${tracking}${OFFICE_STAMP_MARKER}${stepId}-${code.replace(/[^A-Za-z0-9]/g, '')}.${ext}`;
}

/** The office code an office-stamped copy carries, or null for any other file. */
export function officeStampCode(path: string): string | null {
  const match = /-office-stamp-\d+-([A-Za-z0-9]+)\.[A-Za-z0-9]+$/.exec(path);

  return match ? match[1] : null;
}

/**
 * A placement as it arrives from the browser, made safe: numbers only, the
 * corner kept on the page, the page a positive whole number. Null when the
 * office did not ask for a stamp.
 */
export function cleanStampPlacement(raw: unknown): StampPlacement | null {
  if (raw === null || typeof raw !== 'object') {
    return null;
  }

  const value = raw as Record<string, unknown>;
  const fraction = (n: unknown): number | null =>
    typeof n === 'number' && Number.isFinite(n) ? Math.max(0, Math.min(1, n)) : null;
  const page = typeof value.page === 'number' && Number.isInteger(value.page) && value.page >= 1 ? value.page : null;

  return { x: fraction(value.x), y: fraction(value.y), size: fraction(value.size), page };
}

function extensionOf(path: string): string {
  const match = /\.([A-Za-z0-9]+)$/.exec(path);

  return match ? match[1].toLowerCase() : '';
}

/** Where the box sits, in the page's own units, kept fully on the page. */
export function stampBox(width: number, height: number, placement: StampPlacement) {
  const factor =
    placement.size !== null ? Math.max(MIN_WIDTH, Math.min(MAX_WIDTH, placement.size)) : DEFAULT_WIDTH;
  const boxWidth = Math.min(width, Math.round(width * factor));
  const boxHeight = Math.min(height, Math.round(boxWidth * ASPECT));
  const margin = Math.round(width * 0.04);

  let left = placement.x !== null ? Math.round(placement.x * width) : margin;
  let top = placement.y !== null ? Math.round(placement.y * height) : margin;

  left = Math.max(0, Math.min(left, width - boxWidth));
  top = Math.max(0, Math.min(top, height - boxHeight));

  return { left, top, width: boxWidth, height: boxHeight };
}

function escapeXml(value: string): string {
  return value.replace(/[<>&'"]/g, (char) =>
    char === '<' ? '&lt;' : char === '>' ? '&gt;' : char === '&' ? '&amp;' : char === "'" ? '&apos;' : '&quot;',
  );
}

/** The stamp as SVG at its final pixel size, for pictures. */
function stampSvg(text: OfficeStampText, width: number, height: number): Buffer {
  const border = Math.max(2, Math.round(width * 0.012));
  const pad = Math.round(width * 0.05);
  const inner = width - pad * 2;
  // Shrink a long line (a long office or name) to fit rather than overflow.
  const line = (y: number, size: number, value: string, weight: number) => {
    const fitted = Math.min(size, inner / Math.max(1, value.length * 0.62));

    return `<text x="${width / 2}" y="${y}" font-size="${fitted.toFixed(1)}" font-weight="${weight}" text-anchor="middle" fill="${INK.hex}" font-family="Arial, Helvetica, sans-serif">${escapeXml(value)}</text>`;
  };

  const h = height;
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}">
  <rect x="${border / 2}" y="${border / 2}" width="${width - border}" height="${height - border}" rx="${border * 2}" fill="rgba(255,255,255,0.82)" stroke="${INK.hex}" stroke-width="${border}"/>
  ${line(h * 0.17, h * 0.085, text.city, 600)}
  ${line(h * 0.34, h * 0.11, text.office, 700)}
  ${line(h * 0.58, h * 0.2, text.received, 800)}
  ${line(h * 0.76, h * 0.09, text.by, 600)}
  ${line(h * 0.91, h * 0.085, text.when, 600)}
</svg>`;

  return Buffer.from(svg);
}

const IMAGE_FORMATS: Record<string, { format: 'jpeg' | 'png' | 'webp'; mime: string }> = {
  jpg: { format: 'jpeg', mime: 'image/jpeg' },
  jpeg: { format: 'jpeg', mime: 'image/jpeg' },
  png: { format: 'png', mime: 'image/png' },
  webp: { format: 'webp', mime: 'image/webp' },
};

export async function stampImage(
  scan: Buffer,
  ext: string,
  text: OfficeStampText,
  placement: StampPlacement,
): Promise<Buffer> {
  const type = IMAGE_FORMATS[ext];
  const upright = await sharp(scan, { failOn: 'none' }).rotate().toBuffer({ resolveWithObject: true });
  const box = stampBox(upright.info.width, upright.info.height, placement);

  const composed = sharp(upright.data).composite([
    { input: stampSvg(text, box.width, box.height), left: box.left, top: box.top },
  ]);

  if (type.format === 'jpeg') {
    return composed.jpeg({ quality: 92 }).toBuffer();
  }

  if (type.format === 'webp') {
    return composed.webp({ quality: 92 }).toBuffer();
  }

  return composed.png().toBuffer();
}

/** pdf-lib's standard fonts only carry Latin-1; anything else becomes "?". */
function latin1(value: string): string {
  return value.replace(/[^\x20-\x7e\xa0-\xff]/g, '?');
}

export async function stampPdf(
  scan: Buffer,
  text: OfficeStampText,
  placement: StampPlacement,
): Promise<Buffer> {
  const pdf = await PDFDocument.load(scan, { ignoreEncryption: true });
  const pages = pdf.getPages();

  if (pages.length === 0) {
    throw new Error('The PDF has no pages.');
  }

  const page = pages[Math.max(0, Math.min((placement.page ?? 1) - 1, pages.length - 1))];
  const { width, height } = page.getSize();
  const box = stampBox(width, height, placement);
  const bold = await pdf.embedFont(StandardFonts.HelveticaBold);
  const border = Math.max(1, box.width * 0.012);

  // PDF space starts bottom-left; placement is from the top-left.
  const bottom = height - box.top - box.height;

  page.drawRectangle({
    x: box.left,
    y: bottom,
    width: box.width,
    height: box.height,
    color: rgb(1, 1, 1),
    opacity: 0.82,
    borderColor: INK.rgb,
    borderWidth: border,
  });

  const inner = box.width * 0.9;
  const line = (fromTop: number, size: number, value: string) => {
    const words = latin1(value);
    const fitted = Math.min(size, (size * inner) / Math.max(1, bold.widthOfTextAtSize(words, size)));
    const textWidth = bold.widthOfTextAtSize(words, fitted);

    page.drawText(words, {
      x: box.left + (box.width - textWidth) / 2,
      y: bottom + box.height - box.height * fromTop - fitted * 0.35,
      size: fitted,
      font: bold,
      color: INK.rgb,
    });
  };

  const h = box.height;
  line(0.15, h * 0.085, text.city);
  line(0.32, h * 0.11, text.office);
  line(0.55, h * 0.2, text.received);
  line(0.74, h * 0.09, text.by);
  line(0.89, h * 0.085, text.when);

  return Buffer.from(await pdf.save());
}

/**
 * Put this office's RECEIVED stamp on a new copy of the request's latest file.
 * Returns the stored path, or null when there is nothing to stamp.
 *
 * Never throws: the approval it accompanies has already been recorded, so a
 * failed stamp is logged rather than allowed to undo it.
 */
export async function addOfficeStamp(
  documentId: number,
  step: { id: number; departmentName: string; departmentCode: string },
  stampedBy: { id: number; name: string },
  placement: StampPlacement,
): Promise<string | null> {
  try {
    const document = await db.documents.findFirst({
      where: { id: id(documentId) },
      select: {
        id: true,
        tracking_number: true,
        document_attachments: {
          select: { file_path: true, sort_order: true },
          orderBy: [{ sort_order: 'asc' }, { id: 'asc' }],
        },
      },
    });

    if (!document) {
      return null;
    }

    // The newest file carries every stamp so far (and the QR).
    const latest = [...document.document_attachments].reverse().find((row) => isStampable(row.file_path));

    if (!latest) {
      return null;
    }

    const scan = await readPrivate(latest.file_path);

    if (!scan) {
      return null;
    }

    const ext = extensionOf(latest.file_path);
    const text = officeStampText(step.departmentName, stampedBy.name, now());
    const stamped =
      ext === 'pdf' ? await stampPdf(scan, text, placement) : await stampImage(scan, ext, text, placement);

    const path = officeStampPath(document.tracking_number, step.id, step.departmentCode, ext);

    await putPrivate(path, stamped, ext === 'pdf' ? 'application/pdf' : IMAGE_FORMATS[ext].mime);

    const nextOrder =
      document.document_attachments.reduce((max, row) => Math.max(max, row.sort_order ?? 0), 0) + 1;

    await db.document_attachments.create({
      data: {
        document_id: document.id,
        file_path: path,
        uploaded_by: id(stampedBy.id),
        sort_order: nextOrder,
        created_at: now(),
        updated_at: now(),
      },
    });

    return path;
  } catch (error) {
    console.error('[office-stamp] stamping failed', {
      documentId,
      message: error instanceof Error ? error.message : String(error),
    });

    return null;
  }
}

/** The file an office would stamp next, for the placement preview. */
export async function latestStampableAttachment(
  documentId: number,
): Promise<{ id: number; ext: string } | null> {
  const rows = await db.document_attachments.findMany({
    where: { document_id: id(documentId) },
    select: { id: true, file_path: true },
    orderBy: [{ sort_order: 'desc' }, { id: 'desc' }],
  });

  const row = rows.find((attachment) => isStampable(attachment.file_path));

  return row ? { id: toId(row.id), ext: extensionOf(row.file_path) } : null;
}
