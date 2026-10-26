import { describe, expect, it } from 'vitest';
import sharp from 'sharp';
import { PDFDocument } from 'pdf-lib';
import {
  cleanStampPlacement,
  officeStampCode,
  officeStampPath,
  officeStampText,
  stampBox,
  stampImage,
  stampPdf,
} from '@/lib/domain/office-stamp';

/** The optional RECEIVED stamp an office puts on an internal request's file at approval. */
const text = officeStampText('Office of the Mayor', 'Ramon Bautista', new Date(Date.UTC(2026, 9, 26, 9, 30)));
const placement = { x: 0.6, y: 0.05, size: 0.36, page: 1 };

describe('office stamp', () => {
  it('names the office, RECEIVED, who received it and when', () => {
    expect(text.office).toBe('OFFICE OF THE MAYOR');
    expect(text.received).toBe('RECEIVED');
    expect(text.by).toBe('BY: Ramon Bautista');
  });

  it("reads the office back from a stamped copy's path", () => {
    const path = officeStampPath('INT-20261026-ABC234', 12, 'OM', 'pdf');
    expect(path).toBe('document-attachments/INT-20261026-ABC234-office-stamp-12-OM.pdf');
    expect(officeStampCode(path)).toBe('OM');
    expect(officeStampCode('document-attachments/INT-20261026-ABC234-qr-stamped.pdf')).toBeNull();
  });

  it('keeps the box on the page and its size within bounds', () => {
    expect(stampBox(1000, 1400, { x: 0.95, y: 0.99, size: 0.9, page: null })).toEqual({
      left: 400,
      top: 1124,
      width: 600,
      height: 276,
    });
    expect(stampBox(1000, 1400, { x: null, y: null, size: null, page: null }).width).toBe(360);
  });

  it('cleans a placement from the browser', () => {
    expect(cleanStampPlacement(null)).toBeNull();
    expect(cleanStampPlacement('x')).toBeNull();
    expect(cleanStampPlacement({ x: 4, y: -1, size: 'big', page: 0 })).toEqual({ x: 1, y: 0, size: null, page: null });
  });

  it('stamps a picture without changing its size', async () => {
    const scan = await sharp({ create: { width: 800, height: 1100, channels: 3, background: '#ffffff' } })
      .png()
      .toBuffer();
    const stamped = await stampImage(scan, 'png', text, placement);
    const meta = await sharp(stamped).metadata();

    expect([meta.width, meta.height]).toEqual([800, 1100]);
    expect(stamped.equals(scan)).toBe(false);
  });

  it('stamps the chosen PDF page', async () => {
    const pdf = await PDFDocument.create();
    pdf.addPage([612, 792]);
    pdf.addPage([612, 792]);
    const stamped = await stampPdf(Buffer.from(await pdf.save()), text, { ...placement, page: 2 });

    expect((await PDFDocument.load(stamped)).getPageCount()).toBe(2);
  });
});
