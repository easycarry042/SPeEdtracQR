'use client';

import { Suspense, useEffect, useRef } from 'react';
import { usePathname, useSearchParams } from 'next/navigation';

/**
 * The thin green bar along the top of the window while the next page loads
 * (`.soft-nav-bar` in globals.css), the one the old app showed between pages.
 *
 * It starts on a click on an ordinary same-site link that leads somewhere new
 * (sidebar rows, tabs, header buttons) and finishes when the new address has
 * rendered. Links that open a new tab, download, or only jump within the page
 * leave it alone; a navigation that never lands stops it after a while.
 */
export function NavProgress() {
  return (
    <Suspense fallback={null}>
      <Bar />
    </Suspense>
  );
}

const GIVE_UP_MS = 10000;

function Bar() {
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const bar = useRef<HTMLDivElement>(null);
  const giveUp = useRef<ReturnType<typeof setTimeout> | null>(null);

  // The new page has rendered: run the bar out and fade it.
  useEffect(() => {
    const element = bar.current;

    if (!element?.classList.contains('is-loading')) {
      return;
    }

    if (giveUp.current) {
      clearTimeout(giveUp.current);
    }

    element.classList.remove('is-loading');
    element.classList.add('is-done');
  }, [pathname, searchParams]);

  useEffect(() => {
    const start = (): void => {
      const element = bar.current;

      if (!element) {
        return;
      }

      element.classList.remove('is-done');
      // Restart from zero so the 70% creep runs again.
      void element.offsetWidth;
      element.classList.add('is-loading');

      if (giveUp.current) {
        clearTimeout(giveUp.current);
      }

      giveUp.current = setTimeout(() => {
        element.classList.remove('is-loading');
        element.classList.add('is-done');
      }, GIVE_UP_MS);
    };

    const onClick = (event: MouseEvent): void => {
      if (
        event.defaultPrevented ||
        event.button !== 0 ||
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.altKey
      ) {
        return;
      }

      const link = (event.target as Element | null)?.closest?.('a[href]');

      if (!(link instanceof HTMLAnchorElement) || link.hasAttribute('download')) {
        return;
      }

      if (link.target && link.target !== '_self') {
        return;
      }

      const next = new URL(link.href, window.location.href);
      const here = new URL(window.location.href);

      if (next.origin !== here.origin) {
        return;
      }

      // Same page, or only a jump to an anchor on it.
      if (next.pathname === here.pathname && next.search === here.search) {
        return;
      }

      start();
    };

    // Capture: Next's <Link> prevents the default on bubble, which would hide
    // the click from a bubbling listener's `defaultPrevented` check.
    document.addEventListener('click', onClick, true);

    return () => {
      document.removeEventListener('click', onClick, true);

      if (giveUp.current) {
        clearTimeout(giveUp.current);
      }
    };
  }, []);

  return <div ref={bar} className="soft-nav-bar" aria-hidden="true" />;
}
