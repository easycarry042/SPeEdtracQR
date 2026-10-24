import Link from 'next/link';
import type { Kpis } from '@/lib/domain/admin-analytics';
import { fromDays } from '@/lib/domain/duration';
import { routes } from '@/lib/routes';

const number = (value: number): string => value.toLocaleString('en-US');

/**
 * Port of the glance strip in `dashboard.blade.php` (lines 44–57).
 * One line from Reports instead of its charts: the queue below is this page's
 * job, so the numbers stay a glance and a link.
 */
export function GlanceStrip({ glance }: { glance: Kpis }) {
  const overdue = glance.overdue.value;

  return (
    <Link href={routes.reportsIndex()} className="glance-strip">
      <span className="glance-label">Last 30 days</span>
      <span>
        <b>{number(glance.total.value)}</b> filed
      </span>
      <span>
        <b>{number(glance.completed.value)}</b> completed
      </span>
      <span>
        avg. <b>{fromDays(glance.avgProcessing.value)}</b> to complete
      </span>
      <span className={overdue > 0 ? 'is-late' : undefined}>
        <b>{number(overdue)}</b> overdue now
      </span>
      <span className="glance-link">Open reports →</span>
    </Link>
  );
}
