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
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';

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

  if (!['http:', 'https:'].includes(url.protocol)) {
    return null;
  }

  // An internal request's QR: a link to our internal tracker carrying the
  // signed payload. Verify the signed payload regardless of host so codes
  // printed on production/preview/localhost still scan across environments.
  if (url.pathname.replace(/\/+$/, '').endsWith(INTERNAL_TRACKER_PATH)) {
    const signed = (url.searchParams.get('qr') ?? '').toUpperCase();

    return signed.startsWith(SIGNED_PREFIX) ? decodeSigned(signed) : null;
  }

  if (url.origin !== window.location.origin) {
    return null;
  }

  const match = OUR_PATHS.exec(url.pathname);

  return match ? match[2].toUpperCase() : null;
}

export function isStaffBadge(text: string | null | undefined): boolean {
  return STAFF_BADGE_RE.test((text ?? '').trim());
}

/** Human-readable camera failure, so a refusal explains itself. */
export function describe(error: unknown): string {
  const name = error instanceof Error ? error.name : '';
  const message = error instanceof Error ? error.message : String(error ?? '');

  if (typeof window !== 'undefined' && (!window.isSecureContext || !navigator.mediaDevices)) {
    return (
      `Your browser blocks the camera on this address (${window.location.origin}). ` +
      'Cameras only work over https:// or on http://localhost — allowing the permission cannot override this. ' +
      'Open the site over HTTPS, or scan the QR from the machine running the server.'
    );
  }

  if (name === 'NotAllowedError' || name === 'SecurityError' || /NotAllowed|Permission|denied/i.test(message)) {
    return 'The browser is still blocking the camera for this site. Open the padlock/camera icon in the address bar, set Camera to Allow, then reload the page.';
  }

  if (
    name === 'NotFoundError' ||
    name === 'DevicesNotFoundError' ||
    name === 'OverconstrainedError' ||
    /NotFound|no camera|Requested device not found/i.test(message)
  ) {
    return 'No camera was found on this device. Use a phone or a machine with a webcam, or record it manually.';
  }

  if (name === 'NotReadableError' || name === 'TrackStartError' || /NotReadable|TrackStart|in use/i.test(message)) {
    return 'Another app or browser tab is already using the camera. Close it (Zoom, Meet, another scanner tab) and try again.';
  }

  return message || 'The camera could not be started.';
}

/* ── Camera ─────────────────────────────────────────────────────────────────── */

let scanner: Html5Qrcode | null = null;
let fitObserver: ResizeObserver | null = null;
let activeScanId = 0;

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
  if (typeof window === 'undefined' || !navigator.mediaDevices?.enumerateDevices) {
    return false;
  }

  try {
    const devices = await navigator.mediaDevices.enumerateDevices();
    return devices.some((d) => d.kind === 'videoinput');
  } catch {
    return false;
  }
}

/**
 * A .qr-surface (see .qr-viewport in globals.css) is laid out at a real
 * decoding size and only drawn small. Its width is set before the camera
 * starts, because html5-qrcode reads it then to size the video: 1280px
 * for a landscape feed, 720px where the screen is portrait.
 */
function prepareSurface(elementId: string): void {
  const element = document.getElementById(elementId);

  if (!element || !element.classList.contains('qr-surface')) {
    return;
  }

  element.classList.remove('is-fitted');
  element.style.transform = '';
  element.style.width = `${window.innerHeight > window.innerWidth ? 720 : 1280}px`;
}

/**
 * html5-qrcode measures its container when it starts. Callers reveal the box
 * and call start() in the same tick, so the div can still be absent from the DOM
 * or 0px wide at that moment — wait for it to actually be laid out.
 */
function waitForBox(elementId: string, timeoutMs = 4000): Promise<HTMLElement> {
  const startedAt = Date.now();

  return new Promise((resolve, reject) => {
    function check(): void {
      const element = document.getElementById(elementId);

      if (!element) {
        if (Date.now() - startedAt > timeoutMs) {
          reject(new Error('The scanner box is not on the page.'));
          return;
        }
        requestAnimationFrame(check);
        return;
      }

      // Prepare the surface width as soon as the element mounts in the DOM;
      // otherwise an empty position:absolute .qr-surface stays 0px wide forever.
      prepareSurface(elementId);

      const box = element.parentElement ?? element;

      if (box.getClientRects().length > 0 && box.offsetWidth > 0 && element.offsetWidth > 0) {
        resolve(element);
        return;
      }

      if (Date.now() - startedAt > timeoutMs) {
        reject(
          new Error(
            'The scanner area stayed hidden, so the camera could not be sized. Close and reopen the scanner, or reload the page.',
          ),
        );
        return;
      }

      requestAnimationFrame(check);
    }

    check();
  });
}

/**
 * Scale the surface down to cover its square window, centered. A
 * transform, never a width change: html5-qrcode measures clientWidth /
 * clientHeight, which a transform leaves alone, so its decoding stays
 * undistorted and full resolution while the feed fills the window.
 */
function fitSurface(elementId: string): void {
  const element = document.getElementById(elementId);

  if (!element) {
    return;
  }

  const target: HTMLElement = element;

  if (!target.classList.contains('qr-surface')) {
    target.classList.add('is-fitted');
    return;
  }

  const box = target.parentElement;
  if (!box) {
    target.classList.add('is-fitted');
    return;
  }

  const apply = (): boolean => {
    const video = target.querySelector('video');

    if (!video) {
      return false;
    }

    const boxW = box.clientWidth;
    const boxH = box.clientHeight || boxW;

    if (!boxW) {
      return false;
    }

    const width = target.offsetWidth || video.videoWidth || boxW;
    const height =
      video.offsetHeight ||
      (video.videoWidth && video.videoHeight ? (width * video.videoHeight) / video.videoWidth : 0);

    if (!height) {
      return false;
    }

    const scale = Math.max(boxW / width, boxH / height);
    const x = (boxW - width * scale) / 2;
    const y = (boxH - height * scale) / 2;

    target.style.transform = `translate(${x}px, ${y}px) scale(${scale})`;
    target.classList.add('is-fitted');

    return Boolean(video.videoWidth && video.videoHeight);
  };

  const startedAt = Date.now();
  function fitWhenReady(): void {
    if (!apply()) {
      if (Date.now() - startedAt < 5000) {
        requestAnimationFrame(fitWhenReady);
      } else {
        // Fallback: scale by width and make visible even if metadata was not reported
        const boxW = box?.clientWidth || 320;
        const width = target.offsetWidth || boxW;
        const scale = boxW / width;
        target.style.transform = `scale(${scale})`;
        target.classList.add('is-fitted');
      }
    }
  }

  fitWhenReady();

  const video = target.querySelector('video');
  if (video) {
    video.addEventListener('loadedmetadata', () => {
      apply();
    });
    video.addEventListener('playing', () => {
      apply();
    });
    video.addEventListener('resize', () => {
      apply();
    });
  }

  if (fitObserver) {
    fitObserver.disconnect();
    fitObserver = null;
  }

  if (typeof window !== 'undefined' && 'ResizeObserver' in window) {
    fitObserver = new ResizeObserver(() => {
      apply();
    });
    fitObserver.observe(box);
    fitObserver.observe(target);
  }
}

/** Square viewfinder that always fits — a fixed qrbox throws on small boxes. */
function qrbox(viewfinderWidth: number, viewfinderHeight: number): { width: number; height: number } {
  const side = Math.max(120, Math.floor(Math.min(viewfinderWidth, viewfinderHeight) * 0.7));
  return { width: side, height: side };
}

/** Ask for HD frames for accurate barcode reading. */
const HD = { width: { ideal: 1280 }, height: { ideal: 720 } };

function scanConfig(camera?: MediaTrackConstraints) {
  return {
    fps: 10,
    qrbox,
    videoConstraints: camera ? Object.assign({}, camera, HD) : undefined,
  };
}

function scannerOptions() {
  const formats =
    typeof Html5QrcodeSupportedFormats !== 'undefined'
      ? [Html5QrcodeSupportedFormats.QR_CODE]
      : undefined;

  return {
    verbose: false,
    formatsToSupport: formats,
    useBarCodeDetectorIfSupported: true,
  };
}

function isPermissionDenied(error: unknown): boolean {
  const name = error instanceof Error ? error.name : '';
  const message = error instanceof Error ? error.message : String(error ?? '');
  return (
    name === 'NotAllowedError' ||
    name === 'PermissionDeniedError' ||
    name === 'SecurityError' ||
    /NotAllowed|Permission denied|Permission dismissed/i.test(message)
  );
}

/**
 * Launch the scanner: prefer the rear environment camera, but gracefully fall back
 * to whatever camera this machine has (e.g. laptop / desktop webcams).
 */
async function launch(
  elementId: string,
  onDecode: (text: string) => void,
): Promise<void> {
  const tryStart = async (
    cameraIdOrConfig: string | MediaTrackConstraints,
    withHdConstraints: boolean,
  ): Promise<void> => {
    prepareSurface(elementId);
    const instance = new Html5Qrcode(elementId, scannerOptions());
    scanner = instance;
    const constraints =
      withHdConstraints && typeof cameraIdOrConfig === 'object' ? cameraIdOrConfig : undefined;
    await instance.start(
      cameraIdOrConfig,
      scanConfig(constraints),
      (decodedText) => onDecode(decodedText),
      () => {},
    );
  };

  try {
    await tryStart({ facingMode: 'environment' }, true);
    return;
  } catch (firstError) {
    if (isPermissionDenied(firstError)) {
      throw firstError;
    }

    // Retry environment without custom HD videoConstraints (some mobile browsers reject ideal HD)
    try {
      await tryStart({ facingMode: 'environment' }, false);
      return;
    } catch (envError) {
      if (isPermissionDenied(envError)) {
        throw envError;
      }
    }

    // Desktops with only a built-in webcam reject "environment"
    // outright (OverconstrainedError), so retry by device id.
    try {
      const cameras = await Html5Qrcode.getCameras();
      if (cameras && cameras.length > 0) {
        const preferred =
          cameras.find((cam) => /back|rear|environment/i.test(cam.label)) ?? cameras[0];
        try {
          await tryStart({ deviceId: { exact: preferred.id } }, true);
          return;
        } catch {
          await tryStart(preferred.id, false);
          return;
        }
      }
    } catch {
      // Fall through to user-facing mode
    }

    try {
      await tryStart({ facingMode: 'user' }, false);
      return;
    } catch {
      throw firstError;
    }
  }
}

export function start(
  elementId: string,
  onDecoded: (text: string) => void,
  onError?: (error: unknown) => void,
): Promise<void> {
  if (typeof window !== 'undefined' && (!window.isSecureContext || !navigator.mediaDevices)) {
    onError?.(new DOMException('Camera unavailable on an insecure origin.', 'SecurityError'));
    return Promise.resolve();
  }

  const scanId = ++activeScanId;

  return enqueue(async () => {
    await stopInternal();

    if (scanId !== activeScanId) {
      return;
    }

    try {
      await waitForBox(elementId);
      if (scanId !== activeScanId) return;

      await launch(elementId, onDecoded);
      if (scanId !== activeScanId) {
        await stopInternal();
        return;
      }

      fitSurface(elementId);
    } catch (error) {
      await stopInternal();
      onError?.(error);
    }
  });
}

async function stopInternal(): Promise<void> {
  if (fitObserver) {
    fitObserver.disconnect();
    fitObserver = null;
  }

  if (typeof document !== 'undefined') {
    document.querySelectorAll('.qr-surface.is-fitted').forEach((surface) => {
      surface.classList.remove('is-fitted');
      if (surface instanceof HTMLElement) {
        surface.style.transform = '';
      }
    });
  }

  if (!scanner) {
    return;
  }

  const current = scanner;
  scanner = null;

  try {
    await current.stop();
  } catch {
    // Already stopped, or the element went away with the component.
  }

  try {
    current.clear();
  } catch {
    // Ignore clear errors if DOM is already gone
  }
}

export function stop(): Promise<void> {
  activeScanId++;
  return enqueue(stopInternal);
}

/* ── Still images ───────────────────────────────────────────────────────────── */

interface NativeBarcodeDetector {
  detect(source: ImageBitmapSource): Promise<Array<{ rawValue?: string }>>;
}

interface NativeBarcodeDetectorConstructor {
  new (options?: { formats?: string[] }): NativeBarcodeDetector;
}

function createNativeQrDetector(): NativeBarcodeDetector | null {
  if (typeof window === 'undefined') {
    return null;
  }
  const ctor = (window as unknown as { BarcodeDetector?: NativeBarcodeDetectorConstructor })
    .BarcodeDetector;
  if (typeof ctor !== 'function') {
    return null;
  }
  try {
    return new ctor({ formats: ['qr_code'] });
  } catch {
    return null;
  }
}

async function detectWithNative(source: ImageBitmapSource): Promise<string | null> {
  const detector = createNativeQrDetector();
  if (!detector) {
    return null;
  }
  try {
    const results = await detector.detect(source);
    const match = results.find((entry) => typeof entry.rawValue === 'string' && entry.rawValue.trim() !== '');
    return match?.rawValue ?? null;
  } catch {
    return null;
  }
}

async function scanWithHtml5Qrcode(blob: Blob): Promise<string> {
  // html5-qrcode needs a mounted element even for file scanning.
  const host = document.createElement('div');
  host.style.display = 'none';
  document.body.append(host);
  host.id = `speedqr-file-${Math.random().toString(36).slice(2)}`;

  const reader = new Html5Qrcode(host.id, scannerOptions());

  try {
    const asFile =
      blob instanceof File ? blob : new File([blob], 'qr.png', { type: blob.type || 'image/png' });

    return await reader.scanFile(asFile, false);
  } finally {
    try {
      reader.clear();
    } catch {
      // Nothing to clear if the scan never started.
    }
    host.remove();
  }
}

function canvasToPngBlob(canvas: HTMLCanvasElement): Promise<Blob | null> {
  return new Promise((resolve) => {
    canvas.toBlob((blob) => resolve(blob), 'image/png');
  });
}

async function loadBlobImage(blob: Blob): Promise<HTMLImageElement> {
  const url = URL.createObjectURL(blob);
  try {
    return await loadImage(url);
  } finally {
    URL.revokeObjectURL(url);
  }
}

/**
 * Renders an image onto a white-padded canvas (restoring the ISO 18004 quiet
 * zone when a user snips tightly) with optional high-contrast binarization to
 * recover low-contrast scans or anti-aliased PDF previews.
 */
function prepareDecodeCanvas(
  image: HTMLImageElement,
  { binarize = false }: { binarize?: boolean } = {},
): HTMLCanvasElement {
  const srcW = Math.max(1, image.naturalWidth);
  const srcH = Math.max(1, image.naturalHeight);
  const scale = Math.max(1, Math.min(4, 600 / Math.max(srcW, srcH)));
  const drawW = Math.round(srcW * scale);
  const drawH = Math.round(srcH * scale);
  const pad = Math.max(32, Math.round(Math.max(drawW, drawH) * 0.15));

  const canvas = document.createElement('canvas');
  canvas.width = drawW + pad * 2;
  canvas.height = drawH + pad * 2;

  const ctx = canvas.getContext('2d');
  if (!ctx) {
    return canvas;
  }

  ctx.fillStyle = '#ffffff';
  ctx.fillRect(0, 0, canvas.width, canvas.height);
  ctx.imageSmoothingEnabled = scale <= 1;
  ctx.drawImage(image, pad, pad, drawW, drawH);

  if (binarize) {
    const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const data = imgData.data;
    let minLum = 255;
    let maxLum = 0;

    for (let i = 0; i < data.length; i += 4) {
      const lum = (data[i] * 77 + data[i + 1] * 150 + data[i + 2] * 29) >> 8;
      if (lum < minLum) minLum = lum;
      if (lum > maxLum) maxLum = lum;
    }

    const threshold = Math.round((minLum + maxLum) / 2);
    for (let i = 0; i < data.length; i += 4) {
      const lum = (data[i] * 77 + data[i + 1] * 150 + data[i + 2] * 29) >> 8;
      const v = lum < threshold ? 0 : 255;
      data[i] = v;
      data[i + 1] = v;
      data[i + 2] = v;
      data[i + 3] = 255;
    }
    ctx.putImageData(imgData, 0, 0);
  }

  return canvas;
}

/**
 * Decodes a QR out of a picture — a saved screenshot, a photo, a drop, or a
 * screen snip. Runs multiple passes (native BarcodeDetector, QR-focused ZXing,
 * quiet-zone-padded canvas, and high-contrast binarized fallback) so tight
 * snips and dense INT- QR codes decode reliably.
 */
export async function decodeImage(file: File | Blob): Promise<string> {
  // Pass 1: native BarcodeDetector on the raw image (fast and noise-tolerant).
  if (typeof createImageBitmap === 'function') {
    try {
      const bitmap = await createImageBitmap(file);
      try {
        const nativeHit = await detectWithNative(bitmap);
        if (nativeHit) {
          return nativeHit;
        }
      } finally {
        bitmap.close();
      }
    } catch {
      // Fall through to ZXing and padded retries.
    }
  }

  // Pass 2: html5-qrcode focused strictly on QR_CODE.
  try {
    return await scanWithHtml5Qrcode(file);
  } catch {
    // Fall through to padded and high-contrast retries.
  }

  // Pass 3 & 4: restore white quiet zone, then retry with binarization.
  try {
    const img = await loadBlobImage(file);

    for (const binarize of [false, true]) {
      const prepared = prepareDecodeCanvas(img, { binarize });
      const nativeHit = await detectWithNative(prepared);
      if (nativeHit) {
        return nativeHit;
      }

      const png = await canvasToPngBlob(prepared);
      if (!png) {
        continue;
      }

      try {
        return await scanWithHtml5Qrcode(png);
      } catch {
        // Try next preprocessing pass.
      }
    }
  } catch {
    // Image could not be loaded for fallback passes.
  }

  throw new Error('No QR code was found in that picture. Try a clearer, closer picture of it.');
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
    const scopedHost =
      backdropDataUrl === null
        ? document.querySelector<HTMLElement>('[data-qr-snip-target="true"]')
        : null;

    const overlay = document.createElement('div');
    overlay.className = scopedHost ? 'qr-snip qr-snip-scoped' : 'qr-snip';
    overlay.setAttribute('role', 'region');
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

    (scopedHost ?? document.body).append(overlay);

    let origin: { x: number; y: number } | null = null;
    let settled = false;

    const clampPoint = (clientX: number, clientY: number) => {
      if (!scopedHost) {
        return { x: clientX, y: clientY };
      }
      const hostRect = overlay.getBoundingClientRect();
      return {
        x: Math.max(hostRect.left, Math.min(hostRect.right, clientX)),
        y: Math.max(hostRect.top, Math.min(hostRect.bottom, clientY)),
      };
    };

    const drawSelectionBox = (left: number, top: number, width: number, height: number) => {
      if (scopedHost) {
        const hostRect = overlay.getBoundingClientRect();
        const safeW = Math.max(1, hostRect.width);
        const safeH = Math.max(1, hostRect.height);
        Object.assign(box.style, {
          left: `${((left - hostRect.left) / safeW) * 100}%`,
          top: `${((top - hostRect.top) / safeH) * 100}%`,
          width: `${(width / safeW) * 100}%`,
          height: `${(height / safeH) * 100}%`,
        });
      } else {
        Object.assign(box.style, {
          left: `${left}px`,
          top: `${top}px`,
          width: `${width}px`,
          height: `${height}px`,
        });
      }
    };

    const done = (result: BoxRect | null): void => {
      if (settled) return;
      settled = true;
      overlay.remove();
      window.removeEventListener('keydown', onKey);
      window.removeEventListener('cancel-qr-snip', onCancel);
      resolve(result);
    };

    const onKey = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') {
        done(null);
      }
    };

    const onCancel = (): void => {
      done(null);
    };

    overlay.addEventListener('pointerdown', (event) => {
      const point = clampPoint(event.clientX, event.clientY);
      origin = point;
      box.hidden = false;
      drawSelectionBox(origin.x, origin.y, 0, 0);
      try {
        overlay.setPointerCapture(event.pointerId);
      } catch {
        // Ignore capture failures on synthetic events
      }
    });

    overlay.addEventListener('pointermove', (event) => {
      if (!origin) {
        return;
      }

      const point = clampPoint(event.clientX, event.clientY);
      drawSelectionBox(
        Math.min(origin.x, point.x),
        Math.min(origin.y, point.y),
        Math.abs(point.x - origin.x),
        Math.abs(point.y - origin.y),
      );
    });

    overlay.addEventListener('pointerup', (event) => {
      if (!origin) {
        return done(null);
      }

      const point = clampPoint(event.clientX, event.clientY);
      const rect = {
        x: Math.min(origin.x, point.x),
        y: Math.min(origin.y, point.y),
        width: Math.abs(point.x - origin.x),
        height: Math.abs(point.y - origin.y),
      };

      // A click rather than a drag: treat as a cancel, not a 0×0 crop.
      done(rect.width < 12 || rect.height < 12 ? null : rect);
    });

    overlay.addEventListener('pointercancel', () => done(null));

    window.addEventListener('keydown', onKey);
    window.addEventListener('cancel-qr-snip', onCancel);
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
  const samplePoints: Array<[number, number]> = [
    [rect.x + rect.width / 2, rect.y + rect.height / 2],
    [rect.x + rect.width * 0.25, rect.y + rect.height * 0.25],
    [rect.x + rect.width * 0.75, rect.y + rect.height * 0.25],
    [rect.x + rect.width * 0.25, rect.y + rect.height * 0.75],
    [rect.x + rect.width * 0.75, rect.y + rect.height * 0.75],
  ];

  let target: HTMLImageElement | HTMLCanvasElement | undefined;

  for (const [px, py] of samplePoints) {
    const hit = document
      .elementsFromPoint(px, py)
      .find(
        (node): node is HTMLImageElement | HTMLCanvasElement =>
          node instanceof HTMLImageElement || node instanceof HTMLCanvasElement,
      );
    if (hit) {
      target = hit;
      break;
    }
  }

  if (!target) {
    const scopedHost = document.querySelector<HTMLElement>('[data-qr-snip-target="true"]');
    const candidates = scopedHost
      ? Array.from(scopedHost.querySelectorAll<HTMLImageElement | HTMLCanvasElement>('img, canvas'))
      : [];
    target = candidates.find((el) => {
      const b = el.getBoundingClientRect();
      return (
        b.width > 0 &&
        b.height > 0 &&
        b.left < rect.x + rect.width &&
        b.right > rect.x &&
        b.top < rect.y + rect.height &&
        b.bottom > rect.y
      );
    });
  }

  if (!target) {
    throw new Error('No QR image was found in that box. Drag it around the QR code on the document.');
  }

  const bounds = target.getBoundingClientRect();
  const intrinsicWidth =
    target instanceof HTMLCanvasElement ? target.width : target.naturalWidth;
  const intrinsicHeight =
    target instanceof HTMLCanvasElement ? target.height : target.naturalHeight;

  if (!intrinsicWidth || !intrinsicHeight || !bounds.width || !bounds.height) {
    throw new Error('The document preview is still loading. Try again in a moment.');
  }

  const scaleX = intrinsicWidth / bounds.width;
  const scaleY = intrinsicHeight / bounds.height;

  const source =
    target instanceof HTMLCanvasElement || (target.complete && target.naturalWidth > 0)
      ? target
      : await loadImage(target.currentSrc || target.src);

  const sx = Math.max(0, (rect.x - bounds.left) * scaleX);
  const sy = Math.max(0, (rect.y - bounds.top) * scaleY);
  const sw = Math.min(intrinsicWidth - sx, rect.width * scaleX);
  const sh = Math.min(intrinsicHeight - sy, rect.height * scaleY);

  return cropImage(source, sx, sy, Math.max(1, sw), Math.max(1, sh));
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
  image: HTMLImageElement | HTMLCanvasElement,
  x: number,
  y: number,
  width: number,
  height: number,
): Promise<Blob> {
  const canvas = document.createElement('canvas');
  // Upscale small crops with crisp module edges and add a white quiet zone so
  // tight selections still satisfy ISO/IEC 18004 finder-pattern detection.
  const scale = Math.max(1, Math.min(4, 600 / Math.max(width, height)));
  const drawW = Math.round(width * scale);
  const drawH = Math.round(height * scale);
  const pad = Math.max(24, Math.round(Math.max(drawW, drawH) * 0.12));

  canvas.width = drawW + pad * 2;
  canvas.height = drawH + pad * 2;

  const ctx = canvas.getContext('2d')!;
  ctx.fillStyle = '#ffffff';
  ctx.fillRect(0, 0, canvas.width, canvas.height);
  ctx.imageSmoothingEnabled = scale <= 1;
  ctx.drawImage(image, x, y, width, height, pad, pad, drawW, drawH);

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
    body: JSON.stringify({ scanned_value: decodedText, capture_method: method }),
  });

  const result = (await response.json().catch(() => null)) as { redirect?: string; message?: string } | null;

  if (!response.ok) {
    throw new Error(result?.message ?? 'That request could not be opened.');
  }

  window.location.assign(result?.redirect ?? `/internal-requests/track?number=${encodeURIComponent(tracking)}`);
}
