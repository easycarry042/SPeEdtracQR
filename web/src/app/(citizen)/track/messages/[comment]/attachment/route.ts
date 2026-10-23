import type { NextRequest } from 'next/server';
import { isVerified } from '@/lib/domain/citizen-thread-access';
import { citizenAttachment } from '@/lib/domain/citizen-thread';
import { fileResponse, forbidden, notFound, positiveId } from '@/lib/file-response';
import { readPrivate } from '@/lib/storage';

/**
 * Port of `CitizenMessageController@attachment` — a file on a citizen-visible
 * message, downloaded only by the browser that confirmed the citizen.
 */
export const dynamic = 'force-dynamic';

export async function GET(
  _request: NextRequest,
  { params }: { params: Promise<{ comment: string }> },
): Promise<Response> {
  const commentId = positiveId((await params).comment);

  if (commentId === null) {
    return notFound();
  }

  const attachment = await citizenAttachment(commentId);

  if (!attachment) {
    return notFound();
  }

  if (!(await isVerified(attachment.trackingNumber))) {
    return forbidden();
  }

  const contents = await readPrivate(attachment.path);

  if (!contents) {
    return notFound();
  }

  return fileResponse(attachment.name, contents, { download: true });
}
