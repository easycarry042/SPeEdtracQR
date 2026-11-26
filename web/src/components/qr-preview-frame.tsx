'use client';

import { useEffect, useState } from 'react';

type QrStatus = 'loading' | 'ready' | 'error';

function withQueryParam(url: string, key: string, value: string): string {
  const sep = url.includes('?') ? '&' : '?';
  return `${url}${sep}${encodeURIComponent(key)}=${encodeURIComponent(value)}`;
}

/**
 * Shared QR preview frame used in both internal `QrTile` and external `ReviewModal`.
 *
 * Displays a clear spinner while the QR PNG downloads (keeping `alt=""` so the
 * browser never flashes raw tracking-number text inside the frame), automatically
 * falls back to `/api/storage` on-the-fly regeneration once on error, and offers
 * a manual Retry action if the network drops completely.
 */
export function QrPreviewFrame({
  qrUrl,
  trackingNumber,
  className = '',
  crossOrigin,
  onReady,
  onLoadFailed,
}: {
  qrUrl: string | null;
  trackingNumber: string;
  className?: string;
  crossOrigin?: 'anonymous';
  onReady?: () => void;
  onLoadFailed?: () => void;
}) {
  const regenerateUrl = `/storage/qrcodes/${encodeURIComponent(trackingNumber)}.png?inline=1&regenerate=1`;
  const primaryUrl = qrUrl
    ? qrUrl.startsWith('/storage/')
      ? withQueryParam(qrUrl, 'inline', '1')
      : qrUrl
    : regenerateUrl;

  const [status, setStatus] = useState<QrStatus>('loading');
  const [attempt, setAttempt] = useState<0 | 1>(qrUrl ? 0 : 1);
  const [retrySeed, setRetrySeed] = useState(0);

  useEffect(() => {
    setStatus('loading');
    setAttempt(qrUrl ? 0 : 1);
    setRetrySeed(0);
  }, [qrUrl, trackingNumber]);

  const activeSrc =
    attempt === 0
      ? retrySeed > 0
        ? withQueryParam(primaryUrl, 't', String(retrySeed))
        : primaryUrl
      : retrySeed > 0
        ? withQueryParam(regenerateUrl, 't', String(retrySeed))
        : regenerateUrl;

  return (
    <div className={`dm-qr-frame relative flex items-center justify-center ${className}`}>
      {status !== 'error' ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img
          src={activeSrc}
          alt={status === 'ready' ? `QR code for ${trackingNumber}` : ''}
          crossOrigin={crossOrigin}
          className={
            status === 'ready' ? 'opacity-100 transition-opacity duration-150' : 'opacity-0'
          }
          onLoad={() => {
            setStatus('ready');
            onReady?.();
          }}
          onError={() => {
            if (attempt === 0) {
              setAttempt(1);
              setStatus('loading');
            } else {
              setStatus('error');
              onLoadFailed?.();
            }
          }}
        />
      ) : null}

      {status === 'loading' ? (
        <div
          className="absolute inset-0 flex flex-col items-center justify-center gap-2 rounded-[14px] bg-white/95 p-3 text-center"
          role="status"
          aria-live="polite"
        >
          <svg
            className="h-7 w-7 animate-spin text-green"
            fill="none"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <circle
              cx="12"
              cy="12"
              r="9"
              stroke="currentColor"
              strokeOpacity=".25"
              strokeWidth="3"
            />
            <path
              d="M21 12a9 9 0 0 0-9-9"
              stroke="currentColor"
              strokeWidth="3"
              strokeLinecap="round"
            />
          </svg>
          <span className="text-sm font-medium text-ink-soft">Loading QR code…</span>
        </div>
      ) : null}

      {status === 'error' ? (
        <div
          className="absolute inset-0 flex flex-col items-center justify-center gap-3 rounded-[14px] bg-[#fafcfb] p-4 text-center"
          role="alert"
        >
          <p className="text-sm font-medium text-ink-soft">Unable to load QR code.</p>
          <button
            type="button"
            onClick={() => {
              setStatus('loading');
              setAttempt(qrUrl ? 0 : 1);
              setRetrySeed((prev) => prev + 1);
            }}
            className="cr-btn min-h-[40px] text-base"
          >
            Retry
          </button>
        </div>
      ) : null}
    </div>
  );
}
