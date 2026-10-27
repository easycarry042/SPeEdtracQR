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
    expect(text.name).toBe('Ramon Bautista');
    expect(text.date).toMatch(/2026/);
    expect(text.time).toMatch(/(AM|PM)$/);
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
      top: 1160,
      width: 600,
      height: 240,
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

  it("puts the approver's signature on the BY line", async () => {
    const scan = await sharp({ create: { width: 800, height: 1100, channels: 3, background: '#ffffff' } })
      .png()
      .toBuffer();
    const signature = await sharp({ create: { width: 300, height: 100, channels: 4, background: '#000000ff' } })
      .png()
      .toBuffer();
    const without = await stampImage(scan, 'png', text, placement);
    const withSignature = await stampImage(scan, 'png', text, placement, signature);

    expect(withSignature.equals(without)).toBe(false);
  });

  it('stamps the chosen PDF page', async () => {
    const pdf = await PDFDocument.create();
    pdf.addPage([612, 792]);
    pdf.addPage([612, 792]);
    const signature = await sharp({ create: { width: 300, height: 100, channels: 4, background: '#000000ff' } })
      .png()
      .toBuffer();
    const stamped = await stampPdf(Buffer.from(await pdf.save()), text, { ...placement, page: 2 }, signature);

    expect((await PDFDocument.load(stamped)).getPageCount()).toBe(2);
  });
});
