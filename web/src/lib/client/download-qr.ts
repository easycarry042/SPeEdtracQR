'use client';

/**
 * Renders inline SVG QR markup onto an offscreen 512x512 canvas and exports a PNG Blob.
 */
async function svgToPngBlob(svgMarkup: string): Promise<Blob> {
  const svgBlob = new Blob([svgMarkup], { type: 'image/svg+xml;charset=utf-8' });
  const svgUrl = URL.createObjectURL(svgBlob);

  try {
    const image = await new Promise<HTMLImageElement>((resolve, reject) => {
      const img = new Image();
      img.onload = () => resolve(img);
      img.onerror = () => reject(new Error('Could not convert the QR graphic to PNG.'));
      img.src = svgUrl;
    });

    const size = 512;
    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;

    const ctx = canvas.getContext('2d');
    if (!ctx) {
      throw new Error('Canvas context is unavailable.');
    }

    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, size, size);
    ctx.drawImage(image, 0, 0, size, size);

    return await new Promise<Blob>((resolve, reject) => {
      canvas.toBlob(
        (blob) => {
          if (blob && blob.size > 0) {
            resolve(blob);
          } else {
            reject(new Error('Could not generate the QR PNG file.'));
          }
        },
        'image/png',
      );
    });
  } finally {
    URL.revokeObjectURL(svgUrl);
  }
}

/**
 * Downloads a QR code PNG directly via Blob + Object URL without opening a preview tab.
 */
export async function downloadQrPng(options: {
  url: string | null;
  trackingNumber: string;
  fallbackSvg?: string | null;
}): Promise<void> {
  const filename = `${options.trackingNumber}-qr.png`;
  let blob: Blob | null = null;

  if (options.url) {
    const fetchUrl = options.url.startsWith('/storage/')
      ? `${options.url}${options.url.includes('?') ? '&' : '?'}download=1&filename=${encodeURIComponent(filename)}`
      : options.url;

    try {
      const response = await fetch(fetchUrl, { credentials: 'same-origin' });
      if (response.ok) {
        const fetched = await response.blob();
        if (fetched.size > 0) {
          blob = fetched;
        }
      }
    } catch {
      // Fall through to SVG fallback if available
    }
  }

  if (!blob && options.fallbackSvg) {
    blob = await svgToPngBlob(options.fallbackSvg);
  }

  if (!blob) {
    throw new Error('Could not download the QR code image. Please try again.');
  }

  const objectUrl = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = objectUrl;
  link.download = filename;
  link.style.display = 'none';
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
}
