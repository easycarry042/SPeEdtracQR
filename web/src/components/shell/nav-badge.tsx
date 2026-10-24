'use client';

import { useEffect, useState } from 'react';
import { routes } from '@/lib/routes';

/**
 * A sidebar count. Rendered fresh with every page, and kept current in between
 * by a one-minute poll of `nav.badges` — the same cadence the Blade layout's
 * poller used.
 *
 * The number is work waiting, not things unseen: it falls when the work is
 * picked up, not when the page is opened.
 */
type BadgeName = 'home' | 'internal' | 'resources';

export function NavBadge({
  name,
  initial,
  what,
}: {
  name: BadgeName;
  initial: number;
  what: string;
}) {
  const [count, setCount] = useState(initial);

  // A soft navigation re-renders the sidebar with fresh counts; adopt them so
  // the poll's stale value does not win over a just-rendered one.
  useEffect(() => setCount(initial), [initial]);

  useEffect(() => {
    const poll = async (): Promise<void> => {
      try {
        const response = await fetch(routes.navBadges(), { headers: { Accept: 'application/json' } });

        if (response.ok) {
          const badges = (await response.json()) as Record<BadgeName, number>;
          setCount(badges[name] ?? 0);
        }
      } catch {
        // Offline or mid-deploy; the next tick tries again.
      }
    };

    const timer = setInterval(() => void poll(), 60_000);

    return () => clearInterval(timer);
  }, [name]);

  if (count <= 0) {
    return null;
  }

  return (
    <>
      <span className="sb-badge" aria-live="polite">
        {count > 99 ? '99+' : count}
      </span>
      <span className="sr-only">{` (${count} ${what})`}</span>
    </>
  );
}
