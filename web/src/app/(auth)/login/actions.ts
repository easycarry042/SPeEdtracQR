'use server';

import { redirect } from 'next/navigation';
import { z } from 'zod';
import { db } from '@/lib/db';
import { toId } from '@/lib/db';
import { now } from '@/lib/time';
import { verifyPassword } from '@/lib/auth/password';
import { createSession, destroySession, readSession } from '@/lib/auth/session';
import { actorById } from '@/lib/auth/actor';
import { logActivity, SUBJECT } from '@/lib/activity';
import { landingFor, routes } from '@/lib/routes';

/**
 * `FormData.get()` returns `null` for a field that was not rendered, not
 * `undefined` — so optional fields have to be `.nullish()`, or an absent
 * `next` fails validation and the form reports "Expected string, received
 * null" instead of signing the user in.
 */
const schema = z.object({
  email: z.string().trim().min(1, 'Please enter your email address.').email('Enter a valid email address.'),
  password: z.string().min(1, 'Please enter your password.'),
  next: z.string().nullish(),
});

export type LoginState = { error?: string } | undefined;

/**
 * Signs a staff member in.
 *
 * Passwords are Laravel's own bcrypt hashes, verified unchanged — nobody has to
 * reset anything at cutover. Archived (`deleted_at`) and deactivated accounts
 * are refused: SoftDeletes powers "archive", and an archived user must not be
 * able to sign in.
 */
export async function login(_state: LoginState, formData: FormData): Promise<LoginState> {
  const parsed = schema.safeParse({
    email: formData.get('email'),
    password: formData.get('password'),
    next: formData.get('next'),
  });

  if (!parsed.success) {
    return { error: parsed.error.issues[0]?.message ?? 'Check the details you entered.' };
  }

  const { email, password } = parsed.data;

  const user = await db.users.findFirst({
    // Postgres compares case-sensitively; MySQL's collation did not, and people
    // type their address however they like.
    where: { email: { equals: email, mode: 'insensitive' }, deleted_at: null },
    select: { id: true, password: true, is_active: true },
  });

  // One message for a missing account, a wrong password and a disabled one, so
  // the form cannot be used to discover which addresses have accounts.
  const GENERIC = { error: 'These credentials do not match our records.' };

  if (!user || !(await verifyPassword(password, user.password))) {
    return GENERIC;
  }

  if (!user.is_active) {
    return { error: 'This account has been deactivated. Ask an administrator to restore it.' };
  }

  const userId = toId(user.id);

  await createSession(userId);
  await db.users.update({ where: { id: user.id }, data: { last_active_at: now() } });

  // Laravel's login listener wrote this line; it is auto-discovered there, so
  // do not also register it elsewhere or the audit log doubles up.
  await logActivity({
    description: 'Logged in',
    subjectType: SUBJECT.User,
    subjectId: userId,
    causerId: userId,
  });

  const actor = await actorById(userId);
  const intended = parsed.data.next;

  // Only same-site paths, so `?next=` cannot be used as an open redirect.
  const destination =
    intended && intended.startsWith('/') && !intended.startsWith('//')
      ? intended
      : actor
        ? landingFor(actor)
        : routes.welcome();

  redirect(destination);
}

export async function logout(): Promise<void> {
  const session = await readSession();

  if (session) {
    await logActivity({
      description: 'Logged out',
      subjectType: SUBJECT.User,
      subjectId: session.sub,
      causerId: session.sub,
    });
  }

  await destroySession();
  redirect(routes.welcome());
}
