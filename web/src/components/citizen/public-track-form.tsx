'use client';

import { useId, useRef, useState } from 'react';
import { routes } from '@/lib/routes';
import { QrCamera } from '@/components/qr-camera';
import {
  FOREIGN_CODE_MESSAGE,
  decodeImage,
  describe,
  extractTracking,
  imageFromDrop,
  scanFromScreen,
  start,
  stop,
} from '@/lib/client/speed-qr';

/**
 * Look up an internal request by its QR or number — no account needed. On the
 * staff sign-in page ("Track a request") and on the tracker's own page.
 *
 * Three ways in: type the number, scan the QR with the camera, or choose/drop a
 * picture of the QR. Any of them submits the number to the internal tracker.
 */
export function PublicTrackForm({
  heading = false,
  number = '',
}: {
  heading?: boolean;
  number?: string;
}) {
  const readerId = useId().replace(/:/g, '');
  const form = useRef<HTMLFormElement>(null);
  const input = useRef<HTMLInputElement>(null);
  const [scanning, setScanning] = useState(false);
  const [reading, setReading] = useState(false);
  const [error, setError] = useState('');

  /** Any QR this system issued — but only internal requests are tracked here. */
  const use = (decodedText: string): void => {
    const tracking = extractTracking(decodedText);

    if (!tracking) {
      setError(FOREIGN_CODE_MESSAGE);

      return;
    }

    if (input.current) {
      input.current.value = tracking;
    }

    form.current?.submit();
  };

  const toggleScan = async (): Promise<void> => {
    setError('');

    if (scanning) {
      await stop();
      setScanning(false);

      return;
    }

    setScanning(true);
    await start(
      readerId,
      (decodedText) => {
        void stop();
        setScanning(false);
        use(decodedText);
      },
      (cameraError) => {
        setScanning(false);
        setError(
          `${describe(cameraError)} You can choose a picture of the QR instead, or type the number.`,
        );
      },
    );
  };

  const readFile = async (file: File | undefined | null): Promise<void> => {
    if (!file) {
      return;
    }

    setError('');
    setReading(true);

    try {
      use(await decodeImage(file));
    } catch (e) {
      setError(e instanceof Error ? e.message : 'That picture could not be read.');
    } finally {
      setReading(false);
    }
  };

  /** Capture the tab / window showing the QR, then box it. */
  const snip = async (): Promise<void> => {
    if (scanning) {
      await toggleScan();
    }

    setError('');

    try {
      const decoded = await scanFromScreen('any');

      if (decoded !== null) {
        use(decoded);
      }
    } catch (e) {
      setError(
        e instanceof Error && e.message
          ? e.message
          : 'No QR code was found in that box. Drag it around the whole QR.',
      );
    }
  };

  return (
    <div>
      {heading ? (
        <>
          <h2 className="auth-heading">Track a request</h2>
          <p className="auth-subheading">
            Scan an internal request&apos;s QR, or type its number, to see which office has it and
            who is handling it. No account needed.
          </p>
        </>
      ) : null}

      <form ref={form} method="GET" action={routes.internalTrack()} className="auth-form">
        <div className="form-group">
          <label htmlFor="trackNumber" className="form-label">
            Internal request number
          </label>
          <div className="field-shell">
            <span className="field-icon" aria-hidden="true">
              <svg fill="none" stroke="currentColor" strokeWidth={1.9} viewBox="0 0 24 24">
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 18h2v2h-2zM14 18h2M18 14h2"
                />
              </svg>
            </span>
            <input
              id="trackNumber"
              ref={input}
              type="text"
              name="number"
              defaultValue={number}
              required
              autoComplete="off"
              spellCheck={false}
              autoCapitalize="characters"
              placeholder="INT-20261019-AB12CD"
              className="form-input form-input-with-icon mono uppercase"
            />
          </div>
        </div>

        <div className="it-actions">
          <button type="button" className="it-secondary" onClick={() => void toggleScan()}>
            <svg fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24" aria-hidden="true">
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M7 12h10"
              />
            </svg>
            <span>{scanning ? 'Stop camera' : 'Scan QR'}</span>
          </button>
          <button type="submit" className="auth-button !mt-0">
            Track
            <svg fill="none" stroke="currentColor" strokeWidth={2.4} viewBox="0 0 24 24" aria-hidden="true">
              <path strokeLinecap="round" strokeLinejoin="round" d="M5 12h14m-6-6 6 6-6 6" />
            </svg>
          </button>
        </div>

        {scanning ? (
          <div className="it-camera">
            <QrCamera id={readerId} className="mx-auto w-full max-w-[320px]" />
          </div>
        ) : null}

        {/* No camera? Snip the QR off the screen — the browser asks which tab or
            window shows it, then a box is dragged around the QR — or choose a
            saved picture of it. */}
        <div className="it-alt-row">
          <button type="button" className="it-image-link" onClick={() => void snip()} disabled={reading}>
            <span>{reading ? 'Reading the QR…' : 'Select the QR on screen'}</span>
          </button>
          <span aria-hidden="true">·</span>
          <label
            className="it-image-link"
            onDragOver={(event) => event.preventDefault()}
            onDrop={(event) => {
              event.preventDefault();
              void imageFromDrop(event.dataTransfer)
                .then(readFile)
                .catch((e: unknown) =>
                  setError(e instanceof Error ? e.message : 'That drop could not be read.'),
                );
            }}
          >
            <input
              type="file"
              accept="image/*"
              className="sr-only"
              onChange={(event) => {
                void readFile(event.target.files?.[0]);
                event.target.value = '';
              }}
            />
            Choose a picture of the QR
          </label>
        </div>

        {error ? (
          <p className="form-error" role="alert">
            {error}
          </p>
        ) : null}
      </form>
    </div>
  );
}
