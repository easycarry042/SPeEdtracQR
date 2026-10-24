'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import type { CompletedPage } from '@/lib/domain/completed-history';
import { routes } from '@/lib/routes';

/**
 * The dashboards' Completed tab: finished requests fetched from `history.data`
 * a page at a time (the search runs on the server), so the full history never
 * rides in the page payload. Only the latest request may land — typing fires
 * several.
 */
export function useCompletedHistory(enabled: boolean, search: string) {
  const [state, setState] = useState<{
    data: CompletedPage | null;
    loading: boolean;
    failed: boolean;
  }>({ data: null, loading: false, failed: false });
  const ticket = useRef(0);

  const load = useCallback(
    async (page: number): Promise<void> => {
      const mine = ++ticket.current;
      setState((current) => ({ ...current, loading: true, failed: false }));

      try {
        const query: Record<string, string> = { page: String(page) };

        if (search.trim() !== '') {
          query.search = search.trim();
        }

        const response = await fetch(routes.historyData(query), { headers: { Accept: 'application/json' } });

        if (!response.ok) {
          throw new Error(String(response.status));
        }

        const data = (await response.json()) as CompletedPage;

        if (mine === ticket.current) {
          setState({ data, loading: false, failed: false });
        }
      } catch {
        if (mine === ticket.current) {
          setState((current) => ({ ...current, loading: false, failed: true }));
        }
      }
    },
    [search],
  );

  // Back to page 1 whenever the tab opens or the search changes.
  useEffect(() => {
    if (enabled) {
      void load(1);
    }
  }, [enabled, load]);

  return {
    ...state,
    load,
    /** Re-read the current page, after an action changed a row in it. */
    reload: () => load(state.data?.page ?? 1),
  };
}
