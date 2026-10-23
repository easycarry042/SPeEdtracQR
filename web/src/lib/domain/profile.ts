import { randomBytes } from 'node:crypto';
import { db, id } from '@/lib/db';
import { logActivity } from '@/lib/activity';
import type { Actor } from '@/lib/auth/actor';
import { hashPassword, verifyPassword } from '@/lib/auth/password';
import { ActionError } from '@/lib/domain/status-actions';
import { deletePrivate, putPrivate } from '@/lib/storage';
import { now, unixTimestamp } from '@/lib/time';

/**
 * Port of `ProfileController`, `Auth\PasswordController`, `AvatarController`,
 * `SignatureController` and `StaffBadgeController` — what a signed-in person
 * can change about their own account. Every function acts on the actor
 * themselves; nothing here takes another user's id.
 */

/** A face, not a document scan. */
const AVATAR_MAX_BYTES = 2 * 1024 * 1024;

/** A canvas sketch is a few KB, never MB. */
const SIGNATURE_MAX_BYTES = 512 * 1024;

const PNG_DATA_URL = 'data:image/png;base64,';

/** Laravel's `Password::defaults()`: at least 8 characters. */
const PASSWORD_MIN = 8;

/** Read the bytes, not the extension: a ".jpg" that is really a script is refused. */
export function imageKind(bytes: Buffer): 'jpg' | 'png' | 'webp' | null {
  if (bytes.length >= 3 && bytes[0] === 0xff && bytes[1] === 0xd8 && bytes[2] === 0xff) {
    return 'jpg';
  }

  if (bytes.length >= 8 && bytes.subarray(0, 8).equals(Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]))) {
    return 'png';
  }

  if (bytes.length >= 12 && bytes.toString('ascii', 0, 4) === 'RIFF' && bytes.toString('ascii', 8, 12) === 'WEBP') {
    return 'webp';
  }

  return null;
}

export async function updateDetails(actor: Actor, input: { name: string; email: string }): Promise<{ message: string }> {
  const name = input.name.trim();
  const email = input.email.trim().toLowerCase();

  if (name === '' || name.length > 255) {
    throw new ActionError('Enter your name.');
  }

  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) || email.length > 255) {
    throw new ActionError('Enter a valid email address.');
  }

  const clash = await db.users.findFirst({
    where: { email: { equals: email, mode: 'insensitive' }, NOT: { id: id(actor.id) } },
    select: { id: true },
  });

  if (clash) {
    throw new ActionError('The email has already been taken.');
  }

  const current = await db.users.findUnique({ where: { id: id(actor.id) }, select: { email: true } });

  await db.users.update({
    where: { id: id(actor.id) },
    data: {
      name,
      email,
      // A changed address is unverified again, as Breeze did.
      ...(current?.email.toLowerCase() !== email ? { email_verified_at: null } : {}),
      updated_at: now(),
    },
  });

  return { message: 'Saved.' };
}

export async function changePassword(
  actor: Actor,
  input: { current: string; password: string; confirmation: string },
): Promise<{ message: string }> {
  const user = await db.users.findUnique({ where: { id: id(actor.id) }, select: { password: true } });

  if (!user || !(await verifyPassword(input.current, user.password))) {
    throw new ActionError('The password is incorrect.');
  }

  if (input.password.length < PASSWORD_MIN) {
    throw new ActionError(`The new password must be at least ${PASSWORD_MIN} characters.`);
  }

  if (input.password !== input.confirmation) {
    throw new ActionError('The new password confirmation does not match.');
  }

  await db.users.update({
    where: { id: id(actor.id) },
    data: { password: await hashPassword(input.password), updated_at: now() },
  });

  return { message: 'Saved.' };
}

export async function storeAvatar(actor: Actor, file: File | null): Promise<{ message: string }> {
  if (!file || file.size === 0) {
    throw new ActionError('Choose a picture first.');
  }

  if (file.size > AVATAR_MAX_BYTES) {
    throw new ActionError('The picture must be 2 MB or smaller.');
  }

  const bytes = Buffer.from(await file.arrayBuffer());
  const kind = imageKind(bytes);

  if (!kind) {
    throw new ActionError('The picture must be a JPG, PNG, or WebP image.');
  }

  const user = await db.users.findUnique({ where: { id: id(actor.id) }, select: { avatar_path: true } });
  // The timestamp in the name busts any cached copy of the old picture.
  const path = `avatars/user-${actor.id}-${unixTimestamp(now())}.${kind}`;

  await putPrivate(path, bytes, kind === 'jpg' ? 'image/jpeg' : `image/${kind}`);
  await db.users.update({ where: { id: id(actor.id) }, data: { avatar_path: path, updated_at: now() } });

  if (user?.avatar_path && user.avatar_path !== path) {
    await deletePrivate(user.avatar_path);
  }

  return { message: 'Profile picture updated.' };
}

export async function removeAvatar(actor: Actor): Promise<{ message: string }> {
  const user = await db.users.findUnique({ where: { id: id(actor.id) }, select: { avatar_path: true } });

  if (user?.avatar_path) {
    await deletePrivate(user.avatar_path);
    await db.users.update({ where: { id: id(actor.id) }, data: { avatar_path: null, updated_at: now() } });
  }

  return { message: 'Profile picture removed.' };
}

/**
 * The registered e-signature, drawn on a canvas and posted as a PNG data URL.
 * Approvals copy it onto the request step, so later re-registration never
 * rewrites a past endorsement.
 */
export async function storeSignature(actor: Actor, dataUrl: string): Promise<{ message: string }> {
  if (!dataUrl.startsWith(PNG_DATA_URL) || dataUrl.length > 800_000) {
    throw new ActionError('The signature must be a PNG drawing.');
  }

  const bytes = Buffer.from(dataUrl.slice(PNG_DATA_URL.length), 'base64');

  if (bytes.length === 0 || bytes.length > SIGNATURE_MAX_BYTES || imageKind(bytes) !== 'png') {
    throw new ActionError('The signature drawing could not be read.');
  }

  const path = `signatures/user-${actor.id}.png`;

  await putPrivate(path, bytes, 'image/png');
  await db.users.update({ where: { id: id(actor.id) }, data: { signature_path: path, updated_at: now() } });

  return { message: 'Signature saved.' };
}

export async function removeSignature(actor: Actor): Promise<{ message: string }> {
  const user = await db.users.findUnique({ where: { id: id(actor.id) }, select: { signature_path: true } });

  if (user?.signature_path) {
    await deletePrivate(user.signature_path);
    await db.users.update({ where: { id: id(actor.id) }, data: { signature_path: null, updated_at: now() } });
  }

  return { message: 'Signature removed.' };
}

/** 32 random letters and digits, like Laravel's `Str::random(32)`. */
function randomCode(): string {
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
  const bytes = randomBytes(64);
  let code = '';

  for (const byte of bytes) {
    // 248 = 62 * 4: reject the tail so every character is equally likely.
    if (byte < 248) {
      code += alphabet[byte % 62];
    }

    if (code.length === 32) {
      return code;
    }
  }

  return code + randomCode().slice(0, 32 - code.length);
}

export const BADGE_PREFIX = 'SPDSTAFF:';

/** The badge QR's payload — an opaque secret, deliberately not a URL. */
export async function badgePayload(actor: Actor): Promise<string> {
  const user = await db.users.findUnique({ where: { id: id(actor.id) }, select: { identity_code: true } });
  let code = user?.identity_code ?? null;

  if (!code) {
    code = randomCode();
    await db.users.update({ where: { id: id(actor.id) }, data: { identity_code: code, updated_at: now() } });
  }

  return `${BADGE_PREFIX}${code}`;
}

/** Reissue the badge (lost card): the previous printed badge stops working. */
export async function regenerateBadge(actor: Actor): Promise<{ message: string }> {
  await db.users.update({ where: { id: id(actor.id) }, data: { identity_code: randomCode(), updated_at: now() } });
  await logActivity({ description: 'Reissued their staff badge code', causerId: actor.id });

  return { message: 'New badge issued — print it and destroy the old card.' };
}
