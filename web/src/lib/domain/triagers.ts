import { ROLE_SUPERVISOR, ROLE_SUPER_ADMIN, usersWithRole } from '@/lib/auth/rbac';

export type Triager = { id: number; name: string; email: string; department_id: number | null };

/**
 * Who gets belled about a new request needing triage.
 *
 * Port of `DocumentEvent::departmentTriagers`. Super admins are deliberately NOT
 * belled for every new citizen request — it goes to the handling office's
 * supervisors, and only falls back to super admins when that office has no
 * active supervisor. A request with no office at all is every triager's to pick
 * up, so it fans out to all supervisors.
 */
export async function departmentTriagers(
  departmentId: number | null,
  excludeUserId: number | null = null,
): Promise<Triager[]> {
  const exclude = (rows: Triager[]): Triager[] =>
    excludeUserId === null ? rows : rows.filter((row) => row.id !== excludeUserId);

  if (!departmentId) {
    return exclude(await supervisors());
  }

  const officeSupervisors = await usersWithRole(ROLE_SUPERVISOR, departmentId);

  // Only when the office has nobody to triage does this escalate upward.
  return exclude(officeSupervisors.length > 0 ? officeSupervisors : await superAdmins());
}

/** Every supervisor, org-wide. */
export async function supervisors(): Promise<Triager[]> {
  return usersWithRole(ROLE_SUPERVISOR);
}

export async function superAdmins(): Promise<Triager[]> {
  return usersWithRole(ROLE_SUPER_ADMIN);
}
