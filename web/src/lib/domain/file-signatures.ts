/**
 * Inspects file magic bytes / binary signatures so renamed executables or
 * corrupted files cannot slip past extension-only checks.
 */

export const SIGNATURE_MISMATCH_MESSAGE =
  'The file contents do not match its file extension. Please upload a genuine, unmodified file.';

function extensionOf(name: string | null | undefined): string {
  const match = /\.([^.]+)$/.exec(String(name ?? ''));
  return match ? match[1].toLowerCase() : '';
}

function startsWithBytes(bytes: Uint8Array, prefix: readonly number[], offset = 0): boolean {
  if (bytes.length < offset + prefix.length) {
    return false;
  }
  for (let i = 0; i < prefix.length; i += 1) {
    if (bytes[offset + i] !== prefix[i]) {
      return false;
    }
  }
  return true;
}

function asciiAt(bytes: Uint8Array, offset: number, length: number): string {
  if (bytes.length < offset + length) {
    return '';
  }
  let out = '';
  for (let i = 0; i < length; i += 1) {
    out += String.fromCharCode(bytes[offset + i]);
  }
  return out;
}

function containsAscii(bytes: Uint8Array, needle: string, maxOffset = 1024): boolean {
  const limit = Math.min(bytes.length, maxOffset);
  for (let i = 0; i <= limit - needle.length; i += 1) {
    let match = true;
    for (let j = 0; j < needle.length; j += 1) {
      if (bytes[i + j] !== needle.charCodeAt(j)) {
        match = false;
        break;
      }
    }
    if (match) {
      return true;
    }
  }
  return false;
}

function isExecutableBinary(bytes: Uint8Array): boolean {
  // Windows PE / MZ
  if (startsWithBytes(bytes, [0x4d, 0x5a])) return true;
  // Linux ELF
  if (startsWithBytes(bytes, [0x7f, 0x45, 0x4c, 0x46])) return true;
  // Mach-O / Fat binary
  if (
    startsWithBytes(bytes, [0xfe, 0xed, 0xfa, 0xce]) ||
    startsWithBytes(bytes, [0xfe, 0xed, 0xfa, 0xcf]) ||
    startsWithBytes(bytes, [0xce, 0xfa, 0xed, 0xfe]) ||
    startsWithBytes(bytes, [0xcf, 0xfa, 0xed, 0xfe]) ||
    startsWithBytes(bytes, [0xca, 0xfe, 0xba, 0xbe])
  ) {
    return true;
  }
  return false;
}

const HEIF_BRANDS = new Set([
  'heic',
  'heix',
  'hevc',
  'hevx',
  'mif1',
  'msf1',
  'heis',
  'hevm',
  'heim',
  'avif',
]);

const ZIP_HEADERS = [
  [0x50, 0x4b, 0x03, 0x04],
  [0x50, 0x4b, 0x05, 0x06],
  [0x50, 0x4b, 0x07, 0x08],
] as const;

const OLE2_HEADER = [0xd0, 0xcf, 0x11, 0xe0, 0xa1, 0xb1, 0x1a, 0xe1] as const;
const RTF_HEADER = [0x7b, 0x5c, 0x72, 0x74, 0x66] as const;

/** Verify that the leading bytes match the claimed extension. */
export function verifyFileSignature(bytes: Uint8Array, filename: string): string | null {
  if (bytes.length === 0) {
    return null;
  }

  if (isExecutableBinary(bytes)) {
    return SIGNATURE_MISMATCH_MESSAGE;
  }

  const ext = extensionOf(filename);

  switch (ext) {
    case 'pdf':
      return containsAscii(bytes, '%PDF-', 1024) ? null : SIGNATURE_MISMATCH_MESSAGE;

    case 'png':
      return startsWithBytes(bytes, [0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a])
        ? null
        : SIGNATURE_MISMATCH_MESSAGE;

    case 'jpg':
    case 'jpeg':
      return startsWithBytes(bytes, [0xff, 0xd8, 0xff]) ? null : SIGNATURE_MISMATCH_MESSAGE;

    case 'webp':
      return startsWithBytes(bytes, [0x52, 0x49, 0x46, 0x46], 0) &&
        startsWithBytes(bytes, [0x57, 0x45, 0x42, 0x50], 8)
        ? null
        : SIGNATURE_MISMATCH_MESSAGE;

    case 'heic':
    case 'heif':
      return asciiAt(bytes, 4, 4) === 'ftyp' && HEIF_BRANDS.has(asciiAt(bytes, 8, 4))
        ? null
        : SIGNATURE_MISMATCH_MESSAGE;

    case 'docx':
    case 'xlsx':
    case 'odt':
    case 'ods':
      return ZIP_HEADERS.some((sig) => startsWithBytes(bytes, sig))
        ? null
        : SIGNATURE_MISMATCH_MESSAGE;

    case 'doc':
    case 'xls':
      return startsWithBytes(bytes, OLE2_HEADER) ||
        startsWithBytes(bytes, RTF_HEADER) ||
        ZIP_HEADERS.some((sig) => startsWithBytes(bytes, sig))
        ? null
        : SIGNATURE_MISMATCH_MESSAGE;

    case 'rtf':
      return startsWithBytes(bytes, RTF_HEADER) ? null : SIGNATURE_MISMATCH_MESSAGE;

    case 'txt':
    case 'csv': {
      const scanLen = Math.min(bytes.length, 4096);
      for (let i = 0; i < scanLen; i += 1) {
        const b = bytes[i];
        const allowedControl = b === 0x09 || b === 0x0a || b === 0x0d;
        if (!allowedControl && b < 0x20) {
          return SIGNATURE_MISMATCH_MESSAGE;
        }
      }
      return null;
    }

    default:
      return null;
  }
}

/** Reads the leading 4 KB slice of a browser/Node `File` and validates its signature. */
export async function checkFileSignature(file: File | null | undefined): Promise<string | null> {
  if (!file || file.size === 0) {
    return null;
  }

  const slice = file.slice(0, 4096);
  const head = new Uint8Array(await slice.arrayBuffer());
  return verifyFileSignature(head, file.name);
}
