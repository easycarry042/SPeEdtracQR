import sharp, { type OverlayOptions } from 'sharp';
import { PDFDocument, StandardFonts, rgb } from 'pdf-lib';
import { db, id, toId } from '@/lib/db';
import { formatNaive, now } from '@/lib/time';
import { putPrivate, readPrivate } from '@/lib/storage';
import { isStampable, type StampPlacement } from '@/lib/domain/qr-stamp';

/**
 * The RECEIVED stamp an office may put on an internal request's file when it
 * approves its hop (approving is also how it passes the request on). It copies
 * the offices' rubber stamp: a square box with the office across the top, a
 * large RECEIVED, then "BY: ____" and "DATE: ____ TIME ____" lines, which the
 * system fills in the way the clerk would by hand: the approver's frozen
 * e-signature and printed name on the BY line, the date and time on theirs.
 *
 * The stamp is ink only (no background), so the paper shows through; the
 * filled-in parts are in pen blue. It goes on a NEW copy of the request's
 * latest file, so every earlier stamp stays and the files read as a trail; the
 * original upload is never touched.
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

/** Height : width of the stamp box (the rubber stamp is about 5 × 2 cm). */
export const OFFICE_STAMP_ASPECT = 0.4;
const ASPECT = OFFICE_STAMP_ASPECT;

/** Rubber-stamp ink, and the pen the BY / DATE / TIME lines are filled in with. */
const INK = { hex: '#262626', opacity: 0.9, rgb: rgb(0.15, 0.15, 0.15) };
const PEN = { hex: '#1a3d8f', rgb: rgb(0x1a / 255, 0x3d / 255, 0x8f / 255) };

/**
 * Where everything sits, as fractions of the box (x of its width, y of its
 * height; text y is the baseline). Shared by the picture and PDF drawings.
 */
const LAYOUT = {
  border: 0.006,
  office: { y: 0.2, size: 0.12, inset: 0.05 },
  received: { y: 0.53, size: 0.27 },
  by: { label: 0.05, y: 0.8, size: 0.1, lineFrom: 0.15, lineTo: 0.94 },
  signature: { from: 0.17, to: 0.6, top: 0.54, bottom: 0.8 },
  name: { from: 0.6, to: 0.93, y: 0.765, size: 0.08 },
  date: { label: 0.05, y: 0.95, size: 0.1, lineFrom: 0.19, lineTo: 0.56, valueSize: 0.08 },
  time: { label: 0.585, lineFrom: 0.73, lineTo: 0.94 },
  valueLift: 0.025,
} as const;

export type OfficeStampText = {
  office: string;
  received: string;
  name: string;
  date: string;
  time: string;
};

/** The words on the stamp. */
export function officeStampText(office: string, receivedBy: string, at: Date): OfficeStampText {
  return {
    office: office.toUpperCase(),
    received: 'RECEIVED',
    name: receivedBy,
    date: formatNaive(at, 'M j, Y'),
    time: formatNaive(at, 'g:i A'),
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

/** The stamp's ink and its filled-in words as SVG at its final pixel size, for pictures. */
function stampSvg(text: OfficeStampText, width: number, height: number): Buffer {
  const w = width;
  const h = height;
  const L = LAYOUT;
  const stroke = Math.max(1.5, w * L.border);
  // Shrink a long line (a long office or name) to fit rather than overflow.
  const fit = (size: number, room: number, value: string, factor: number) =>
    Math.min(size, room / Math.max(1, value.length * factor));
  const textAt = (
    x: number,
    y: number,
    size: number,
    value: string,
    { weight = 500, anchor = 'start', color = INK.hex, spacing = 0 } = {},
  ) =>
    `<text x="${x.toFixed(1)}" y="${y.toFixed(1)}" font-size="${size.toFixed(1)}" font-weight="${weight}" text-anchor="${anchor}" letter-spacing="${spacing.toFixed(1)}" fill="${color}" font-family="Arial, Helvetica, sans-serif">${escapeXml(value)}</text>`;
  const line = (from: number, to: number, y: number) =>
    `<line x1="${(w * from).toFixed(1)}" y1="${(h * y).toFixed(1)}" x2="${(w * to).toFixed(1)}" y2="${(h * y).toFixed(1)}" stroke="${INK.hex}" stroke-width="${(stroke * 0.8).toFixed(1)}"/>`;

  const officeSize = fit(h * L.office.size, w * (1 - L.office.inset * 2), text.office, 0.68);
  const nameSize = fit(h * L.name.size, w * (L.name.to - L.name.from), text.name, 0.55);
  const dateMid = (L.date.lineFrom + L.date.lineTo) / 2;
  const timeMid = (L.time.lineFrom + L.time.lineTo) / 2;

  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}">
  <g opacity="${INK.opacity}">
    <rect x="${stroke / 2}" y="${stroke / 2}" width="${w - stroke}" height="${h - stroke}" fill="none" stroke="${INK.hex}" stroke-width="${stroke}"/>
    ${textAt(w / 2, h * L.office.y, officeSize, text.office, { anchor: 'middle', spacing: officeSize * 0.04 })}
    ${textAt(w / 2, h * L.received.y, fit(h * L.received.size, w * 0.8, text.received, 0.75), text.received, { weight: 800, anchor: 'middle', spacing: h * 0.01 })}
    ${textAt(w * L.by.label, h * L.by.y, h * L.by.size, 'BY:')}
    ${line(L.by.lineFrom, L.by.lineTo, L.by.y)}
    ${textAt(w * L.date.label, h * L.date.y, h * L.date.size, 'DATE:')}
    ${line(L.date.lineFrom, L.date.lineTo, L.date.y)}
    ${textAt(w * L.time.label, h * L.date.y, h * L.date.size, 'TIME')}
    ${line(L.time.lineFrom, L.time.lineTo, L.date.y)}
  </g>
  ${textAt(w * L.name.to, h * L.name.y, nameSize, text.name, { weight: 600, anchor: 'end', color: PEN.hex })}
  ${textAt(w * dateMid, h * (L.date.y - L.valueLift), h * L.date.valueSize, text.date, { weight: 600, anchor: 'middle', color: PEN.hex })}
  ${textAt(w * timeMid, h * (L.date.y - L.valueLift), h * L.date.valueSize, text.time, { weight: 600, anchor: 'middle', color: PEN.hex })}
</svg>`;

  return Buffer.from(svg);
}

/** The signature's spot on the BY line, in the box's own pixels. */
function signatureSlot(width: number, height: number) {
  const S = LAYOUT.signature;

  return {
    left: Math.round(width * S.from),
    top: Math.round(height * S.top),
    width: Math.max(1, Math.round(width * (S.to - S.from))),
    height: Math.max(1, Math.round(height * (S.bottom - S.top))),
  };
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
  signature: Buffer | null = null,
): Promise<Buffer> {
  const type = IMAGE_FORMATS[ext];
  const upright = await sharp(scan, { failOn: 'none' }).rotate().toBuffer({ resolveWithObject: true });
  const box = stampBox(upright.info.width, upright.info.height, placement);
  const layers: OverlayOptions[] = [
    { input: stampSvg(text, box.width, box.height), left: box.left, top: box.top },
  ];

  if (signature) {
    const slot = signatureSlot(box.width, box.height);
    const fitted = await sharp(signature, { failOn: 'none' })
      .resize(slot.width, slot.height, { fit: 'inside' })
      .png()
      .toBuffer({ resolveWithObject: true });

    // Sit it on the line, centred along it, as a hand signature would.
    layers.push({
      input: fitted.data,
      left: box.left + slot.left + Math.round((slot.width - fitted.info.width) / 2),
      top: box.top + slot.top + slot.height - fitted.info.height,
    });
  }

  const composed = sharp(upright.data).composite(layers);

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
  signature: Buffer | null = null,
): Promise<Buffer> {
  const pdf = await PDFDocument.load(scan, { ignoreEncryption: true });
  const pages = pdf.getPages();

  if (pages.length === 0) {
    throw new Error('The PDF has no pages.');
  }

  const page = pages[Math.max(0, Math.min((placement.page ?? 1) - 1, pages.length - 1))];
  const { width, height } = page.getSize();
  const box = stampBox(width, height, placement);
  const regular = await pdf.embedFont(StandardFonts.Helvetica);
  const bold = await pdf.embedFont(StandardFonts.HelveticaBold);
  const L = LAYOUT;
  const w = box.width;
  const h = box.height;
  const stroke = Math.max(0.8, w * L.border);

  // PDF space starts bottom-left; the layout is from the box's top-left.
  const X = (fraction: number) => box.left + w * fraction;
  const Y = (fraction: number) => height - box.top - h * fraction;

  const fitted = (font: typeof bold, value: string, size: number, room: number) =>
    Math.min(size, (size * room) / Math.max(1, font.widthOfTextAtSize(value, size)));
  const write = (
    value: string,
    x: number,
    y: number,
    size: number,
    { font = regular, anchor = 'start', color = INK.rgb, opacity = INK.opacity } = {},
  ) => {
    const words = latin1(value);
    const textWidth = font.widthOfTextAtSize(words, size);
    const left = anchor === 'middle' ? x - textWidth / 2 : anchor === 'end' ? x - textWidth : x;

    page.drawText(words, { x: left, y, size, font, color, opacity });
  };
  const rule = (from: number, to: number, y: number) =>
    page.drawLine({
      start: { x: X(from), y: Y(y) },
      end: { x: X(to), y: Y(y) },
      thickness: stroke * 0.8,
      color: INK.rgb,
      opacity: INK.opacity,
    });

  page.drawRectangle({
    x: box.left + stroke / 2,
    y: Y(1) + stroke / 2,
    width: w - stroke,
    height: h - stroke,
    borderColor: INK.rgb,
    borderWidth: stroke,
    borderOpacity: INK.opacity,
  });

  const office = latin1(text.office);
  write(office, X(0.5), Y(L.office.y), fitted(regular, office, h * L.office.size, w * (1 - L.office.inset * 2)), { anchor: 'middle' });
  write(text.received, X(0.5), Y(L.received.y), fitted(bold, text.received, h * L.received.size, w * 0.8), { font: bold, anchor: 'middle' });
  write('BY:', X(L.by.label), Y(L.by.y), h * L.by.size);
  rule(L.by.lineFrom, L.by.lineTo, L.by.y);
  write('DATE:', X(L.date.label), Y(L.date.y), h * L.date.size);
  rule(L.date.lineFrom, L.date.lineTo, L.date.y);
  write('TIME', X(L.time.label), Y(L.date.y), h * L.date.size);
  rule(L.time.lineFrom, L.time.lineTo, L.date.y);

  if (signature) {
    try {
      const image = await pdf.embedPng(signature).catch(() => pdf.embedJpg(signature));
      const room = { width: w * (L.signature.to - L.signature.from), height: h * (L.signature.bottom - L.signature.top) };
      const scale = Math.min(room.width / image.width, room.height / image.height);

      page.drawImage(image, {
        x: X(L.signature.from) + (room.width - image.width * scale) / 2,
        y: Y(L.signature.bottom),
        width: image.width * scale,
        height: image.height * scale,
      });
    } catch {
      // An unreadable signature leaves the printed name to stand alone.
    }
  }

  const name = latin1(text.name);
  const pen = { font: bold, color: PEN.rgb, opacity: 1 };
  write(name, X(L.name.to), Y(L.name.y), fitted(bold, name, h * L.name.size, w * (L.name.to - L.name.from)), { ...pen, anchor: 'end' });
  write(text.date, X((L.date.lineFrom + L.date.lineTo) / 2), Y(L.date.y - L.valueLift), h * L.date.valueSize, { ...pen, anchor: 'middle' });
  write(text.time, X((L.time.lineFrom + L.time.lineTo) / 2), Y(L.date.y - L.valueLift), h * L.date.valueSize, { ...pen, anchor: 'middle' });

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
  /** The approver's e-signature as frozen on this hop, for the BY line. */
  signaturePath: string | null = null,
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
    const signature = signaturePath ? await readPrivate(signaturePath) : null;
    const stamped =
      ext === 'pdf'
        ? await stampPdf(scan, text, placement, signature)
        : await stampImage(scan, ext, text, placement, signature);

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
