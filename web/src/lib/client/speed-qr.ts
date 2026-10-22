'use client';

/**
 * The one scanner helper, ported from `partials/qr-scan-helpers.blade.php`
 * (`window.SpeedQr`).
 *
 * It decodes the same payload format `lib/domain/tracking-qr` produces, but
 * CANNOT check the signature — only the server holds the key. That is fine and
 * deliberate: custody scans post the raw payload for the server to verify, and
 * a forged code here can at most open a tracking page anyone could have typed
 * the number into anyway. The server is the gate.
 */
import { Html5Qrcode } from 'html5-qrcode';

/**
 * Tracking numbers this system issues: SPD- (citizen) and INT- (internal
 * office-to-office), both PREFIX-YYYYMMDD-XXXXXX with 6 base32 characters.
 */
export const TRACKING_RE = /^(SPD|INT)-\d{8}-[0-9A-Z]{6}$/i;

/** Paths of ours that a QR may legitimately point at. */
const OUR_PATHS = /^\/(track|verify)\/((?:SPD|INT)-\d{8}-[0-9A-Z]{6})(?:\/|$)/i;

/** Where an internal request's QR link points. */
const INTERNAL_TRACKER_PATH = '/internal-requests/track';

/** Signed request QRs (see `lib/domain/tracking-qr`). */
const SIGNED_PREFIX = 'SPDQR1:';
const SIGNATURE_BYTES = 8;
const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

/** Staff badge payloads — opaque, never a URL. */
const STAFF_BADGE_RE = /^SPDSTAFF:[A-Za-z0-9]{16,40}$/;

/** Human-readable rejection, for UIs that need to explain the refusal. */
export const FOREIGN_CODE_MESSAGE =
  'That code was not issued by SPeED TraQR. Scan the QR printed on the claim slip or folder.';

/** The tracking number inside a signed request QR, or null. */
function decodeSigned(raw: string): string | null {
  const body = raw.slice(SIGNED_PREFIX.length);

  if (!/^[A-Z2-7]+$/.test(body)) {
    return null;
  }

  let bits = '';

  for (const char of body) {
    bits += BASE32.indexOf(char).toString(2).padStart(5, '0');
  }

  let bytes = '';

  for (let i = 0; i + 8 <= bits.length; i += 8) {
    bytes += String.fromCharCode(Number.parseInt(bits.slice(i, i + 8), 2));
  }

  const tracking = bytes.slice(0, -SIGNATURE_BYTES);

  return bytes.length > SIGNATURE_BYTES && TRACKING_RE.test(tracking) ? tracking : null;
}

/**
 * Extract a tracking number, but ONLY from a code this system issued: a signed
 * request QR (what every new code carries), one of our own tracking URLs (same
 * origin), or a bare tracking number — the last two keep stickers printed
 * before signed QRs working.
 *
 * A foreign QR — a Wi-Fi code, a payment code, a link to another site, or text
 * that merely mentions a tracking number — returns null so callers reject it
 * instead of acting on someone else's code.
 */
export function extractTracking(text: string | null | undefined): string | null {
  const raw = (text ?? '').trim();

  if (!raw) {
    return null;
  }

  if (raw.toUpperCase().startsWith(SIGNED_PREFIX)) {
    return decodeSigned(raw.toUpperCase());
  }

  // A bare tracking number (typed in, or a code encoding just the number).
  if (TRACKING_RE.test(raw)) {
    return raw.toUpperCase();
  }

  // Otherwise it must be one of OUR URLs.
  let url: URL;

  try {
    url = new URL(raw, window.location.origin);
  } catch {
    return null;
  }

  if (url.origin !== window.location.origin) {
    return null;
  }

  // An internal request's QR: a link to our internal tracker carrying the
  // signed payload.
  if (url.pathname.replace(/\/+$/, '').endsWith(INTERNAL_TRACKER_PATH)) {
    const signed = (url.searchParams.get('qr') ?? '').toUpperCase();

    return signed.startsWith(SIGNED_PREFIX) ? decodeSigned(signed) : null;
  }

  const match = OUR_PATHS.exec(url.pathname);

  return match ? match[2].toUpperCase() : null;
}

export function isStaffBadge(text: string | null | undefined): boolean {
  return STAFF_BADGE_RE.test((text ?? '').trim());
}

/** Human-readable camera failure, so a refusal explains itself. */
export function describe(error: unknown): string {
  const message = error instanceof Error ? error.message : String(error ?? '');

  if (/NotAllowed|Permission|denied/i.test(message)) {
    return 'The camera permission was refused. Allow camera access for this site, then try again.';
  }

  if (/NotFound|no camera|Requested device not found/i.test(message)) {
    return 'No camera was found on this device.';
  }

  if (/NotReadable|TrackStart|in use/i.test(message)) {
    return 'The camera is already in use by another app or tab.';
  }

  if (/secure|https/i.test(message)) {
    return 'The camera needs a secure (https) connection.';
  }

  return 'The camera could not be started.';
}

/* ── Camera ─────────────────────────────────────────────────────────────────── */

let scanner: Html5Qrcode | null = null;

/**
 * Serializes every start/stop. Releasing a camera is asynchronous, so without
 * this a second scan opens a new stream while the previous one is still
 * shutting down and the browser refuses it — the scanner looks dead even though
 * the permission is granted.
 */
let queue: Promise<unknown> = Promise.resolve();

function enqueue<T>(task: () => Promise<T>): Promise<T> {
  const run = queue.then(task, task);

  // Keep the chain alive even when a task rejects.
  queue = run.catch(() => undefined);

  return run;
}

export async function hasCamera(): Promise<boolean> {
  try {
    return (await Html5Qrcode.getCameras()).length > 0;
  } catch {
    return false;
  }
}

export function start(
  elementId: string,
  onDecoded: (text: string) => void,
  onError?: (error: unknown) => void,
): Promise<void> {
  return enqueue(async () => {
    await stopInternal();

    try {
      scanner = new Html5Qrcode(elementId, { verbose: false });

      await scanner.start(
        { facingMode: 'environment' },
        // A square box: never `object-fit: cover` on the video, which distorts
        // what html5-qrcode decodes.
        { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1 },
        (decodedText) => onDecoded(decodedText),
        () => {
          // Per-frame "not found" callbacks are noise, not errors.
        },
      );
    } catch (error) {
      scanner = null;
      onError?.(error);
    }
  });
}

async function stopInternal(): Promise<void> {
  if (!scanner) {
    return;
  }

  const current = scanner;
  scanner = null;

  try {
    await current.stop();
    current.clear();
  } catch {
    // Already stopped, or the element went away with the component.
  }
}

export function stop(): Promise<void> {
  return enqueue(stopInternal);
}

/* ── Still images ───────────────────────────────────────────────────────────── */

/** Decodes a QR out of a picture — a saved screenshot, a photo, a drop. */
export async function decodeImage(file: File | Blob): Promise<string> {
  // html5-qrcode needs a mounted element even for file scanning.
  const host = document.createElement('div');
  host.style.display = 'none';
  document.body.append(host);
  host.id = `speedqr-file-${Math.random().toString(36).slice(2)}`;

  const reader = new Html5Qrcode(host.id, { verbose: false });

  try {
    const asFile = file instanceof File ? file : new File([file], 'qr.png', { type: file.type });

    return await reader.scanFile(asFile, false);
  } catch {
    throw new Error('No QR code was found in that picture. Try a clearer, closer picture of it.');
  } finally {
    try {
      reader.clear();
    } catch {
      // Nothing to clear if the scan never started.
    }
    host.remove();
  }
}

/** Pulls an image out of a drag-and-drop, including a dragged <img> URL. */
export async function imageFromDrop(dataTransfer: DataTransfer | null): Promise<File> {
  const file = dataTransfer?.files?.[0];

  if (file && file.type.startsWith('image/')) {
    return file;
  }

  // Dragging an image from another page hands over its URL, not the bytes.
  const url = dataTransfer?.getData('text/uri-list') || dataTransfer?.getData('text/plain');

  if (url) {
    try {
      const response = await fetch(url);
      const blob = await response.blob();

      if (blob.type.startsWith('image/')) {
        return new File([blob], 'qr.png', { type: blob.type });
      }
    } catch {
      // Cross-origin images usually cannot be fetched; fall through.
    }
  }

  throw new Error('Drop a picture of the QR code, or choose one from your device.');
}

/* ── Snipping the QR off the screen ─────────────────────────────────────────── */

export type SnipMode = 'page' | 'any';

/**
 * Reads a QR already visible on screen, with no camera.
 *
 * 'page' dims this page and the user drags a box around a QR that is part of
 * it — the page's own images are read directly, so there is no permission
 * prompt at all. 'any' screen-captures another tab or window (the browser asks
 * which), then boxes the QR on the still.
 *
 * Returns null when the user cancels.
 */
export async function scanFromScreen(mode: SnipMode = 'page'): Promise<string | null> {
  if (mode === 'page') {
    const rect = await dragBox(null);

    if (!rect) {
      return null;
    }

    const cropped = await cropFromPage(rect);

    return cropped ? decodeImage(cropped) : null;
  }

  const frame = await captureScreen();

  if (!frame) {
    return null;
  }

  const rect = await dragBox(frame.dataUrl);

  if (!rect) {
    return null;
  }

  return decodeImage(await cropFrame(frame, rect));
}

type Frame = { dataUrl: string; width: number; height: number };

async function captureScreen(): Promise<Frame | null> {
  const media = navigator.mediaDevices as MediaDevices & {
    getDisplayMedia?: (constraints: unknown) => Promise<MediaStream>;
  };

  if (!media?.getDisplayMedia) {
    throw new Error('This browser cannot capture the screen. Choose a picture of the QR instead.');
  }

  let stream: MediaStream;

  try {
    stream = await media.getDisplayMedia({ video: true });
  } catch {
    // The user dismissed the picker.
    return null;
  }

  try {
    const video = document.createElement('video');
    video.srcObject = stream;
    video.muted = true;
    await video.play();

    // One frame is enough, but the first can arrive blank.
    await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));

    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d')!.drawImage(video, 0, 0);

    return { dataUrl: canvas.toDataURL('image/png'), width: canvas.width, height: canvas.height };
  } finally {
    for (const track of stream.getTracks()) {
      track.stop();
    }
  }
}

export type BoxRect = { x: number; y: number; width: number; height: number };

/**
 * Dims the page (or shows the captured still) and lets the user drag a box.
 * Resolves with the box in CSS pixels, or null if they cancel.
 */
function dragBox(backdropDataUrl: string | null): Promise<BoxRect | null> {
  return new Promise((resolve) => {
    const overlay = document.createElement('div');
    overlay.className = 'qr-snip';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-label', 'Drag a box around the QR code');

    if (backdropDataUrl) {
      const still = document.createElement('img');
      still.src = backdropDataUrl;
      still.className = 'qr-snip-frame';
      overlay.append(still);
    }

    const hint = document.createElement('p');
    hint.className = 'qr-snip-hint';
    hint.textContent = 'Drag a box around the QR code · Esc to cancel';
    overlay.append(hint);

    const box = document.createElement('div');
    box.className = 'qr-snip-box';
    box.hidden = true;
    overlay.append(box);

    document.body.append(overlay);

    let origin: { x: number; y: number } | null = null;

    const done = (result: BoxRect | null): void => {
      overlay.remove();
      window.removeEventListener('keydown', onKey);
      resolve(result);
    };

    const onKey = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') {
        done(null);
      }
    };

    overlay.addEventListener('pointerdown', (event) => {
      origin = { x: event.clientX, y: event.clientY };
      box.hidden = false;
      Object.assign(box.style, { left: `${origin.x}px`, top: `${origin.y}px`, width: '0px', height: '0px' });
    });

    overlay.addEventListener('pointermove', (event) => {
      if (!origin) {
        return;
      }

      Object.assign(box.style, {
        left: `${Math.min(origin.x, event.clientX)}px`,
        top: `${Math.min(origin.y, event.clientY)}px`,
        width: `${Math.abs(event.clientX - origin.x)}px`,
        height: `${Math.abs(event.clientY - origin.y)}px`,
      });
    });

    overlay.addEventListener('pointerup', (event) => {
      if (!origin) {
        return done(null);
      }

      const rect = {
        x: Math.min(origin.x, event.clientX),
        y: Math.min(origin.y, event.clientY),
        width: Math.abs(event.clientX - origin.x),
        height: Math.abs(event.clientY - origin.y),
      };

      // A click rather than a drag: treat as a cancel, not a 0×0 crop.
      done(rect.width < 12 || rect.height < 12 ? null : rect);
    });

    window.addEventListener('keydown', onKey);
  });
}

/** Crops the captured still to the dragged box, in the still's own pixels. */
async function cropFrame(frame: Frame, rect: BoxRect): Promise<Blob> {
  const image = await loadImage(frame.dataUrl);

  // The still is shown with `object-fit: contain`, so map viewport pixels back
  // through the same letterboxing.
  const scale = Math.min(window.innerWidth / frame.width, window.innerHeight / frame.height);
  const shownWidth = frame.width * scale;
  const shownHeight = frame.height * scale;
  const offsetX = (window.innerWidth - shownWidth) / 2;
  const offsetY = (window.innerHeight - shownHeight) / 2;

  return cropImage(
    image,
    (rect.x - offsetX) / scale,
    (rect.y - offsetY) / scale,
    rect.width / scale,
    rect.height / scale,
  );
}

/**
 * Crops a QR that is part of THIS page: finds the image under the box and
 * crops it directly, so no screen-capture permission is needed.
 */
async function cropFromPage(rect: BoxRect): Promise<Blob | null> {
  const centre = document.elementsFromPoint(rect.x + rect.width / 2, rect.y + rect.height / 2);
  const target = centre.find((node): node is HTMLImageElement => node instanceof HTMLImageElement);

  if (!target) {
    throw new Error('No QR image was found in that box. Drag it around the QR picture itself.');
  }

  const bounds = target.getBoundingClientRect();
  const scaleX = target.naturalWidth / bounds.width;
  const scaleY = target.naturalHeight / bounds.height;

  // The whole image is usually the QR; crop to the box but clamp to the image.
  return cropImage(
    await loadImage(target.currentSrc || target.src),
    Math.max(0, (rect.x - bounds.left) * scaleX),
    Math.max(0, (rect.y - bounds.top) * scaleY),
    Math.min(target.naturalWidth, rect.width * scaleX),
    Math.min(target.naturalHeight, rect.height * scaleY),
  );
}

function loadImage(src: string): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const image = new Image();
    image.crossOrigin = 'anonymous';
    image.addEventListener('load', () => resolve(image));
    image.addEventListener('error', () => reject(new Error('That image could not be read.')));
    image.src = src;
  });
}

async function cropImage(
  image: HTMLImageElement,
  x: number,
  y: number,
  width: number,
  height: number,
): Promise<Blob> {
  const canvas = document.createElement('canvas');
  // Upscale small crops: the decoder needs pixels to work with.
  const scale = Math.max(1, Math.min(4, 400 / Math.max(width, height)));
  canvas.width = Math.round(width * scale);
  canvas.height = Math.round(height * scale);

  canvas
    .getContext('2d')!
    .drawImage(image, x, y, width, height, 0, 0, canvas.width, canvas.height);

  return new Promise((resolve, reject) => {
    canvas.toBlob(
      (blob) => (blob ? resolve(blob) : reject(new Error('That box could not be read.'))),
      'image/png',
    );
  });
}

/**
 * One scan receives: for an INT- code this posts to the custody endpoint, which
 * records the receipt if the scanner may receive it, then opens the request.
 * Everything else goes to its tracking page.
 */
export async function openScanned(decodedText: string, method = 'scan'): Promise<void> {
  const tracking = extractTracking(decodedText);

  if (!tracking) {
    throw new Error(FOREIGN_CODE_MESSAGE);
  }

  if (!tracking.toUpperCase().startsWith('INT-')) {
    window.location.assign(`/track/${encodeURIComponent(tracking)}?view=citizen`);

    return;
  }

  const response = await fetch('/custody/scan', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ payload: decodedText, capture_method: method }),
  });

  const result = (await response.json().catch(() => null)) as { redirect?: string; message?: string } | null;

  if (!response.ok) {
    throw new Error(result?.message ?? 'That request could not be opened.');
  }

  window.location.assign(result?.redirect ?? `/internal-requests/track?number=${encodeURIComponent(tracking)}`);
}
