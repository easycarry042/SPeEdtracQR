import type { NextRequest } from 'next/server';
import { NextResponse } from 'next/server';
import { db } from '@/lib/db';
import { formatNaive, forHumans } from '@/lib/time';
import { citizenTrackingUrl, statusOf } from '@/lib/domain/document';
import { label as statusLabel } from '@/lib/domain/document-status';
import { citizenFacing } from '@/lib/domain/scope';

/**
 * JSON lookup for the public track page.
 *
 * Validates the format server-side, then reports one of three outcomes:
 * invalid format, no record found, or the found request's public status
 * summary. Internal office-to-office requests are never resolved here — the
 * public must not see them, so they come back as "not found" like any unknown
 * number rather than as a different error that would confirm they exist.
 */
const TRACKING_PATTERN = /^(SPD|INT)-\d{8}-[0-9A-Z]{6}$/;

export async function GET(request: NextRequest) {
  const tracking = (request.nextUrl.searchParams.get('tracking_number') ?? '')
    .trim()
    .toUpperCase();

  if (tracking === '') {
    return NextResponse.json(
      {
        status: 'invalid',
        title: 'Invalid tracking number.',
        message: 'Please enter a tracking number.',
      },
      { status: 422 },
    );
  }

  if (!TRACKING_PATTERN.test(tracking)) {
    return NextResponse.json(
      {
        status: 'invalid',
        title: 'Invalid tracking number.',
        message:
          'Please enter a valid tracking number using the correct format (e.g. SPD-20260728-K7M9Q2).',
      },
      { status: 422 },
    );
  }

  const document = await db.documents.findFirst({
    where: { tracking_number: tracking, deleted_at: null, ...citizenFacing },
    select: {
      tracking_number: true,
      document_type: true,
      status: true,
      created_at: true,
      updated_at: true,
      department: { select: { name: true } },
    },
  });

  if (!document) {
    return NextResponse.json(
      {
        status: 'not_found',
        title: 'No record found.',
        message:
          "We couldn't find a request with that tracking number. Please check your tracking number and try again.",
      },
      { status: 404 },
    );
  }

  const stage = statusOf(document);

  return NextResponse.json({
    status: 'found',
    data: {
      tracking_number: document.tracking_number,
      document_type: document.document_type,
      status: stage,
      status_label: statusLabel(stage),
      // The office, never the person — this is a public endpoint.
      department: document.department?.name ?? null,
      submitted_at: document.created_at ? formatNaive(document.created_at, 'M j, Y') : null,
      updated_at: document.updated_at ? forHumans(document.updated_at) : null,
      url: citizenTrackingUrl(document.tracking_number),
    },
  });
}
