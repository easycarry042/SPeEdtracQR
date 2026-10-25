'use client';

import { useEffect, useId, useState } from 'react';
import { createPortal } from 'react-dom';
import { QrCamera } from '@/components/qr-camera';
import {
  FOREIGN_CODE_MESSAGE,
  decodeImage,
  describe,
  openScanned,
  scanFromScreen,
  start,
  stop,
} from '@/lib/client/speed-qr';

/**
 * The header's Scan QR Code dialog.
 *
 * One scan receives: `openScanned` posts an INT- code to the custody endpoint,
 * which records the receipt if this person may receive it and then opens the
 * request. A citizen code goes to its tracking page.
 *
 * A camera scan is always allowed; a picture or a screen snip is only accepted
 * for soft copies — the server refuses them for paper requests, because taking
 * custody of a folder means having the folder.
 */
export function ScanDialog({ onClose }: { onClose: () => void }) {
  const readerId = useId().replace(/:/g, '');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    if (!mounted) return;

    void start(
      readerId,
      (decodedText) => {
        void stop();
        void use(decodedText, 'scan');
      },
      (cameraError) => setError(`${describe(cameraError)} You can choose a picture of the QR instead.`),
    );

    return () => {
      void stop();
    };
    // `use` is stable for the dialog's lifetime; re-running would restart the camera.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [mounted, readerId]);

  useEffect(() => {
    const onKey = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') {
        onClose();
      }
    };

    document.addEventListener('keydown', onKey);

    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  const use = async (decodedText: string, method: 'scan' | 'image' | 'screen'): Promise<void> => {
    setBusy(true);
    setError('');

    try {
      await openScanned(decodedText, method);
    } catch (e) {
      setError(e instanceof Error ? e.message : FOREIGN_CODE_MESSAGE);
      setBusy(false);
    }
  };

  if (!mounted) {
    return null;
  }

  return createPortal(
    <div
      className="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto bg-black/60 p-4 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
      aria-labelledby="headerScanTitle"
      onClick={(e) => {
        if (e.target === e.currentTarget) {
          onClose();
        }
      }}
    >
      <div className="relative my-auto w-full max-w-[420px] rounded-2xl bg-paper p-5 shadow-2xl">
        <div className="flex items-start justify-between gap-4">
          <div>
            <h2 id="headerScanTitle" className="text-lg font-bold text-ink">
              Scan a QR code
            </h2>
            <p className="mt-1 text-sm text-ink-soft">
              Point the camera at the QR on a claim slip or folder.
            </p>
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

        <div className="it-alt-row mt-4">
          <button
            type="button"
            className="it-image-link"
            disabled={busy}
            onClick={async () => {
              await stop();
              setError('');

              try {
                const decoded = await scanFromScreen('any');

                if (decoded !== null) {
                  await use(decoded, 'screen');
                }
              } catch (e) {
                setError(e instanceof Error ? e.message : 'No QR code was found in that box.');
              }
            }}
          >
            Select the QR on screen
          </button>
          <span aria-hidden="true">·</span>
          <label className="it-image-link">
            <input
              type="file"
              accept="image/*"
              className="sr-only"
              onChange={async (event) => {
                const file = event.target.files?.[0];
                event.target.value = '';

                if (!file) {
                  return;
                }

                setError('');
                setBusy(true);

                try {
                  await use(await decodeImage(file), 'image');
                } catch (e) {
                  setError(e instanceof Error ? e.message : 'That picture could not be read.');
                  setBusy(false);
                }
              }}
            />
            Choose a picture of the QR
          </label>
        </div>

        {error ? (
          <p className="form-error mt-3" role="alert">
            {error}
          </p>
        ) : null}
      </div>
    </div>,
    document.body,
  );
}
