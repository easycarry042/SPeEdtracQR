'use client';

import { useCallback, useRef } from 'react';
import { useRouter } from 'next/navigation';
import { useConfirm } from '@/components/confirm-dialog';
import type { FormDepartment } from '@/lib/domain/internal-request-form';
import { RequestModal } from '../request-modal';
import { FilingForm } from './form';

/**
 * The filing form in a modal over the Internal page (`/requests?file=1`), the
 * same way an internal request opens there. Closing goes back to the queue as
 * it was, but once anything has been typed or uploaded it asks first, so a
 * stray click on the backdrop never throws away a half-filled request.
 */
export function FilingModal({
  closeHref,
  ...form
}: {
  closeHref: string;
  departments: FormDepartment[];
  mandatory: FormDepartment[];
  officeId: number | null;
  officeName: string;
  officeCode: string | null;
}) {
  const router = useRouter();
  const confirm = useConfirm();
  const dirty = useRef(false);

  const mayClose = useCallback(async (): Promise<boolean> => {
    if (!dirty.current) {
      return true;
    }

    return confirm({
      title: 'Discard this request?',
      message: 'What you have entered and the scanned document will not be kept.',
      confirmLabel: 'Discard',
      tone: 'danger',
    });
  }, [confirm]);

  const onDirtyChange = useCallback((value: boolean) => {
    dirty.current = value;
  }, []);

  return (
    <RequestModal closeHref={closeHref} labelledBy="file-request-title" beforeClose={mayClose}>
      <div className="dm-card dm-fixed dm-fit ff-modal-card mx-auto w-full max-w-[940px]">
        <FilingForm
          {...form}
          onDirtyChange={onDirtyChange}
          onClose={() => {
            void mayClose().then((ok) => {
              if (ok) {
                router.replace(closeHref, { scroll: false });
              }
            });
          }}
        />
      </div>
    </RequestModal>
  );
}
