'use client';

/**
 * Loads an HTMLImageElement from an object URL and revokes it once settled.
 */
async function loadImageFromBlob(blob: Blob): Promise<HTMLImageElement> {
  const objectUrl = URL.createObjectURL(blob);

  try {
    return await new Promise<HTMLImageElement>((resolve, reject) => {
      const img = new Image();
      img.onload = () => resolve(img);
      img.onerror = () => reject(new Error('Could not decode the QR graphic.'));
      img.src = objectUrl;
    });
  } finally {
    URL.revokeObjectURL(objectUrl);
  }
}

/**
 * Renders inline SVG QR markup onto an offscreen 512x512 canvas and exports a PNG Blob.
 */
async function svgToPngBlob(svgMarkup: string): Promise<Blob> {
  const svgBlob = new Blob([svgMarkup], { type: 'image/svg+xml;charset=utf-8' });
  const image = await loadImageFromBlob(svgBlob);

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

  return await canvasToPngBlob(canvas, 'Could not generate the QR PNG file.');
}

function canvasToPngBlob(canvas: HTMLCanvasElement, errorMessage: string): Promise<Blob> {
  return new Promise<Blob>((resolve, reject) => {
    canvas.toBlob(
      (blob) => {
        if (blob && blob.size > 0) {
          resolve(blob);
        } else {
          reject(new Error(errorMessage));
        }
      },
      'image/png',
    );
  });
}

function roundedRectPath(
  ctx: CanvasRenderingContext2D,
  x: number,
  y: number,
  width: number,
  height: number,
  radius: number,
): void {
  const r = Math.min(radius, width / 2, height / 2);
  ctx.beginPath();
  ctx.moveTo(x + r, y);
  ctx.lineTo(x + width - r, y);
  ctx.quadraticCurveTo(x + width, y, x + width, y + r);
  ctx.lineTo(x + width, y + height - r);
  ctx.quadraticCurveTo(x + width, y + height, x + width - r, y + height);
  ctx.lineTo(x + r, y + height);
  ctx.quadraticCurveTo(x, y + height, x, y + height - r);
  ctx.lineTo(x, y + r);
  ctx.quadraticCurveTo(x, y, x + r, y);
  ctx.closePath();
}

function wrapLines(ctx: CanvasRenderingContext2D, text: string, maxWidth: number): string[] {
  const words = text.trim().split(/\s+/).filter(Boolean);
  if (words.length === 0) return [];

  const lines: string[] = [];
  let current = words[0];

  for (let i = 1; i < words.length; i += 1) {
    const candidate = `${current} ${words[i]}`;
    if (ctx.measureText(candidate).width <= maxWidth) {
      current = candidate;
    } else {
      lines.push(current);
      current = words[i];
    }
  }

  lines.push(current);
  return lines;
}

function drawTrackedCenteredText(
  ctx: CanvasRenderingContext2D,
  text: string,
  centerX: number,
  y: number,
  spacing: number,
): void {
  const chars = Array.from(text);
  const widths = chars.map((ch) => ctx.measureText(ch).width);
  const totalWidth =
    widths.reduce((sum, w) => sum + w, 0) + Math.max(0, chars.length - 1) * spacing;

  let cursorX = centerX - totalWidth / 2;
  ctx.textAlign = 'left';
  for (let i = 0; i < chars.length; i += 1) {
    ctx.fillText(chars[i], cursorX, y);
    cursorX += widths[i] + spacing;
  }
}

async function fetchQrBlob(url: string, filename: string): Promise<Blob | null> {
  const fetchUrl = url.startsWith('/storage/')
    ? `${url}${url.includes('?') ? '&' : '?'}download=1&filename=${encodeURIComponent(filename)}`
    : url;

  try {
    const response = await fetch(fetchUrl, { credentials: 'same-origin' });
    if (response.ok) {
      const fetched = await response.blob();
      if (fetched.size > 0) {
        return fetched;
      }
    }
  } catch {
    // Fall back to SVG or caller error handling
  }

  return null;
}

async function resolveQrImage(options: {
  url: string | null;
  trackingNumber: string;
  fallbackSvg?: string | null;
}): Promise<HTMLImageElement | null> {
  if (options.fallbackSvg) {
    const svgBlob = new Blob([options.fallbackSvg], { type: 'image/svg+xml;charset=utf-8' });
    return await loadImageFromBlob(svgBlob);
  }

  if (options.url) {
    const fetched = await fetchQrBlob(options.url, `${options.trackingNumber}-qr.png`);
    if (fetched) {
      return await loadImageFromBlob(fetched);
    }
  }

  return null;
}

/**
 * Renders the citizen "Request submitted" confirmation card (without the action
 * buttons row) to a high-DPI PNG matching the submitted receipt UI.
 */
async function renderReceiptCardPngBlob(options: {
  qrImage: HTMLImageElement;
  trackingNumber: string;
  documentType?: string | null;
}): Promise<Blob> {
  const scale = 2;
  const outerPad = 24;
  const cardWidth = 620;
  const cardPadX = 48;
  const cardPadTop = 44;
  const cardPadBottom = 42;
  const canvasWidth = cardWidth + outerPad * 2;

  const bodyStyles = getComputedStyle(document.body);
  const sansFont = bodyStyles.fontFamily || 'system-ui, -apple-system, sans-serif';
  const monoFont =
    bodyStyles.getPropertyValue('--mono').trim() ||
    'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace';

  // Measure wrapped text on a scratch context so card height adapts cleanly.
  const measureCanvas = document.createElement('canvas');
  const mctx = measureCanvas.getContext('2d');
  if (!mctx) {
    throw new Error('Canvas context is unavailable.');
  }

  const subtitle =
    "Save your QR code and tracking number below. To follow your request, scan the code on this site\u2019s Track a request page \u2014 your phone\u2019s camera app won\u2019t open it.";
  mctx.font = `500 17px ${sansFont}`;
  const subtitleLines = wrapLines(mctx, subtitle, 460);

  const docType = (options.documentType ?? '').trim();
  mctx.font = `500 16px ${sansFont}`;
  const docTypeLines = docType ? wrapLines(mctx, docType, cardWidth - cardPadX * 2) : [];

  const checkRadius = 28;
  const titleHeight = 40;
  const subtitleLineHeight = 27;
  const qrBoxSize = 240;
  const qrBoxPad = 14;
  const pillHeight = 46;

  let cursorY = outerPad + cardPadTop;
  const checkCenterY = cursorY + checkRadius;
  cursorY += checkRadius * 2 + 22;
  const titleY = cursorY;
  cursorY += titleHeight + 12;
  const subtitleStartY = cursorY;
  cursorY += subtitleLines.length * subtitleLineHeight + 28;
  const qrBoxY = cursorY;
  cursorY += qrBoxSize + 28;
  const keyLabelY = cursorY;
  cursorY += 18 + 10;
  const pillY = cursorY;
  cursorY += pillHeight;
  const docTypeStartY = docTypeLines.length > 0 ? cursorY + 18 : cursorY;
  if (docTypeLines.length > 0) {
    cursorY = docTypeStartY + docTypeLines.length * 24;
  }
  cursorY += cardPadBottom;

  const cardHeight = cursorY - outerPad;
  const canvasHeight = outerPad * 2 + cardHeight;

  const canvas = document.createElement('canvas');
  canvas.width = canvasWidth * scale;
  canvas.height = canvasHeight * scale;

  const ctx = canvas.getContext('2d');
  if (!ctx) {
    throw new Error('Canvas context is unavailable.');
  }

  ctx.scale(scale, scale);

  // Outer mint canvas background matching the receipt page
  ctx.fillStyle = '#f5faf3';
  ctx.fillRect(0, 0, canvasWidth, canvasHeight);

  // Card surface + soft green-tinted shadow
  ctx.save();
  ctx.shadowColor = 'rgba(0, 64, 4, 0.12)';
  ctx.shadowBlur = 24;
  ctx.shadowOffsetY = 8;
  roundedRectPath(ctx, outerPad, outerPad, cardWidth, cardHeight, 20);
  ctx.fillStyle = '#ffffff';
  ctx.fill();
  ctx.restore();

  roundedRectPath(ctx, outerPad, outerPad, cardWidth, cardHeight, 20);
  ctx.strokeStyle = 'rgba(0, 64, 4, 0.12)';
  ctx.lineWidth = 1;
  ctx.stroke();

  const centerX = canvasWidth / 2;

  // Green checkmark badge
  ctx.beginPath();
  ctx.arc(centerX, checkCenterY, checkRadius, 0, Math.PI * 2);
  ctx.fillStyle = '#eef8ea';
  ctx.fill();

  ctx.beginPath();
  ctx.moveTo(centerX - 10, checkCenterY + 1);
  ctx.lineTo(centerX - 3, checkCenterY + 8);
  ctx.lineTo(centerX + 11, checkCenterY - 7);
  ctx.strokeStyle = '#01721a';
  ctx.lineWidth = 3;
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';
  ctx.stroke();

  // Heading
  ctx.fillStyle = '#004004';
  ctx.font = `900 36px ${sansFont}`;
  ctx.textAlign = 'center';
  ctx.textBaseline = 'top';
  ctx.fillText('Request submitted', centerX, titleY);

  // Subtitle
  ctx.fillStyle = '#353535';
  ctx.font = `500 17px ${sansFont}`;
  subtitleLines.forEach((line, index) => {
    ctx.fillText(line, centerX, subtitleStartY + index * subtitleLineHeight);
  });

  // Framed QR container
  const qrBoxX = centerX - qrBoxSize / 2;
  roundedRectPath(ctx, qrBoxX, qrBoxY, qrBoxSize, qrBoxSize, 16);
  ctx.fillStyle = '#ffffff';
  ctx.fill();
  ctx.strokeStyle = 'rgba(0, 64, 4, 0.12)';
  ctx.lineWidth = 1;
  ctx.stroke();

  const qrDrawSize = qrBoxSize - qrBoxPad * 2;
  ctx.save();
  ctx.imageSmoothingEnabled = false;
  ctx.drawImage(options.qrImage, qrBoxX + qrBoxPad, qrBoxY + qrBoxPad, qrDrawSize, qrDrawSize);
  ctx.restore();

  // "TRACKING NUMBER" label
  ctx.fillStyle = '#4b5a50';
  ctx.font = `700 12px ${sansFont}`;
  ctx.textBaseline = 'top';
  drawTrackedCenteredText(ctx, 'TRACKING NUMBER', centerX, keyLabelY, 1.1);

  // Monospace tracking number pill
  ctx.font = `700 24px ${monoFont}`;
  const pillTextWidth = ctx.measureText(options.trackingNumber).width;
  const pillWidth = Math.max(220, pillTextWidth + 44);
  const pillX = centerX - pillWidth / 2;

  roundedRectPath(ctx, pillX, pillY, pillWidth, pillHeight, 10);
  ctx.fillStyle = '#eef8ea';
  ctx.fill();

  ctx.fillStyle = '#004004';
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.fillText(options.trackingNumber, centerX, pillY + pillHeight / 2 + 1);

  // Document type / subtitle underneath
  if (docTypeLines.length > 0) {
    ctx.fillStyle = '#4b5a50';
    ctx.font = `500 16px ${sansFont}`;
    ctx.textBaseline = 'top';
    docTypeLines.forEach((line, index) => {
      ctx.fillText(line, centerX, docTypeStartY + index * 24);
    });
  }

  return await canvasToPngBlob(canvas, 'Could not generate the receipt QR card PNG.');
}

function triggerBlobDownload(blob: Blob, filename: string): void {
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

/**
 * Downloads a QR code PNG directly via Blob + Object URL without opening a preview tab.
 * When `variant === 'receipt-card'`, exports the full "Request submitted" card
 * (without the bottom button bar).
 */
export async function downloadQrPng(options: {
  url: string | null;
  trackingNumber: string;
  fallbackSvg?: string | null;
  variant?: 'qr' | 'receipt-card';
  documentType?: string | null;
}): Promise<void> {
  const filename = `${options.trackingNumber}-qr.png`;

  if (options.variant === 'receipt-card') {
    const image = await resolveQrImage(options);
    if (!image) {
      throw new Error('Could not download the QR code image. Please try again.');
    }

    const cardBlob = await renderReceiptCardPngBlob({
      qrImage: image,
      trackingNumber: options.trackingNumber,
      documentType: options.documentType,
    });
    triggerBlobDownload(cardBlob, filename);
    return;
  }

  let blob: Blob | null = options.url ? await fetchQrBlob(options.url, filename) : null;

  if (!blob && options.fallbackSvg) {
    blob = await svgToPngBlob(options.fallbackSvg);
  }

  if (!blob) {
    throw new Error('Could not download the QR code image. Please try again.');
  }

  triggerBlobDownload(blob, filename);
}
