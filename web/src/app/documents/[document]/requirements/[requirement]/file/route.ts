import type { NextRequest } from 'next/server';
import { currentActor } from '@/lib/auth/actor';
import { canAccessDocument } from '@/lib/domain/scope';
import { markOpened, requirementFileFor } from '@/lib/domain/requirement-review';
import { fileResponse, forbidden, notFound, positiveId } from '@/lib/file-response';
import { readPrivate } from '@/lib/storage';

/**
 * A citizen's requirement upload, streamed from the private disk to staff who
 * may see the request — never anonymously fetchable.
 *
 * `?open=1` is a reviewer opening the file to read it (the Review modal's View
 * link and thumbnail). The first such open by someone who can check it is what
 * unlocks "Looks good"; thumbnails load without it, so a glance at the list
 * does not count as having looked.
 */
export const dynamic = 'force-dynamic';

export async function GET(
  request: NextRequest,
  { params }: { params: Promise<{ document: string; requirement: string }> },
): Promise<Response> {
  const { document, requirement } = await params;
  const documentId = positiveId(document);
  const requirementId = positiveId(requirement);

  if (documentId === null || requirementId === null) {
    return notFound();
  }

  const actor = await currentActor();

  if (!actor) {
    return forbidden();
  }

  const file = await requirementFileFor(documentId, requirementId);

  if (!file) {
    return notFound();
  }

  if (!canAccessDocument({ created_by: file.createdBy, assigned_to: file.assignedTo }, actor)) {
    return forbidden();
  }

  const contents = await readPrivate(file.path);

  if (!contents) {
    return notFound();
  }

  if (request.nextUrl.searchParams.get('open') === '1') {
    await markOpened(documentId, requirementId, actor);
  }

  return fileResponse(file.path, contents);
}
