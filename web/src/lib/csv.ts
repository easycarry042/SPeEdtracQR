/**
 * CSV for the exports — what PHP's `fputcsv` produced, plus two things it did
 * not do:
 *
 *  - a UTF-8 byte-order mark, so Excel opens "Peña" as Peña and not "PeÃ±a";
 *  - formula neutering: citizen names and notes come from a public form, and a
 *    cell starting with = + - @ would run as a formula when the export is
 *    opened in a spreadsheet. Such cells get a leading apostrophe.
 */
export type Cell = string | number | null | undefined;

const FORMULA_START = /^[=+\-@\t\r]/;

function cell(value: Cell): string {
  if (value === null || value === undefined) {
    return '';
  }

  let text = String(value);

  if (typeof value === 'string' && FORMULA_START.test(text)) {
    text = `'${text}`;
  }

  return /[",\r\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
}

export function toCsv(rows: Cell[][]): string {
  return `﻿${rows.map((row) => row.map(cell).join(',')).join('\r\n')}\r\n`;
}

export function csvResponse(filename: string, rows: Cell[][]): Response {
  return new Response(toCsv(rows), {
    headers: {
      'Content-Type': 'text/csv; charset=utf-8',
      'Content-Disposition': `attachment; filename="${filename.replace(/[^\w.-]/g, '_')}"`,
      'Cache-Control': 'private, no-store',
    },
  });
}
