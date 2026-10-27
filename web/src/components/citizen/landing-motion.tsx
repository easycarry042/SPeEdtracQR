'use client';

import { useEffect } from 'react';

/**
 * The landing page's motion layer, in one place so the page itself stays a
 * server component. It renders nothing; on mount it wires three things up:
 *
 * - **Entrances.** Every `[data-reveal]` element that is still below the fold
 *   is held back (`lp-pending`) and rises in as it enters. Elements already on
 *   screen are left alone, and without JavaScript nothing is ever hidden.
 * - **How it works.** `[data-steps]` carries a `--fill` (0–1) that follows the
 *   scroll, and each `[data-step]` gets `data-on` once the line reaches it.
 * - **Feature cards on touch screens.** With no hover to light a card, the
 *   card crossing the middle of the screen gets `data-active` instead.
 *
 * With reduced motion asked for, nothing travels: the steps all show as
 * reached and no entrance is held back.
 */
export function LandingMotion() {
  useEffect(() => {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const cleanups: Array<() => void> = [];

    // ── Entrances ──────────────────────────────────────────────────────────
    if (!reduceMotion) {
      const pending = Array.from(document.querySelectorAll<HTMLElement>('[data-reveal]')).filter(
        (element) => element.getBoundingClientRect().top > window.innerHeight,
      );
      pending.forEach((element) => element.classList.add('lp-pending'));

      const entering = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              entry.target.classList.add('lp-in');
              entering.unobserve(entry.target);
            }
          });
        },
        { rootMargin: '0px 0px -10% 0px' },
      );
      pending.forEach((element) => entering.observe(element));
      cleanups.push(() => entering.disconnect());
    }

    // ── How it works: the line fills as the section scrolls past ───────────
    const steps = document.querySelector<HTMLElement>('[data-steps]');
    if (steps) {
      const items = Array.from(steps.querySelectorAll<HTMLElement>('[data-step]'));
      const paint = (progress: number): void => {
        steps.style.setProperty('--fill', progress.toFixed(3));
        items.forEach((item, index) => {
          item.toggleAttribute('data-on', progress >= index / Math.max(1, items.length - 1) - 0.001);
        });
      };

      if (reduceMotion) {
        paint(1);
      } else {
        let frame = 0;
        const update = (): void => {
          cancelAnimationFrame(frame);
          frame = requestAnimationFrame(() => {
            // From the list's top at 80% of the viewport to its bottom at 45%.
            const box = steps.getBoundingClientRect();
            const start = window.innerHeight * 0.8;
            const end = window.innerHeight * 0.45 - box.height;
            paint(Math.min(1, Math.max(0, (start - box.top) / (start - end))));
          });
        };
        update();
        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        cleanups.push(() => {
          cancelAnimationFrame(frame);
          window.removeEventListener('scroll', update);
          window.removeEventListener('resize', update);
        });
      }
    }

    // ── Feature cards: light the one in the middle of a touch screen ──────
    if (window.matchMedia('(hover: none)').matches) {
      const centred = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => entry.target.toggleAttribute('data-active', entry.isIntersecting));
        },
        { rootMargin: '-45% 0px -45% 0px' },
      );
      document.querySelectorAll('[data-feature-card]').forEach((card) => centred.observe(card));
      cleanups.push(() => centred.disconnect());
    }

    return () => cleanups.forEach((cleanup) => cleanup());
  }, []);

  return null;
}
