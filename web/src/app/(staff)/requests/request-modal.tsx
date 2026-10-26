'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';

/**
 * The Internal page's request modal: the request opens over the queue instead
 * of on a page of its own, the way citizen requests open on the Dashboard.
 *
 * The open request lives in the URL (`?open=…`), so a reload, a shared link or
 * the back button all land on the same view. Closing (✕, Escape, or a click on
 * the backdrop) replaces the URL with `closeHref`, the queue as it was.
 */
export function RequestModal({
  closeHref,
  labelledBy,
  children,
}: {
  closeHref: string;
  labelledBy: string;
  children: React.ReactNode;
}) {
  const router = useRouter();

  useEffect(() => {
    const close = (): void => router.replace(closeHref, { scroll: false });

    const onKey = (event: KeyboardEvent): void => {
      // Leave Escape to a dialog stacked on top (QR viewer, scanner, confirm).
      if (event.key === 'Escape' && !event.defaultPrevented && !stackedDialogOpen()) {
        close();
      }
    };

    const previous = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', onKey);

    return () => {
      document.body.style.overflow = previous;
      document.removeEventListener('keydown', onKey);
    };
  }, [closeHref, router]);

  return (
    <div
      className="request-modal fixed inset-0 z-50 overflow-y-auto bg-black/40 px-4 py-[27px]"
      role="dialog"
      aria-modal="true"
      aria-labelledby={labelledBy}
      onClick={(event) => {
        if (event.target === event.currentTarget) {
          router.replace(closeHref, { scroll: false });
        }
      }}
    >
      <div
        className="mx-auto w-full max-w-[1240px]"
        onClick={(event) => {
          if (event.target === event.currentTarget) {
            router.replace(closeHref, { scroll: false });
          }
        }}
      >
        {children}
      </div>
    </div>
  );
}

/** True while another dialog (QR viewer, camera, confirm, snip) sits on top. */
function stackedDialogOpen(): boolean {
  return (
    document.querySelector(
      '.request-modal [role="dialog"], body > [role="dialog"], body > [role="alertdialog"], .qr-snip',
    ) !== null
  );
}
