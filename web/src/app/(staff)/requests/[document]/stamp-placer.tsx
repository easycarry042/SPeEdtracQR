'use client';

import { useEffect, useRef, useState } from 'react';
import { renderPdfUrlPage } from '@/lib/client/pdf-preview';

/**
 * Where an office puts its RECEIVED stamp on the request's file, chosen at
 * approval. The box is dragged over a preview of the newest image or PDF page;
 * the placement it reports is normalised the way `office-stamp.ts` reads it:
 * `x`/`y` the box's top-left (0–1), `size` its width as a fraction of the page
 * width, `page` the 1-based PDF page.
 */
export type StampChoice = { x: number; y: number; size: number; page: number };

/** Height : width of the stamp, as `office-stamp.ts` draws it. */
const ASPECT = 0.46;
const MIN_SIZE = 0.2;
const MAX_SIZE = 0.6;
const DEFAULT_SIZE = 0.36;
const MARGIN = 0.04;
/** Stamps per column before the default spot moves to the next column. */
const PER_COLUMN = 5;

/**
 * The default spot for the office's stamp: the top-right corner for the first,
 * then down the right margin (and on to the next column) past the stamps
 * earlier offices put there. Assumes a portrait page; dragging corrects it.
 */
function defaultSpot(stampsBefore: number) {
  const row = stampsBefore % PER_COLUMN;
  const column = Math.floor(stampsBefore / PER_COLUMN) % 2;
  const height = DEFAULT_SIZE * ASPECT * 0.77;

  return {
    x: column === 0 ? 1 - DEFAULT_SIZE - MARGIN : MARGIN,
    y: MARGIN + row * (height + 0.02),
    size: DEFAULT_SIZE,
  };
}

export function StampPlacer({
  target,
  office,
  stampsBefore,
  onChange,
}: {
  target: { url: string; ext: string };
  office: string;
  /** Office stamps already on the file. */
  stampsBefore: number;
  onChange: (choice: StampChoice | null) => void;
}) {
  const isPdf = target.ext === 'pdf';
  const stage = useRef<HTMLDivElement>(null);
  const [src, setSrc] = useState<string | null>(isPdf ? null : target.url);
  const [page, setPage] = useState(1);
  const [pageCount, setPageCount] = useState(1);
  const [problem, setProblem] = useState<string | null>(null);
  // Page width : height, to keep the box's height on the page while dragging.
  const [ratio, setRatio] = useState<number | null>(null);
  const [box, setBox] = useState(() => defaultSpot(stampsBefore));

  useEffect(() => {
    if (!isPdf) {
      return;
    }

    let active = true;
    setSrc(null);

    void renderPdfUrlPage(target.url, page)
      .then((result) => {
        if (!active) return;
        setSrc(result.dataUrl);
        setPageCount(result.pageCount);
      })
      .catch(() => {
        if (active) setProblem('The PDF could not be previewed. The stamp goes at the top-right of page 1.');
      });

    return () => {
      active = false;
    };
  }, [isPdf, target.url, page]);

  const heightOf = (size: number): number => (ratio ? size * ASPECT * ratio : size * ASPECT);

  const clamp = (next: { x: number; y: number; size: number }) => ({
    size: next.size,
    x: Math.max(0, Math.min(1 - next.size, next.x)),
    y: Math.max(0, Math.min(1 - heightOf(next.size), next.y)),
  });

  // Report the placement whenever it (or the page) changes.
  useEffect(() => {
    onChange({ x: box.x, y: box.y, size: box.size, page });
  }, [box, page, onChange]);

  const startDrag = (event: React.PointerEvent<HTMLDivElement>): void => {
    const rect = stage.current?.getBoundingClientRect();

    if (!rect) {
      return;
    }

    event.preventDefault();
    event.currentTarget.setPointerCapture(event.pointerId);

    const start = { px: event.clientX, py: event.clientY, x: box.x, y: box.y };
    const target = event.currentTarget;

    const move = (moveEvent: PointerEvent): void => {
      setBox((current) =>
        clamp({
          ...current,
          x: start.x + (moveEvent.clientX - start.px) / rect.width,
          y: start.y + (moveEvent.clientY - start.py) / rect.height,
        }),
      );
    };
    const stop = (): void => {
      target.removeEventListener('pointermove', move);
      target.removeEventListener('pointerup', stop);
      target.removeEventListener('pointercancel', stop);
    };

    target.addEventListener('pointermove', move);
    target.addEventListener('pointerup', stop);
    target.addEventListener('pointercancel', stop);
  };

  const nudge = (event: React.KeyboardEvent<HTMLDivElement>): void => {
    const step = event.shiftKey ? 0.05 : 0.01;
    const delta: Record<string, [number, number]> = {
      ArrowLeft: [-step, 0],
      ArrowRight: [step, 0],
      ArrowUp: [0, -step],
      ArrowDown: [0, step],
    };
    const move = delta[event.key];

    if (move) {
      event.preventDefault();
      setBox((current) => clamp({ ...current, x: current.x + move[0], y: current.y + move[1] }));
    }
  };

  return (
    <div className="mt-2 space-y-2">
      <div className="ff-stage">
        {problem ? (
          <p className="px-4 text-center text-[length:var(--fs-small)] font-semibold text-status-amber">{problem}</p>
        ) : !src ? (
          <p className="text-[length:var(--fs-small)] font-semibold text-ink-soft">Opening the file…</p>
        ) : (
          <div ref={stage} className="relative inline-block select-none leading-none">
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              src={src}
              alt="The request's file"
              className="block max-h-[330px] w-auto rounded-[4px] shadow-sm"
              draggable={false}
              onLoad={(event) => {
                const image = event.currentTarget;
                setRatio(image.naturalWidth / Math.max(1, image.naturalHeight));
              }}
              onError={() => setProblem('The file could not be previewed. The stamp goes at the top-right.')}
            />
            <div
              role="slider"
              tabIndex={0}
              aria-label="RECEIVED stamp position. Use the arrow keys to move it."
              aria-valuetext={`${Math.round(box.x * 100)}% from the left, ${Math.round(box.y * 100)}% from the top`}
              className="office-stamp-box"
              style={{
                left: `${box.x * 100}%`,
                top: `${box.y * 100}%`,
                width: `${box.size * 100}%`,
                aspectRatio: `1 / ${ASPECT}`,
              }}
              onPointerDown={startDrag}
              onKeyDown={nudge}
            >
              <StampGlyph office={office} />
            </div>
          </div>
        )}
      </div>

      <div className="flex flex-wrap items-center gap-3">
        <span className="text-[length:var(--fs-caption)] font-semibold text-ink-soft">Size</span>
        <input
          type="range"
          min={MIN_SIZE}
          max={MAX_SIZE}
          step="0.01"
          value={box.size}
          onChange={(event) => setBox((current) => clamp({ ...current, size: Number(event.target.value) }))}
          className="h-1.5 min-w-[120px] flex-1 accent-green"
          aria-label="Stamp size"
        />
        {isPdf && pageCount > 1 ? (
          <span className="flex items-center gap-1 text-[length:var(--fs-caption)] text-ink-soft">
            <button
              type="button"
              className="cr-btn cr-btn-sm"
              disabled={page <= 1}
              onClick={() => setPage((current) => Math.max(1, current - 1))}
              aria-label="Previous page"
            >
              ‹
            </button>
            Page {page} of {pageCount}
            <button
              type="button"
              className="cr-btn cr-btn-sm"
              disabled={page >= pageCount}
              onClick={() => setPage((current) => Math.min(pageCount, current + 1))}
              aria-label="Next page"
            >
              ›
            </button>
          </span>
        ) : null}
      </div>
    </div>
  );
}

/** A preview of the stamp's words, scaled with the box. */
function StampGlyph({ office }: { office: string }) {
  return (
    <svg viewBox="0 0 100 46" className="block h-full w-full" aria-hidden="true">
      <g fill="#1d3f91" fontFamily="Arial, Helvetica, sans-serif" textAnchor="middle">
        <text x="50" y="8.5" fontSize="4" fontWeight="600">CITY OF SAN PEDRO, LAGUNA</text>
        <text x="50" y="16.5" fontSize={Math.min(5, 100 / Math.max(1, office.length * 0.62))} fontWeight="700">
          {office.toUpperCase()}
        </text>
        <text x="50" y="28" fontSize="9" fontWeight="800">RECEIVED</text>
        <text x="50" y="36" fontSize="4" fontWeight="600">BY: your name</text>
        <text x="50" y="42.5" fontSize="3.8" fontWeight="600">DATE · TIME of approval</text>
      </g>
    </svg>
  );
}
