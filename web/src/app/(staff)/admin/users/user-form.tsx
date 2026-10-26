'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState, useTransition } from 'react';
import { routes } from '@/lib/routes';
import { useConfirm } from '@/components/confirm-dialog';
import { saveUser } from '@/app/actions/admin';

/**
 * Add / edit an account — one form for both.
 *
 * On edit the password pair is optional and blank means "leave it alone": an
 * admin correcting a misspelled name must not be made to reset the person's
 * password to do it.
 *
 * Only active offices are offered. An inactive office still owns its history,
 * but putting someone new into one would route their work nowhere.
 */
export function UserForm({
  user,
  roles,
  offices,
}: {
  user: {
    id: number;
    name: string;
    email: string;
    role: string | null;
    departmentId: number | null;
  } | null;
  roles: string[];
  offices: Array<{ id: number; name: string; code: string }>;
}) {
  const router = useRouter();
  const confirm = useConfirm();
  const [pending, startTransition] = useTransition();
  const [name, setName] = useState(user?.name ?? '');
  const [email, setEmail] = useState(user?.email ?? '');
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [role, setRole] = useState(user?.role ?? '');
  const [office, setOffice] = useState(user?.departmentId === null || user === null ? '' : String(user.departmentId));
  const [show, setShow] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = async (event: React.FormEvent): Promise<void> => {
    event.preventDefault();

    // Asked before the transition, so the button does not read "Saving…" while
    // the question is still open.
    const confirmed = await confirm({
      title: user === null ? 'Create this account?' : 'Save changes to this account?',
      message:
        user === null
          ? `${name.trim() || 'This person'} can sign in as soon as it is created.`
          : 'Role and office changes take effect on their next page load.',
      confirmLabel: user === null ? 'Create account' : 'Save changes',
    });

    if (!confirmed) {
      return;
    }

    startTransition(async () => {
      const result = await saveUser(user?.id ?? null, {
        name,
        email,
        password: password === '' ? null : password,
        passwordConfirmation: confirmation === '' ? null : confirmation,
        role,
        departmentId: office === '' ? null : Number(office),
      });

      if (!result.ok) {
        setError(result.message);

        return;
      }

      router.push(routes.adminUsersIndex({ saved: user === null ? 'created' : 'updated' }));
    });
  };

  return (
    <section className="panel">
      <div className="ph">
        <h2>{user === null ? 'New account' : 'Account details'}</h2>
      </div>

      <form onSubmit={submit} className="pb space-y-5 p-6">
        {error ? (
          <p className="callout p-amber" role="alert">
            {error}
          </p>
        ) : null}

        <div>
          <label className="inv-field-label" htmlFor="userName">
            Full name <span className="text-status-red">*</span>
          </label>
          <input
            id="userName"
            type="text"
            className="field w-full"
            value={name}
            onChange={(event) => setName(event.target.value)}
            maxLength={255}
            required
            autoFocus
          />
        </div>

        <div>
          <label className="inv-field-label" htmlFor="userEmail">
            Email address <span className="text-status-red">*</span>
          </label>
          <input
            id="userEmail"
            type="email"
            className="field w-full"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            required
          />
          <p className="mt-1 text-[length:var(--fs-caption)] text-ink-soft">
            This is the address they sign in with, and where their notices are sent.
          </p>
        </div>

        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
          <div>
            <label className="inv-field-label" htmlFor="userPassword">
              {user === null ? (
                <>
                  Password <span className="text-status-red">*</span>
                </>
              ) : (
                'New password'
              )}
            </label>
            <input
              id="userPassword"
              type={show ? 'text' : 'password'}
              className="field w-full"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              autoComplete="new-password"
              required={user === null}
            />
            {user === null ? (
              <p className="mt-1 text-[length:var(--fs-caption)] text-ink-soft">
                At least 8 characters.
              </p>
            ) : (
              <p className="mt-1 text-[length:var(--fs-caption)] text-ink-soft">
                Leave blank to keep their current password.
              </p>
            )}
          </div>

          <div>
            <label className="inv-field-label" htmlFor="userPasswordConfirm">
              {user === null ? 'Confirm password' : 'Confirm new password'}
            </label>
            <input
              id="userPasswordConfirm"
              type={show ? 'text' : 'password'}
              className="field w-full"
              value={confirmation}
              onChange={(event) => setConfirmation(event.target.value)}
              autoComplete="new-password"
              required={user === null}
            />
            <label className="mt-2 flex items-center gap-2 text-[length:var(--fs-caption)] text-ink-soft">
              <input type="checkbox" checked={show} onChange={(event) => setShow(event.target.checked)} />
              Show passwords
            </label>
          </div>
        </div>

        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
          <div>
            <label className="inv-field-label" htmlFor="userRole">
              Role <span className="text-status-red">*</span>
            </label>
            <select
              id="userRole"
              className="field w-full"
              value={role}
              onChange={(event) => setRole(event.target.value)}
              required
            >
              <option value="">Select a role…</option>
              {roles.map((name) => (
                <option key={name} value={name}>
                  {name
                    .split('_')
                    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
                    .join(' ')}
                </option>
              ))}
            </select>
            <p className="mt-1 text-[length:var(--fs-caption)] text-ink-soft">
              Staff carry and advance the work · Supervisors triage and assign for their office ·
              Super admins are org-wide.
            </p>
          </div>

          <div>
            <label className="inv-field-label" htmlFor="userOffice">
              Office
            </label>
            <select
              id="userOffice"
              className="field w-full"
              value={office}
              onChange={(event) => setOffice(event.target.value)}
            >
              <option value="">None (org-wide)</option>
              {offices.map((entry) => (
                <option key={entry.id} value={entry.id}>
                  {entry.name} ({entry.code})
                </option>
              ))}
            </select>
            <p className="mt-1 text-[length:var(--fs-caption)] text-ink-soft">
              Everything but a super admin is scoped to their office. Only active offices are
              listed.
            </p>
          </div>
        </div>

        <div className="flex items-center justify-end gap-3 border-t border-hairline pt-4">
          <Link href={routes.adminUsersIndex()} className="cr-btn">
            Cancel
          </Link>
          <button type="submit" className="cr-btn cr-btn-primary" disabled={pending}>
            {user === null ? 'Create account' : 'Save changes'}
          </button>
        </div>
      </form>
    </section>
  );
}
