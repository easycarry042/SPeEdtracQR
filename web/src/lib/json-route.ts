import { NextResponse } from 'next/server';

/**
 * Laravel's validation-error JSON (`{ message, errors: { field: [..] } }`), so
 * the browser code reads failures the same way it did against the PHP app.
 */
export function invalid(field: string, message: string, status = 422): NextResponse {
  return NextResponse.json({ message, errors: { [field]: [message] } }, { status });
}

/** A JSON body, or an empty object when the request carried none or bad JSON. */
export async function jsonBody(request: Request): Promise<Record<string, unknown>> {
  try {
    const body = (await request.json()) as unknown;

    return body !== null && typeof body === 'object' ? (body as Record<string, unknown>) : {};
  } catch {
    return {};
  }
}

export const str = (value: unknown): string => (typeof value === 'string' ? value : '');
