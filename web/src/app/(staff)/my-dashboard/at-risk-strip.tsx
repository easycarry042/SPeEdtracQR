import type { SlaStanding } from '@/lib/domain/sla-watch';
import { percentUsed } from '@/lib/domain/sla-watch';
import { fromHours } from '@/lib/domain/duration';

/**
 * The at-risk warning, on the staff cockpit rather than the supervisor desk:
 * the supervisor assigns, the staff member advances, so the warning belongs to
 * whoever can actually act on it.
 *
 * Ordered worst first. Past the cap the overrun is reported in hours beside the
 * bar, because a bar that can only ever be full says nothing about how late a
 * thing is.
 */
type Row = {
  id: number;
  trackingNumber: string;
  documentType: string;
  sla: SlaStanding;
};

export function AtRiskStrip({ rows }: { rows: Row[] }) {
  return (
    <section className="callout p-amber mb-5">
      <h2 className="text-sm font-bold text-ink">
        {rows.length} request{rows.length === 1 ? '' : 's'} need attention
      </h2>

      <ul className="mt-3 space-y-2">
        {rows.slice(0, 5).map((row) => (
          <li key={row.id} className="flex flex-wrap items-center gap-3 text-[length:var(--fs-small)]">
            <span className="id-chip">{row.trackingNumber}</span>
            <span className="min-w-0 flex-1 truncate">{row.documentType}</span>

            <span className="inv-bar w-[120px]" aria-hidden="true">
              <span
                className="inv-bar-fill"
                style={{ width: `${percentUsed(row.sla)}%` }}
              />
            </span>

            <span className={row.sla.overdue ? 'cc-tag cc-tag-late' : 'cc-tag'}>
              {row.sla.overdue
                ? `${fromHours(row.sla.hoursOver)} over`
                : `${fromHours(row.sla.remainingHours)} left`}
            </span>
          </li>
        ))}
      </ul>

      {rows.length > 5 ? (
        <p className="mt-2 text-[length:var(--fs-small)] text-ink-soft">
          …and {rows.length - 5} more.
        </p>
      ) : null}
    </section>
  );
}
