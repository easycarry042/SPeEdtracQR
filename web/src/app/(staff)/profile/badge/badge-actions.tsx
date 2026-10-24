'use client';

import { useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import { reissueBadge } from '@/app/actions/profile';

export function BadgeActions() {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [message, setMessage] = useState<string | null>(null);

  return (
    <>
      {message ? (
        <div className="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
          {message}
        </div>
      ) : null}
      <div className="mt-4 flex flex-wrap items-center justify-between gap-3 print:hidden">
        <button type="button" onClick={() => window.print()} className="cr-btn cr-btn-primary">
          Print badge
        </button>
        <button
          type="button"
          disabled={pending}
          className="cr-btn cr-btn-sm"
          onClick={() => {
            if (window.confirm('Issue a new badge? The card you printed before will stop working.')) {
              startTransition(async () => {
                const result = await reissueBadge();
                setMessage(result.message);
                router.refresh();
              });
            }
          }}
        >
          Badge lost — issue a new one
        </button>
      </div>
    </>
  );
}
