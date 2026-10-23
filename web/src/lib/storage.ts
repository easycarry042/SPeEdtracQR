import { mkdir, readFile, stat, writeFile } from 'node:fs/promises';
import { randomBytes } from 'node:crypto';
import path from 'node:path';
import { supabase, supabaseConfigured } from '@/lib/supabase';

/**
 * The two Laravel disks, now two Supabase Storage buckets.
 *
 * `private` is where every citizen and staff upload goes — those are served
 * only through the attachment route, which checks the viewer's office first.
 * `public` holds the QR images, which are deliberately world-readable. Object
 * paths are the same relative paths Laravel stored on the rows
 * (`qrcodes/SPD-….png`, `attachments/…`), so migrated rows keep resolving.
 *
 * Vercel's filesystem is read-only, so Supabase is the only option there. When
 * SUPABASE_URL is unset (local dev beside the Laravel app) the old disk folders
 * under `storage/app` are used instead.
 */
export type Disk = 'private' | 'public';

const LOCAL_ROOT = path.join(process.cwd(), '..', 'storage', 'app');

function localPath(disk: Disk, relative: string): string {
  return path.join(LOCAL_ROOT, disk, relative);
}

function extensionOf(name: string): string {
  const match = /\.([A-Za-z0-9]+)$/.exec(name);

  return match ? match[1].toLowerCase() : 'bin';
}

async function put(disk: Disk, relative: string, contents: Buffer, contentType?: string): Promise<void> {
  if (!supabaseConfigured()) {
    const absolute = localPath(disk, relative);

    await mkdir(path.dirname(absolute), { recursive: true });
    await writeFile(absolute, contents);

    return;
  }

  const { error } = await supabase()
    .storage.from(disk)
    .upload(relative, contents, { contentType, upsert: true });

  if (error) {
    throw new Error(`Storage upload to ${disk}/${relative} failed: ${error.message}`);
  }
}

async function read(disk: Disk, relative: string): Promise<Buffer | null> {
  if (!supabaseConfigured()) {
    try {
      return await readFile(localPath(disk, relative));
    } catch {
      return null;
    }
  }

  const { data, error } = await supabase().storage.from(disk).download(relative);

  return error || !data ? null : Buffer.from(await data.arrayBuffer());
}

async function exists(disk: Disk, relative: string): Promise<boolean> {
  if (!supabaseConfigured()) {
    try {
      return (await stat(localPath(disk, relative))).isFile();
    } catch {
      return false;
    }
  }

  const { data, error } = await supabase().storage.from(disk).exists(relative);

  return !error && data;
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

  await put('private', relative, Buffer.from(await file.arrayBuffer()), file.type || undefined);

  return relative;
}

/** Write bytes to an exact path on the private disk (`Storage::put`). */
export async function putPrivate(relative: string, contents: Buffer, contentType?: string): Promise<void> {
  await put('private', relative, contents, contentType);
}

export async function readPrivate(relative: string): Promise<Buffer | null> {
  return read('private', relative);
}

/**
 * Whether a private file is really there. A row can carry a path whose file was
 * never written or has since been cleared — and a signature that exists only as
 * a column must not count as a registered signature.
 */
export async function privateExists(relative: string | null | undefined): Promise<boolean> {
  return relative ? exists('private', relative) : false;
}

/** Write a world-readable file (QR images) to the public disk. */
export async function putPublic(relative: string, contents: Buffer, contentType?: string): Promise<void> {
  await put('public', relative, contents, contentType);
}

export async function readPublic(relative: string): Promise<Buffer | null> {
  return read('public', relative);
}

/**
 * Where the browser can fetch a public file directly, or null when it is served
 * from the local disk (the `/storage/*` route streams it then).
 */
export function publicObjectUrl(relative: string): string | null {
  if (!supabaseConfigured()) {
    return null;
  }

  return supabase().storage.from('public').getPublicUrl(relative).data.publicUrl;
}
