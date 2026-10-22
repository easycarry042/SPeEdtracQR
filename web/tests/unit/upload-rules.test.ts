import { describe, expect, it } from 'vitest';
import {
  MAX_BYTES,
  PAPER_SCAN_EXTENSIONS,
  accept,
  check,
  checkPaperScan,
  extensions,
  hint,
  isImage,
} from '@/lib/domain/upload-rules';

/**
 * Port of the upload-rule expectations in `MultiFormatUploadTest` and
 * `CitizenUploadPickerTest`.
 *
 * HEIC is the case worth pinning: it is what an iPhone camera produces by
 * default, it was once rejected as "not an allowed file", and it is accepted for
 * upload while still not counting as inline-renderable.
 */
const file = (name: string, size = 1024) => ({ name, size });

describe('UploadRules.check', () => {
  it('accepts the formats offices actually attach', () => {
    for (const name of [
      'scan.jpg', 'scan.jpeg', 'scan.png', 'photo.webp', 'photo.heic', 'photo.heif',
      'letter.pdf', 'letter.doc', 'letter.docx', 'letter.odt', 'letter.rtf', 'notes.txt',
      'budget.xls', 'budget.xlsx', 'list.csv', 'list.ods',
    ]) {
      expect(check(file(name)), name).toBeNull();
    }
  });

  it('accepts an iPhone HEIC photo', () => {
    expect(check(file('IMG_0001.HEIC'))).toBeNull();
  });

  it('is case-insensitive about the extension', () => {
    expect(check(file('SCAN.PDF'))).toBeNull();
  });

  it('refuses a format nobody should be filing', () => {
    expect(check(file('payload.exe'))).toContain('must be an image');
    expect(check(file('archive.zip'))).toContain('must be an image');
  });

  it('refuses a file over 10 MB', () => {
    expect(check(file('big.pdf', MAX_BYTES + 1))).toContain('10 MB');
  });

  it('accepts a file exactly at the limit', () => {
    expect(check(file('exact.pdf', MAX_BYTES))).toBeNull();
  });

  it('treats no file as acceptable when optional, and a problem when required', () => {
    expect(check(null)).toBeNull();
    expect(check(undefined)).toBeNull();
    expect(check(file('empty.pdf', 0))).toBeNull();
    expect(check(null, { required: true })).toContain('choose a file');
  });
});

describe('UploadRules.checkPaperScan', () => {
  it('accepts only what the QR can be stamped onto', () => {
    for (const name of ['signed.pdf', 'signed.jpg', 'signed.jpeg', 'signed.png', 'signed.webp']) {
      expect(checkPaperScan(file(name)), name).toBeNull();
    }
  });

  it('refuses a Word file, which was once filed with no stamped copy', () => {
    expect(checkPaperScan(file('signed.docx'))).toContain('PDF or a picture');
  });

  it('refuses a HEIC, which the stamper cannot render', () => {
    expect(checkPaperScan(file('signed.heic'))).toContain('PDF or a picture');
  });

  it('requires a file', () => {
    expect(checkPaperScan(null)).toContain('choose a file');
  });
});

describe('UploadRules.isImage', () => {
  it('is true only for formats a browser renders inline', () => {
    expect(isImage('a/b.jpg')).toBe(true);
    expect(isImage('a/b.png')).toBe(true);
    expect(isImage('a/b.webp')).toBe(true);
  });

  it('is false for HEIC, which most browsers cannot render inline', () => {
    expect(isImage('a/b.heic')).toBe(false);
    expect(isImage('a/b.heif')).toBe(false);
  });

  it('is false for documents and for nothing at all', () => {
    expect(isImage('a/b.pdf')).toBe(false);
    expect(isImage(null)).toBe(false);
    expect(isImage('')).toBe(false);
  });
});

describe('UploadRules.accept', () => {
  it('matches the picker to the rules, so the dialog cannot offer a refused file', () => {
    const attribute = accept();

    for (const extension of extensions()) {
      expect(attribute).toContain(`.${extension}`);
    }
  });

  it('narrows to the paper-scan set when asked', () => {
    expect(accept(PAPER_SCAN_EXTENSIONS)).toBe('.pdf,.jpg,.jpeg,.png,.webp');
  });
});

describe('UploadRules.hint', () => {
  it('states the ceiling in MB, as the forms show it', () => {
    expect(hint()).toBe('PDF, Word, Excel, or image files, up to 10 MB each');
  });
});
