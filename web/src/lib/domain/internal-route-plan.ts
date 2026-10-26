/**
 * The route an internal request takes (client-safe: no database, so the filing
 * form draws the same route the server will create).
 *
 * The filer chooses one of two ways:
 *
 *  - **Through Action Mamamayan and the Mayor** (the official process, and the
 *    default): Action Mamamayan validates it, then the Office of the Mayor
 *    approves it and, in doing so, chooses the office it goes to next. No
 *    destination is picked at filing.
 *  - **Straight to an office**: the filer picks the office (the Office of the
 *    Mayor included) and the request goes there directly.
 *
 * Past the queued route, whichever office holds it may pass it on to another
 * office, or mark it done. The mandatory offices are named by code; change the
 * list to change the route. Requests already in progress keep their route.
 */
export const MANDATORY_ROUTE_CODES = ['AM', 'OM'] as const;

/** Action Mamamayan validates; it is never a request's destination. */
export const VALIDATING_OFFICE_CODE = 'AM';

/** The Office of the Mayor chooses where a request sent through it goes next. */
export const ROUTING_OFFICE_CODE = 'OM';

export const ROUTE_THROUGH = 'through';
export const ROUTE_DIRECT = 'direct';

export type RouteMode = typeof ROUTE_THROUGH | typeof ROUTE_DIRECT;

export type RouteOffice = { id: number; name: string; code: string };

export function isRouteMode(value: unknown): value is RouteMode {
  return value === ROUTE_THROUGH || value === ROUTE_DIRECT;
}

/**
 * The offices queued at filing, in order.
 *
 * - Straight to an office: just that office.
 * - Through Action Mamamayan and the Mayor: the mandatory offices, in order.
 *   Action Mamamayan filing skips its own place; the Office of the Mayor always
 *   stays, even when it filed, because it is the office that chooses where the
 *   request goes next.
 *
 * `mandatory` are the mandatory offices that exist and are active, in route
 * order; one that is missing is left out rather than blocking every filing.
 */
export function planRoute(
  mandatory: RouteOffice[],
  filingOfficeId: number | null,
  mode: RouteMode,
  destination: RouteOffice | null,
): RouteOffice[] {
  if (mode === ROUTE_DIRECT) {
    return destination ? [destination] : [];
  }

  return mandatory.filter(
    (office) => office.id !== filingOfficeId || office.code === ROUTING_OFFICE_CODE,
  );
}

/** Whether an office may be chosen as a request's destination. */
export function isDestination(office: { code: string }): boolean {
  return office.code !== VALIDATING_OFFICE_CODE;
}
