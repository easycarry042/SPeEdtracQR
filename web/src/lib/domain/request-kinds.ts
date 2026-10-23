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
