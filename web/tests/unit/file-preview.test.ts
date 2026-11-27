import { describe, expect, it } from 'vitest';
import { parseCsvRows, previewKindFor } from '@/components/file-preview-modal';

describe('previewKindFor', () => {
  it('classifies images, pdfs, docx, text, and csv for in-screen rendering', () => {
    expect(previewKindFor('docx')).toBe('docx');
    expect(previewKindFor('.DOCX')).toBe('docx');
    expect(previewKindFor('pdf')).toBe('pdf');
    expect(previewKindFor('JPG')).toBe('image');
    expect(previewKindFor('png')).toBe('image');
    expect(previewKindFor('webp')).toBe('image');
    expect(previewKindFor('txt')).toBe('text');
    expect(previewKindFor('csv')).toBe('csv');
    expect(previewKindFor('xlsx')).toBe('unsupported');
  });
});

describe('parseCsvRows', () => {
  it('parses quoted fields, escaped quotes, and CRLF line endings', () => {
    const csv = 'Name,Note\r\n"Cruz, Ana","Said ""hello"""\r\nJuan,Ready';

    expect(parseCsvRows(csv)).toEqual([
      ['Name', 'Note'],
      ['Cruz, Ana', 'Said "hello"'],
      ['Juan', 'Ready'],
    ]);
  });
});
