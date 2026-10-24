'use client';

import { useId, useState } from 'react';
import { useRouter } from 'next/navigation';
import { QrCamera } from '@/components/qr-camera';
import { routes } from '@/lib/routes';
import {
  FOREIGN_CODE_MESSAGE,
  TRACKING_RE,
  decodeImage,
  describe,
  extractTracking,
  imageFromDrop,
  start,
  stop,
} from '@/lib/client/speed-qr';

type LookupResult =
  | { status: 'invalid' | 'not_found'; title: string; message: string }
  | {
      status: 'found';
      data: {
        tracking_number: string;
        document_type: string;
        status_label: string;
        department: string | null;
        submitted_at: string | null;
        updated_at: string | null;
        url: string;
      };
    };

/**
 * The lookup form. Validates the number's shape before asking the server, then
 * reports one of three outcomes: invalid format, no record found, or the found
 * request's public summary (which links through to its tracking page).
 *
 * An internal (INT-) request is never resolved here — the public must not see
 * them — so it comes back as "not found" like any unknown number.
 */
export function TrackLookup({ notFound }: { notFound: string | null }) {
  const router = useRouter();
  const readerId = useId().replace(/:/g, '');
  const [value, setValue] = useState(notFound ?? '');
  const [scanning, setScanning] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [result, setResult] = useState<LookupResult | null>(
    notFound
      ? {
          status: 'not_found',
          title: 'No record found.',
          message: `We couldn't find a request with the tracking number “${notFound}”. Please check it and try again.`,
        }
      : null,
  );

  const lookup = async (tracking: string): Promise<void> => {
    setError('');
    setBusy(true);

    try {
      const response = await fetch(`${routes.trackLookup()}?tracking_number=${encodeURIComponent(tracking)}`, {
        headers: { Accept: 'application/json' },
      });

      const payload = (await response.json()) as LookupResult;
      setResult(payload);

      // A hit goes straight to the tracking page — the summary card is only
      // there for the moment before the navigation lands.
      if (payload.status === 'found') {
        router.push(payload.data.url);
      }
    } catch {
      setError('We could not reach the server. Check your connection and try again.');
    } finally {
      setBusy(false);
    }
  };

  const use = (decodedText: string): void => {
    const tracking = extractTracking(decodedText);

    if (!tracking) {
      setError(FOREIGN_CODE_MESSAGE);

      return;
    }

    setValue(tracking);
    void lookup(tracking);
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
        setError(`${describe(cameraError)} You can type the number instead.`);
      },
    );
  };

  const readFile = async (file: File | undefined | null): Promise<void> => {
    if (!file) {
      return;
    }

    setError('');
    setBusy(true);

    try {
      use(await decodeImage(file));
    } catch (e) {
      setError(e instanceof Error ? e.message : 'That picture could not be read.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="cz-card">
      <div className="cz-card-body">
        <form
          onSubmit={(event) => {
            event.preventDefault();
            const tracking = value.trim().toUpperCase();

            // Real numbers are PREFIX-YYYYMMDD-XXXXXX. Checking the shape here
            // saves a round trip and gives the same message the server would.
            if (!TRACKING_RE.test(tracking)) {
              setResult({
                status: 'invalid',
                title: 'Invalid tracking number.',
                message:
                  'Please enter a valid tracking number using the correct format (e.g. SPD-20260728-K7M9Q2).',
              });

              return;
            }

            void lookup(tracking);
          }}
        >
          <div className="form-group">
            <label htmlFor="tracking" className="form-label">
              Tracking number
            </label>
            <input
              id="tracking"
              type="text"
              value={value}
              onChange={(event) => {
                setValue(event.target.value);
                setResult(null);
              }}
              required
              autoComplete="off"
              spellCheck={false}
              autoCapitalize="characters"
              placeholder="SPD-20260728-K7M9Q2"
              className="form-input mono uppercase"
            />
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
            <button type="submit" className="cz-btn cz-btn-primary !mt-0" disabled={busy}>
              {busy ? 'Checking…' : 'Track'}
            </button>
          </div>

          {scanning ? (
            <div className="it-camera mt-4">
              <QrCamera id={readerId} className="mx-auto w-full max-w-[320px]" />
            </div>
          ) : null}

          <div className="it-alt-row">
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

        {result && result.status !== 'found' ? (
          <div className="it-result mt-6" role="status">
            <p className="it-result-head">{result.title}</p>
            <p className="mt-1 text-[15px] text-[#4b5a50]">{result.message}</p>
          </div>
        ) : null}

        {result?.status === 'found' ? (
          <div className="it-result mt-6">
            <p className="it-result-head">{result.data.document_type}</p>
            <dl className="it-facts mt-2">
              <div>
                <dt>Status</dt>
                <dd>{result.data.status_label}</dd>
              </div>
              <div>
                <dt>Office</dt>
                <dd>{result.data.department ?? 'Not yet routed'}</dd>
              </div>
              <div>
                <dt>Filed</dt>
                <dd>{result.data.submitted_at ?? '—'}</dd>
              </div>
            </dl>
          </div>
        ) : null}
      </div>
    </div>
  );
}
