import { mkdir, readFile, stat, writeFile } from 'node:fs/promises';
import { randomBytes } from 'node:crypto';
import path from 'node:path';

/**
 * The two Laravel disks, addressed directly on disk so both stacks read and
 * write the same files during cutover.
 *
 * `local` is PRIVATE (`storage/app/private`) and is where every citizen and
 * staff upload goes — those are served only through the attachment route, which
 * checks the viewer's office first. `public` (`storage/app/public`) holds the QR
 * images, which are deliberately world-readable.
 */
const STORAGE_ROOT = path.join(process.cwd(), '..', 'storage', 'app');

export const PRIVATE_ROOT = path.join(STORAGE_ROOT, 'private');
export const PUBLIC_ROOT = path.join(STORAGE_ROOT, 'public');

export function privatePath(relative: string): string {
  return path.join(PRIVATE_ROOT, relative);
}

export function publicPath(relative: string): string {
  return path.join(PUBLIC_ROOT, relative);
}

function extensionOf(name: string): string {
  const match = /\.([A-Za-z0-9]+)$/.exec(name);

  return match ? match[1].toLowerCase() : 'bin';
}

/**
 * Store an uploaded file on the private disk under `directory`, returning the
 * relative path to save on the row.
 *
 * Mirrors Laravel's `$file->store($dir, 'local')`: a random 40-char name keeps
 * the citizen's original filename out of the path (it is routinely their own
 * name) and makes paths unguessable even if one ever leaked into a log.
 */
export async function storePrivate(file: File, directory: string): Promise<string> {
  const relative = `${directory}/${randomBytes(20).toString('hex')}.${extensionOf(file.name)}`;

  await putPrivate(relative, Buffer.from(await file.arrayBuffer()));

  return relative;
}

/** Write bytes to an exact path on the private disk (`Storage::put`). */
export async function putPrivate(relative: string, contents: Buffer): Promise<void> {
  const absolute = privatePath(relative);

  await mkdir(path.dirname(absolute), { recursive: true });
  await writeFile(absolute, contents);
}

export async function readPrivate(relative: string): Promise<Buffer | null> {
  try {
    return await readFile(privatePath(relative));
  } catch {
    return null;
  }
}

/**
 * Whether a private file is really there. A row can carry a path whose file was
 * never written or has since been cleared — and a signature that exists only as
 * a column must not count as a registered signature.
 */
export async function privateExists(relative: string | null | undefined): Promise<boolean> {
  if (!relative) {
    return false;
  }

  try {
    return (await stat(privatePath(relative))).isFile();
  } catch {
    return false;
  }
}
