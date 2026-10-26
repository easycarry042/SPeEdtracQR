'use client';

import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { QrCamera } from '@/components/qr-camera';

/**
 * The camera box for an internal request's scan, opened as a modal over the
 * page instead of growing inside the panel.
 *
 * The caller still owns the scan: it calls `SpeedQr.start(readerId, …)` as it
 * opens this (start waits for the box to mount) and `SpeedQr.stop()` in
 * `onClose`. Escape and a click on the backdrop close it.
 */
export function CameraModal({
  open,
  readerId,
  title = 'Scan the QR code',
  hint,
  error,
  onClose,
}: {
  open: boolean;
  readerId: string;
  title?: string;
  hint?: React.ReactNode;
  /** A refusal while the camera keeps reading (e.g. another request's QR). */
  error?: string | null;
  onClose: () => void;
}) {
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    if (!open) {
      return;
    }

    const onKey = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') {
        // Close the camera only, not the request modal underneath.
        event.preventDefault();
        event.stopPropagation();
        onClose();
      }
    };

    document.addEventListener('keydown', onKey, true);

    return () => document.removeEventListener('keydown', onKey, true);
  }, [open, onClose]);

  if (!open || !mounted) {
    return null;
  }

  return createPortal(
    <div
      className="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto bg-black/60 p-4 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
      aria-labelledby={`${readerId}-title`}
      onClick={(event) => {
        if (event.target === event.currentTarget) {
          onClose();
        }
      }}
    >
      <div className="relative my-auto w-full max-w-[420px] rounded-2xl bg-paper p-5 shadow-2xl">
        <div className="flex items-start justify-between gap-4">
          <div>
            <h2 id={`${readerId}-title`} className="text-lg font-bold text-ink">
              {title}
            </h2>
            {hint ? <p className="mt-1 text-sm text-ink-soft">{hint}</p> : null}
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close scanner"
            className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-ink-soft transition hover:bg-green-wash"
          >
            <svg fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24" className="h-5 w-5">
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 6l12 12M18 6 6 18" />
            </svg>
          </button>
        </div>

        <div className="mt-4">
          <QrCamera id={readerId} className="mx-auto w-full max-w-[320px]" />
        </div>

        {error ? (
          <p className="form-error mt-3" role="alert">
            {error}
          </p>
        ) : null}

        <div className="mt-4 flex justify-end">
          <button type="button" className="cr-btn" onClick={onClose}>
            Cancel scan
          </button>
        </div>
      </div>
    </div>,
    document.body,
  );
}
