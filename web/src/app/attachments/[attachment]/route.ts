import type { NextRequest } from 'next/server';
import { revalidatePath } from 'next/cache';
import { NextResponse } from 'next/server';
import { currentActor } from '@/lib/auth/actor';
import { attachmentFor, removeAttachment } from '@/lib/domain/attachments';
import { canAccessDocument } from '@/lib/domain/scope';
import { ActionError } from '@/lib/domain/status-actions';
import { fileResponse, forbidden, notFound, positiveId } from '@/lib/file-response';
import { readPrivate } from '@/lib/storage';

/**
 * Port of `AttachmentController@show` / `@destroy`.
 *
 * Attachments live on the private disk and are served only here, to a
 * signed-in staff member who may see the parent request — the old public
 * /storage/... URLs made citizen documents anonymously fetchable.
 */
export const dynamic = 'force-dynamic';

type Params = { params: Promise<{ attachment: string }> };

export async function GET(request: NextRequest, { params }: Params): Promise<Response> {
  const attachmentId = positiveId((await params).attachment);

  if (attachmentId === null) {
    return notFound();
  }

  const actor = await currentActor();

  if (!actor) {
    return forbidden();
  }

  const attachment = await attachmentFor(attachmentId);

  if (!attachment) {
    return notFound();
  }

  if (!canAccessDocument(attachment.document, actor)) {
    return forbidden();
  }

  const contents = await readPrivate(attachment.filePath);

  if (!contents) {
    return notFound();
  }

  return fileResponse(attachment.filePath, contents, {
    download: request.nextUrl.searchParams.get('download') === '1',
  });
}

/** Remove a wrongly placed attachment. */
export async function DELETE(_request: NextRequest, { params }: Params): Promise<Response> {
  const attachmentId = positiveId((await params).attachment);

  if (attachmentId === null) {
    return notFound();
  }

  try {
    const result = await removeAttachment(attachmentId, await currentActor());

    revalidatePath('/', 'layout');

    return NextResponse.json(result);
  } catch (error) {
    if (error instanceof ActionError) {
      return NextResponse.json({ message: error.message }, { status: error.status ?? 422 });
    }

    throw error;
  }
}
