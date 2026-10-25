/**
 * The route every internal request takes (client-safe: no database, so the
 * filing form draws the same route the server will create).
 *
 * Every request first goes to Action Mamamayan, which validates and confirms
 * it, then to the Office of the Mayor, and only then to the office it was
 * filed for. These offices are named by code; change the list to change the
 * route. Requests already in progress keep the route they were filed with.
 */
export const MANDATORY_ROUTE_CODES = ['AM', 'OM'] as const;

/** Action Mamamayan validates; it is never a request's destination. */
export const VALIDATING_OFFICE_CODE = 'AM';

export type RouteOffice = { id: number; name: string; code: string };

/**
 * The offices a request passes through, in order.
 *
 * - The mandatory offices come first, in order, then the destination.
 * - The filing office skips its own place on the route (Action Mamamayan or
 *   the Office of the Mayor filing their own request).
 * - A destination already on the route is not repeated (the Office of the
 *   Mayor as destination ends the route there).
 *
 * `mandatory` are the mandatory offices that exist and are active, in route
 * order; one that is missing is left out rather than blocking every filing.
 */
export function planRoute(
  mandatory: RouteOffice[],
  filingOfficeId: number | null,
  destination: RouteOffice,
): RouteOffice[] {
  const route = mandatory.filter((office) => office.id !== filingOfficeId);

  if (!route.some((office) => office.id === destination.id)) {
    route.push(destination);
  }

  return route;
}

/** Whether an office may be chosen as a request's destination. */
export function isDestination(office: { code: string }): boolean {
  return office.code !== VALIDATING_OFFICE_CODE;
}
