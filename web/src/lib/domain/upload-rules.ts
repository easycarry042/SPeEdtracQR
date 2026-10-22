/**
 * One definition of what may be attached to a request, shared by every upload
 * path (public form, citizen re-upload, staff attachments, internal paper scan).
 *
 * Port of `App\Support\UploadRules`. Offices attach far more than photos: PDFs
 * and Word files for letters and clearances, spreadsheets for budgets and
 * participant lists, plain text for exports — and HEIC, which is what an iPhone
 * camera produces by default and was previously rejected as "not an allowed file".
 */

/** Photos and scans. */
export const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'] as const;

/** Text documents. */
export const DOCUMENT_EXTENSIONS = ['pdf', 'doc', 'docx', 'odt', 'rtf', 'txt'] as const;

/** Spreadsheets (budgets, participant lists, inventories). */
export const SHEET_EXTENSIONS = ['xls', 'xlsx', 'csv', 'ods'] as const;

/** Max upload size in kilobytes (10 MB). */
export const MAX_KILOBYTES = 10240;

export const MAX_BYTES = MAX_KILOBYTES * 1024;

/**
 * The signed paper of an internal request: only what the QR can be stamped
 * onto — a picture (sharp/GD, on the server) or a PDF (pdf-lib, in the browser).
 * A Word file used to be accepted and silently filed without a stamped copy.
 */
export const PAPER_SCAN_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp'] as const;

/** Every accepted extension. */
export function extensions(): string[] {
  return [...IMAGE_EXTENSIONS, ...DOCUMENT_EXTENSIONS, ...SHEET_EXTENSIONS];
}

/** The `accept` attribute for a file input, so the picker matches the rules. */
export function accept(list: readonly string[] = extensions()): string {
  return list.map((extension) => `.${extension}`).join(',');
}

function extensionOf(name: string | null | undefined): string {
  const match = /\.([^.]+)$/.exec(String(name ?? ''));

  return match ? match[1].toLowerCase() : '';
}

/**
 * Whether a stored path is a browser-renderable image (thumbnails, previews).
 *
 * HEIC/HEIF are images but most browsers cannot render them inline, so they are
 * treated as files for preview purposes.
 */
export function isImage(path: string | null | undefined): boolean {
  return ['jpg', 'jpeg', 'png', 'webp'].includes(extensionOf(path));
}

/** Human-readable summary for upload hints. */
export function hint(): string {
  return `PDF, Word, Excel, or image files, up to ${Math.floor(MAX_KILOBYTES / 1024)} MB each`;
}

export type UploadProblem = string | null;

/**
 * Validate one uploaded file against the shared rules.
 *
 * Returns the message to show, or null when the file is acceptable — the same
 * two failure modes Laravel's `mimes:` and `max:` rules produce, worded the way
 * the Blade forms worded them.
 */
export function check(
  file: { name: string; size: number } | null | undefined,
  options: { required?: boolean; allowed?: readonly string[] } = {},
): UploadProblem {
  const { required = false, allowed = extensions() } = options;

  if (!file || file.size === 0) {
    return required ? 'Please choose a file.' : null;
  }

  if (!allowed.includes(extensionOf(file.name))) {
    return allowed === PAPER_SCAN_EXTENSIONS
      ? 'The paper scan must be a PDF or a picture (JPG/PNG/WEBP).'
      : 'Each requirement file must be an image (JPG/PNG), a PDF, or a Word document (DOCX).';
  }

  if (file.size > MAX_BYTES) {
    return `That file is larger than ${Math.floor(MAX_KILOBYTES / 1024)} MB. Please attach a smaller copy.`;
  }

  return null;
}

/** Validate an internal request's paper scan (stampable formats only). */
export function checkPaperScan(file: { name: string; size: number } | null | undefined): UploadProblem {
  return check(file, { required: true, allowed: PAPER_SCAN_EXTENSIONS });
}
