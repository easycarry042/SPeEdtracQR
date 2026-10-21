import Link from 'next/link';
import type { Kpis } from '@/lib/domain/admin-analytics';
import { fromDays } from '@/lib/domain/duration';
import { routes } from '@/lib/routes';

/**
 * A one-line glance at the office's last 30 days, from the same numbers as
 * Reports.
 *
 * The queue stays the dashboard's job and the charts live on Reports — this is
 * the link between them, not a second analytics page.
 */
export function GlanceStrip({ glance }: { glance: Kpis }) {
  return (
    <div className="glance-strip">
      <span className="glance-label">Last 30 days</span>

      <span>
        <strong>{glance.total.value}</strong> filed
      </span>
      <span>
        <strong>{glance.completed.value}</strong> completed
      </span>
      <span>
        <strong>{fromDays(glance.avgProcessing.value)}</strong> average
        {glance.avgProcessing.sample > 0 ? (
          <span className="text-ink-soft"> ({glance.avgProcessing.sample})</span>
        ) : null}
      </span>

      {glance.overdue.value > 0 ? (
        <span className="cc-tag cc-tag-late">{glance.overdue.value} overdue</span>
      ) : null}
      {glance.atRisk.value > 0 ? (
        <span className="cc-tag">{glance.atRisk.value} at risk</span>
      ) : null}

      <Link href={routes.reportsIndex()} className="glance-link">
        Open Reports
      </Link>
    </div>
  );
}
