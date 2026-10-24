import { db, id } from '@/lib/db';
import { now } from '@/lib/time';

/**
 * The two panels that outlived the retired Services report.
 *
 * The report itself is gone (`/reports/services` redirects), but the command
 * center still draws its requirement-checklist health and, for one office, its
 * bookable resources — so those two queries live on here rather than in a page.
 */
export type RequirementStats = {
  mandatory_total: number;
  submitted: number;
  verified: number;
  submitted_pct: number;
  verified_pct: number;
};

export type ResourceUseRow = {
  name: string;
  is_active: boolean;
  total: number;
  approved: number;
  pending: number;
  upcoming: number;
};

/** The request-type names an office owns — how its requirements are identified. */
async function typeNames(departmentId: number): Promise<string[]> {
  const types = await db.request_types.findMany({
    where: { department_id: id(departmentId) },
    select: { name: true },
  });

  return types.map((type) => type.name);
}

/**
 * Requirement checklist health across all document requests: how much of the
 * mandatory paperwork citizens have actually submitted, and staff verified.
 */
export async function requirementStats(
  departmentId: number | null = null,
): Promise<RequirementStats> {
  const scope = departmentId
    ? { document: { document_type: { in: await typeNames(departmentId) } } }
    : {};

  const where = { is_mandatory: true, ...scope };

  const [mandatory_total, submitted, verified] = await Promise.all([
    db.document_requirements.count({ where }),
    db.document_requirements.count({ where: { ...where, uploaded_file_path: { not: null } } }),
    db.document_requirements.count({ where: { ...where, verified_at: { not: null } } }),
  ]);

  const pct = (part: number): number =>
    mandatory_total > 0 ? Math.round((part / mandatory_total) * 100) : 0;

  return {
    mandatory_total,
    submitted,
    verified,
    submitted_pct: pct(submitted),
    verified_pct: pct(verified),
  };
}

/**
 * How much each bookable resource is actually used.
 *
 * Scoped to an office, only the resources that office's own request types
 * reserve are listed — the city-wide view lives on the Resources page instead.
 */
export async function bookingsByResource(
  departmentId: number | null = null,
): Promise<ResourceUseRow[]> {
  let resourceFilter = {};

  if (departmentId) {
    const types = await db.request_types.findMany({
      where: { department_id: id(departmentId), resource_id: { not: null } },
      select: { resource_id: true },
      distinct: ['resource_id'],
    });

    resourceFilter = { id: { in: types.map((type) => type.resource_id!) } };
  }

  const resources = await db.resources.findMany({
    where: resourceFilter,
    select: {
      name: true,
      is_active: true,
      bookings: { select: { status: true, ends_at: true } },
    },
  });

  const at = now();

  return resources
    .map((resource): ResourceUseRow => {
      const { bookings } = resource;

      return {
        name: resource.name,
        is_active: resource.is_active,
        total: bookings.length,
        approved: bookings.filter((booking) => booking.status === 'approved').length,
        pending: bookings.filter((booking) => booking.status === 'pending').length,
        // Still to come: approved and not yet finished.
        upcoming: bookings.filter(
          (booking) => booking.status === 'approved' && booking.ends_at >= at,
        ).length,
      };
    })
    .sort((a, b) => b.total - a.total || a.name.localeCompare(b.name));
}
