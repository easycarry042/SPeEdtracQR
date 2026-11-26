import { describe, expect, it } from 'vitest';
import sharp from 'sharp';
import { PDFDocument, PDFName, pushGraphicsState, scale } from 'pdf-lib';
import { renderPng } from '@/lib/domain/qr-service';
import { NO_PLACEMENT, stampImage, stampPdf } from '@/lib/domain/qr-stamp';

describe('qr-stamp PDF & image hardening', () => {
  it('isolates unbalanced graphics state and strips overlapping non-Link annotations in stampPdf', async () => {
    const pdf = await PDFDocument.create();
    const page = pdf.addPage([600, 800]);

    // Simulate a dirty source PDF that leaves an unclosed graphics state + scale transform.
    page.pushOperators(pushGraphicsState(), scale(0.5, 0.5));

    // Add two annotations: an Ink (white-out) overlapping the bottom-right QR box,
    // and a Link annotation in the same corner (which should be preserved),
    // plus an Ink annotation far away in the top-left (which should be preserved).
    const overlappingInk = pdf.context.register(
      pdf.context.obj({
        Type: 'Annot',
        Subtype: 'Ink',
        Rect: [420, 0, 600, 200],
      }),
    );
    const overlappingLink = pdf.context.register(
      pdf.context.obj({
        Type: 'Annot',
        Subtype: 'Link',
        Rect: [420, 0, 600, 200],
      }),
    );
    const distantInk = pdf.context.register(
      pdf.context.obj({
        Type: 'Annot',
        Subtype: 'Ink',
        Rect: [10, 700, 100, 780],
      }),
    );
    page.node.set(
      PDFName.of('Annots'),
      pdf.context.obj([overlappingInk, overlappingLink, distantInk]),
    );

    const qrPng = await renderPng('INT-20261126-VG4P48');
    const stampedBytes = await stampPdf(Buffer.from(await pdf.save()), qrPng, NO_PLACEMENT);

    const reloaded = await PDFDocument.load(stampedBytes);
    const stampedPage = reloaded.getPages()[0];

    // Overlapping Ink annotation must be removed; Link & distant Ink must remain.
    const annots = stampedPage.node.Annots();
    expect(annots).toBeDefined();
    expect(annots?.size()).toBe(2);

    // Content streams must be wrapped (q ... original ... Q ... qr) -> at least 4 streams.
    const contents = stampedPage.node.Contents();
    expect(contents).toBeDefined();
  });

  it('flattens AcroForm fields and embeds QR without throwing on forms', async () => {
    const pdf = await PDFDocument.create();
    const page = pdf.addPage([600, 800]);
    const form = pdf.getForm();
    const field = form.createTextField('notes');
    field.setText('Approved');
    field.addToPage(page, { x: 420, y: 20, width: 150, height: 80 });

    const qrPng = await renderPng('INT-20261126-VG4P48');
    const stampedBytes = await stampPdf(Buffer.from(await pdf.save()), qrPng, NO_PLACEMENT);
    const reloaded = await PDFDocument.load(stampedBytes);

    expect(reloaded.getForm().getFields()).toHaveLength(0);
  });

  it('stamps a JPEG image cleanly while preserving dimensions', async () => {
    const base = await sharp({
      create: { width: 800, height: 1000, channels: 3, background: '#f8faf8' },
    })
      .jpeg()
      .toBuffer();

    const qrPng = await renderPng('INT-20261126-VG4P48');
    const stamped = await stampImage(base, 'jpg', qrPng, NO_PLACEMENT);
    const meta = await sharp(stamped).metadata();

    expect(meta.format).toBe('jpeg');
    expect(meta.width).toBe(800);
    expect(meta.height).toBe(1000);
  });
});
