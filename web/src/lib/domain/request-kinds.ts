/**
 * What kind of thing a request type asks for — the four values on
 * `request_types.kind` (`App\Models\RequestType::KIND_*`).
 *
 * These live apart from `admin-catalog` because the setup form needs them in
 * the browser: the resource picker only appears for the two kinds that draw on
 * a resource. Importing them from the catalog service would drag Prisma into
 * the client bundle with them.
 */
export const REQUEST_KINDS = ['document', 'booking', 'equipment', 'service'] as const;

export type RequestKind = (typeof REQUEST_KINDS)[number];

/** Kinds that reserve or lend a resource, and so must point at one. */
export const RESOURCE_KINDS: string[] = ['booking', 'equipment'];

/** How each kind is described on the setup form. */
export const REQUEST_KIND_LABELS: Record<RequestKind, string> = {
  document: 'Document / Permit (produces a document)',
  booking: 'Facility reservation (reserves a place for a time)',
  equipment: 'Equipment borrowing (a quantity of items for a date)',
  service: 'Service / Production (make a quantity by a date)',
};

/**
 * How a request type treats the claiming date:
 *  - `optional`: facility reservations already hold a booked schedule; staff/supervisors
 *    may optionally set or clear a separate claiming date.
 *  - `required`: equipment borrowings, physical flag requisitions, and document pickups
 *    require a valid future/today claiming date before supervisor review.
 *  - `none`: pure services without counter pickup (Lei Making, Layout Request,
 *    Social Media Posting, Events Set-up, Program Assistance, Other Venue Request).
 */
export type ClaimDatePolicy = 'optional' | 'required' | 'none';

const OPTIONAL_FACILITY_NAMES = new Set([
  'city plaza reservation',
  'gateway park reservation',
]);

const NO_CLAIM_SERVICE_NAMES = new Set([
  'lei making',
  'layout request',
  'social media posting',
  'events set-up',
  'program assistance',
  'other venue request',
]);

export function claimDatePolicyFor(
  kind: string | null | undefined,
  documentType: string | null | undefined,
): ClaimDatePolicy {
  const k = (kind ?? '').trim().toLowerCase();
  const name = (documentType ?? '').trim().toLowerCase();

  if (k === 'booking') {
    return 'optional';
  }

  if (k === 'equipment' || name.includes('requisition') || k === 'document') {
    return 'required';
  }

  if (k === 'service') {
    return 'none';
  }

  if (OPTIONAL_FACILITY_NAMES.has(name)) {
    return 'optional';
  }

  if (NO_CLAIM_SERVICE_NAMES.has(name)) {
    return 'none';
  }

  return 'required';
}
