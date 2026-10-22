/**
 * What a resource IS (`resources.kind`, `App\Models\Resource::KIND_*`).
 *
 * A facility and a counted pile are measured differently — one venue reserved
 * by time against stock shared between overlapping borrowings — so this is the
 * value every lending rule branches on.
 *
 * It lives here, apart from `lending`, because the catalog form has to offer
 * the choice in the browser and `lending` reaches the database.
 */

/** One of a thing — the gymnasium. Two approved reservations may not overlap. */
export const KIND_FACILITY = 'facility';

/** A countable pile — chairs, tents. Overlapping borrowings share the stock. */
export const KIND_EQUIPMENT = 'equipment';

export const RESOURCE_KIND_VALUES = [KIND_EQUIPMENT, KIND_FACILITY] as const;

export type ResourceKind = (typeof RESOURCE_KIND_VALUES)[number];
