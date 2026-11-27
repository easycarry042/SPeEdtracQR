'use client';

import { useState } from 'react';
import { downloadQrPng } from '@/lib/client/download-qr';

export interface DownloadQrButtonProps {
  qrUrl: string | null;
  trackingNumber: string;
  label?: string;
  className?: string;
  fallbackSvg?: string | null;
  variant?: 'qr' | 'receipt-card';
  documentType?: string | null;
}

/**
 * Direct PNG download button using Blob + Object URL so cross-origin storage
 * URLs never open a browser preview tab.
 */
export function DownloadQrButton({
  qrUrl,
  trackingNumber,
  label = 'Download QR',
  className = 'cr-btn',
  fallbackSvg = null,
  variant = 'qr',
  documentType = null,
}: DownloadQrButtonProps) {
  const [downloading, setDownloading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!qrUrl && !fallbackSvg) {
    return null;
  }

  const handleDownload = async (): Promise<void> => {
    if (downloading) {
      return;
    }

    setDownloading(true);
    setError(null);

    try {
      await downloadQrPng({ url: qrUrl, trackingNumber, fallbackSvg, variant, documentType });
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to download QR code.');
    } finally {
      setDownloading(false);
    }
  };

  return (
    <>
      <button
        type="button"
        onClick={() => void handleDownload()}
        disabled={downloading}
        aria-busy={downloading}
        className={className}
      >
        <svg fill="none" stroke="currentColor" strokeWidth="2.2" viewBox="0 0 24 24" aria-hidden="true">
          <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v11m0 0-4-4m4 4 4-4M5 19h14" />
        </svg>
        {downloading ? 'Downloading…' : label}
      </button>
      {error ? (
        <p className="w-full text-center text-sm font-semibold text-status-red" role="alert">
          {error}
        </p>
      ) : null}
    </>
  );
}
