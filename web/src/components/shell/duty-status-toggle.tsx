'use client';

import { useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import {
  toggleMyAvailabilityAction,
  toggleUserAvailabilityAction,
} from '@/app/actions/availability';
import { useConfirm } from '@/components/confirm-dialog';

export function DutyStatusToggle({
  isAvailable,
  userId,
  size = 'md',
  showLabel = true,
}: {
  isAvailable: boolean;
  userId?: number;
  size?: 'sm' | 'md';
  showLabel?: boolean;
}) {
  const router = useRouter();
  const [available, setAvailable] = useState(isAvailable);
  const [isPending, startTransition] = useTransition();
  const confirm = useConfirm();

  const handleToggle = async (e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();

    const next = !available;
    const self = userId === undefined;

    // Going On Leave hands their not-yet-accepted requests to someone else.
    const confirmed = await confirm(
      next
        ? {
            title: self ? 'Mark yourself On Duty?' : 'Mark them On Duty?',
            message: 'New requests can be assigned again straight away.',
            confirmLabel: 'On Duty',
          }
        : {
            title: self ? 'Go On Leave?' : 'Put them On Leave?',
            message:
              'Requests assigned but not yet accepted are handed to another on-duty handler, or to the office pool. Work already accepted stays put.',
            confirmLabel: 'On Leave',
            tone: 'danger',
          },
    );

    if (!confirmed) {
      return;
    }

    setAvailable(next);

    startTransition(async () => {
      const res = userId !== undefined
        ? await toggleUserAvailabilityAction(userId)
        : await toggleMyAvailabilityAction();

      if (!res.ok) {
        // Revert on failure
        setAvailable(!next);
      } else {
        router.refresh();
      }
    });
  };

  const isSmall = size === 'sm';

  return (
    <button
      type="button"
      onClick={handleToggle}
      disabled={isPending}
      aria-label={`Duty status: ${available ? 'On Duty' : 'On Leave'}. Click to toggle.`}
      title={`Click to mark as ${available ? 'On Leave' : 'On Duty'}`}
      className={`inline-flex items-center gap-1.5 rounded-full font-semibold transition-all shadow-xs cursor-pointer select-none focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-1 disabled:opacity-60 ${
        isSmall ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-1 text-xs'
      } ${
        available
          ? 'border border-emerald-300/80 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 hover:border-emerald-400 focus-visible:ring-emerald-500'
          : 'border border-amber-300/80 bg-amber-50 text-amber-800 hover:bg-amber-100 hover:border-amber-400 focus-visible:ring-amber-500'
      }`}
    >
      <span
        className={`shrink-0 rounded-full transition-colors ${
          isSmall ? 'h-1.5 w-1.5' : 'h-2 w-2'
        } ${available ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'}`}
        aria-hidden="true"
      />
      {showLabel ? (
        <span className="leading-none whitespace-nowrap">
          {available ? 'On Duty' : 'On Leave'}
        </span>
      ) : null}
    </button>
  );
}
