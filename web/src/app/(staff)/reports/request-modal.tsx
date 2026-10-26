'use client';

import { useRouter } from 'next/navigation';
import type { ReviewRow } from '@/lib/domain/request-review';
import { ReviewModal } from '@/components/review/review-modal';

/**
 * A request opened from Reports, in the same Review modal the Dashboard uses.
 * The open request lives in the URL (`?open=SPD-…`); closing returns to the
 * report with its filters.
 */
export function ReportsRequestModal({
  row,
  assignableStaff,
  closeHref,
}: {
  row: ReviewRow;
  assignableStaff: Array<{ id: number; name: string; is_available?: boolean }>;
  closeHref: string;
}) {
  const router = useRouter();

  return (
    <ReviewModal
      row={row}
      mode="supervisor"
      assignableStaff={assignableStaff}
      onClose={() => router.replace(closeHref, { scroll: false })}
    />
  );
}
