'use client';

import { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

/**
 * The "Are you sure?" step in front of every critical action.
 *
 * `useConfirm()` returns `confirm(options)`, a promise that settles true only
 * when the person presses the confirm button. One dialog is mounted by the
 * signed-in shell (`AppShell`); outside it the hook falls back to the browser's
 * `window.confirm`, so a component never has to know where it is rendered.
 *
 * Styled in-page rather than `window.confirm`, which cannot be styled and is
 * suppressed outright in some embedded browsers.
 */
export type ConfirmOptions = {
  title: string;
  message?: React.ReactNode;
  confirmLabel?: string;
  cancelLabel?: string;
  /** `danger` for anything that removes, denies or cannot be taken back. */
  tone?: 'default' | 'danger';
};

type Confirm = (options: ConfirmOptions) => Promise<boolean>;

const browserConfirm: Confirm = async (options) =>
  typeof window === 'undefined'
    ? false
    : window.confirm(
        typeof options.message === 'string' ? `${options.title}\n\n${options.message}` : options.title,
      );

const ConfirmContext = createContext<Confirm>(browserConfirm);

export function useConfirm(): Confirm {
  return useContext(ConfirmContext);
}

type Open = ConfirmOptions & { resolve: (ok: boolean) => void };

export function ConfirmProvider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useState<Open | null>(null);
  const confirmButton = useRef<HTMLButtonElement>(null);
  const returnFocus = useRef<HTMLElement | null>(null);

  const confirm = useCallback<Confirm>(
    (options) =>
      new Promise<boolean>((resolve) => {
        returnFocus.current = document.activeElement as HTMLElement | null;
        setOpen((current) => {
          // A second ask while one is showing cancels the first.
          current?.resolve(false);

          return { ...options, resolve };
        });
      }),
    [],
  );

  const settle = useCallback(
    (ok: boolean): void => {
      open?.resolve(ok);
      setOpen(null);
      returnFocus.current?.focus?.();
    },
    [open],
  );

  useEffect(() => {
    if (!open) {
      return;
    }

    confirmButton.current?.focus();

    const onKey = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') {
        event.stopPropagation();
        settle(false);
      }
    };

    // Capture, so Escape closes this dialog and not the modal underneath it.
    document.addEventListener('keydown', onKey, true);

    return () => document.removeEventListener('keydown', onKey, true);
  }, [open, settle]);

  return (
    <ConfirmContext.Provider value={confirm}>
      {children}
      {open
        ? createPortal(
            <div
              className="gate-modal confirm-modal"
              role="alertdialog"
              aria-modal="true"
              aria-labelledby="confirmTitle"
              aria-describedby={open.message ? 'confirmMessage' : undefined}
              onClick={(event) => {
                if (event.target === event.currentTarget) {
                  settle(false);
                }
              }}
            >
              <div className="gate-modal-card">
                <h3 id="confirmTitle" className="text-base font-bold text-ink">
                  {open.title}
                </h3>
                {open.message ? (
                  <div id="confirmMessage" className="mt-1.5 text-[length:var(--fs-small)] text-ink-soft">
                    {open.message}
                  </div>
                ) : null}
                <div className="gate-actions mt-4 justify-end">
                  <button type="button" className="cr-btn cr-btn-ghost" onClick={() => settle(false)}>
                    {open.cancelLabel ?? 'Cancel'}
                  </button>
                  <button
                    ref={confirmButton}
                    type="button"
                    className={`cr-btn ${open.tone === 'danger' ? 'cr-btn-danger-solid' : 'cr-btn-primary'}`}
                    onClick={() => settle(true)}
                  >
                    {open.confirmLabel ?? 'Confirm'}
                  </button>
                </div>
              </div>
            </div>,
            document.body,
          )
        : null}
    </ConfirmContext.Provider>
  );
}
