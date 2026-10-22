import { db } from '@/lib/db';

/**
 * Shared option lists for the document submission form and edit form.
 * Port of `App\Support\DocumentFormOptions`.
 */

/** The built-in defaults, used when the admin catalog is empty. */
export function defaultCategoryOptions(): string[] {
  return [
    'Business Permit',
    'Barangay Clearance',
    'Building Permit',
    "Mayor's Permit",
    'Real Property Tax',
    'Birth Certificate Request',
    'Community Tax Certificate',
    'Other',
  ];
}

/**
 * Active document-kind request types, sourced from the admin-managed catalog.
 * Falls back to the built-in defaults when the catalog is empty or the table
 * isn't migrated yet (fresh installs / tests).
 */
export async function categoryOptions(): Promise<string[]> {
  try {
    const names = await db.request_types.findMany({
      where: { is_active: true, kind: 'document' },
      orderBy: [{ sort_order: 'asc' }, { name: 'asc' }],
      select: { name: true },
    });

    if (names.length > 0) {
      return names.map((row) => row.name);
    }
  } catch {
    // request_types not migrated yet — fall through to defaults.
  }

  return defaultCategoryOptions();
}
