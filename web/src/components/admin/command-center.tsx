import Link from 'next/link';
import { StaffStatTile } from '@/components/staff-stat-tile';
import { NotifyButton } from '@/components/review/notify-dialog';
import type { CommandCenterData } from '@/lib/domain/command-center';
import type { Delta } from '@/lib/domain/admin-analytics';
import { RANGES } from '@/lib/domain/command-center';
import { label as statusLabel, band as statusBand, DocumentStatus } from '@/lib/domain/document-status';
import { fromDays } from '@/lib/domain/duration';
import { forHumans } from '@/lib/time';

/**
 * The numbers page shared by the super admin's command center
 * (`/admin/dashboard`, the city) and a supervisor's Reports (`/reports`, their
 * office). `audience` says which.
 *
 * Office mode keeps every link on pages a supervisor can open: a request links
 * to the citizen tracking page rather than the oversight list, "Requests by
 * Office" becomes "Bookings by Resource", and the comparison panels are dropped
 * — one office has nothing to compare itself against.
 */
const number = (value: number): string => value.toLocaleString('en-US');

function deltaText(delta: Delta): string | null {
  if (!delta) {
    return null;
  }

  const arrow = delta.direction === 'up' ? '▲' : delta.direction === 'down' ? '▼' : '·';

  return `${arrow} ${delta.value > 0 ? '+' : ''}${delta.value}%`;
}

/** Sparkline points in a 0..100 x 0..30 box. */
function sparkPoints(values: number[]): string | null {
  if (values.length === 0) {
    return null;
  }

  const max = Math.max(1, ...values);

  return values
    .map((value, index) => {
      const x = values.length > 1 ? (index * 100) / (values.length - 1) : 0;

      return `${x.toFixed(2)},${(28 - (value / max) * 26).toFixed(2)}`;
    })
    .join(' ');
}

function Panel({
  title,
  sub,
  children,
  className = '',
  role,
}: {
  title: string;
  sub?: string;
  children: React.ReactNode;
  className?: string;
  role?: 'alert';
}) {
  return (
    <section className={`cc-panel ${className}`.trim()} role={role}>
      <div className="cc-panel-head">
        <h2 className="cc-panel-title">{title}</h2>
        {sub ? <p className="cc-panel-sub">{sub}</p> : null}
      </div>
      <div className="cc-panel-body">{children}</div>
    </section>
  );
}

/**
 * A comparison bar row. The bar is read against the busiest row, not against
 * the total, so each row prints its own COUNT beside the bar — it is a
 * comparison between offices, not a share of a whole.
 */
function Bars({ rows }: { rows: Array<{ label: string; value: number; pct: number }> }) {
  if (rows.length === 0) {
    return <p className="cc-panel-sub">Nothing in this range.</p>;
  }

  return (
    <div className="cc-bars">
      {rows.map((row) => (
        <div key={row.label} className="cc-bar-row">
          <span className="cc-bar-label">{row.label}</span>
          <span className="cc-bar-track">
            <span className="cc-bar-fill" style={{ width: `${row.pct}%` }} />
          </span>
          <span className="cc-bar-value">{number(row.value)}</span>
        </div>
      ))}
    </div>
  );
}

/** Filed vs completed, each week — grouped bars sharing one scale. */
function Throughput({
  rows,
  sub,
}: {
  rows: Array<{ label: string; created: number; completed: number }>;
  sub: string;
}) {
  const max = Math.max(1, ...rows.flatMap((row) => [row.created, row.completed]));

  return (
    <Panel title="Requests Filed vs. Completed" sub={sub}>
      {rows.length === 0 ? (
        <p className="cc-panel-sub">Nothing in this range.</p>
      ) : (
        <>
          <div className="flex items-end gap-3 overflow-x-auto pb-2" style={{ minHeight: 140 }}>
            {rows.map((row) => (
              <div key={row.label} className="flex shrink-0 flex-col items-center gap-1">
                <div className="flex items-end gap-[3px]" style={{ height: 110 }}>
                  <span
                    title={`${row.created} filed`}
                    style={{
                      width: 12,
                      height: `${Math.max(2, (row.created / max) * 110)}px`,
                      background: 'var(--green-deep)',
                      borderRadius: '3px 3px 0 0',
                    }}
                  />
                  <span
                    title={`${row.completed} completed`}
                    style={{
                      width: 12,
                      height: `${Math.max(2, (row.completed / max) * 110)}px`,
                      background: 'var(--green-bright, #2f9e44)',
                      borderRadius: '3px 3px 0 0',
                    }}
                  />
                </div>
                <span style={{ fontSize: 10.5, color: 'var(--ink-soft)' }}>{row.label}</span>
              </div>
            ))}
          </div>
          <div className="cc-legend">
            <span className="cc-legend-row">
              <span className="cc-legend-dot" style={{ background: 'var(--green-deep)' }} /> Filed
            </span>
            <span className="cc-legend-row">
              <span
                className="cc-legend-dot"
                style={{ background: 'var(--green-bright, #2f9e44)' }}
              />{' '}
              Completed
            </span>
          </div>
        </>
      )}
    </Panel>
  );
}

function ActivityStrip({ rows }: { rows: CommandCenterData['recentActivity'] }) {
  return (
    <Panel title="Recent Activity" sub="who did what, latest first">
      {rows.length === 0 ? (
        <p className="cc-panel-sub">Nothing recorded yet.</p>
      ) : (
        <table className="cc-panel-table">
          <tbody>
            {rows.map((row) => (
              <tr key={row.id}>
                <td>{row.description}</td>
                <td>{row.causerName ?? 'System'}</td>
                <td>{row.createdAt ? forHumans(row.createdAt) : '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </Panel>
  );
}

export function CommandCenter({ data }: { data: CommandCenterData }) {
  const isOffice = data.audience === 'office';
  const isInternal = data.filters.view === 'internal';
  const pagePath = isOffice ? '/reports' : '/admin/dashboard';

  /** Office mode sends a supervisor to the citizen tracking page they can open. */
  const requestUrl = (tracking: string): string =>
    isOffice
      ? `/track/${encodeURIComponent(tracking)}`
      : `/admin/requests?open=${encodeURIComponent(tracking)}`;

  const query = (overrides: Record<string, string | number | null>): string => {
    const params = new URLSearchParams();
    const merged = {
      view: data.filters.view,
      range: data.filters.range,
      document_type: data.filters.document_type,
      ...overrides,
    };

    for (const [key, value] of Object.entries(merged)) {
      if (value !== null && value !== '') {
        params.set(key, String(value));
      }
    }

    return `${pagePath}?${params.toString()}`;
  };

  return (
    <div className="page-shell">
      {/* ── Region A: header + filters ─────────────────────────────────────── */}
      <div className="an-header">
        <div>
          <h2 style={{ fontSize: 18, fontWeight: 700, color: 'var(--green-deep)', margin: 0 }}>
            {isOffice
              ? `${data.office?.name ?? 'All offices'} · Reports`
              : 'City of San Pedro · Command center'}
          </h2>
          <div
            style={{
              fontSize: 11.5,
              color: 'var(--ink-soft)',
              marginTop: 2,
              display: 'flex',
              alignItems: 'center',
              gap: 6,
            }}
          >
            <span className="live-dot" /> live · updated {forHumans(data.updatedAt)}
          </div>
        </div>

        <form method="GET" action={pagePath} className="toolbar">
          <input type="hidden" name="view" value={data.filters.view} />

          <label className="sr-only" htmlFor="range">Range</label>
          <select id="range" name="range" defaultValue={data.filters.range} className="cr-input w-48">
            {RANGES.map((range) => (
              <option key={range} value={range}>
                Last {range} days
              </option>
            ))}
          </select>

          {/* Every internal request shares one type, so the slice is citizen-only. */}
          {isInternal ? null : (
            <>
              <label className="sr-only" htmlFor="document_type">Type</label>
              <select
                id="document_type"
                name="document_type"
                defaultValue={data.filters.document_type ?? ''}
                className="cr-input w-56"
              >
                <option value="">All</option>
                {data.documentTypes.map((type) => (
                  <option key={type} value={type}>
                    {type}
                  </option>
                ))}
              </select>
            </>
          )}

          <button type="submit" className="cr-btn cr-btn-primary cr-btn-sm">Apply</button>

          {data.filters.active ? (
            <Link href={query({ range: 30, document_type: null })} className="cr-btn cr-btn-sm">
              Reset
            </Link>
          ) : null}
        </form>
      </div>

      {/* The two sides. Citizen requests and office-to-office requests used to be
          counted together, so INT- requests sat in the citizen overdue list
          judged by citizen stage clocks. Now each side is read on its own. */}
      <nav className="st-tabs self-start" aria-label="Which requests">
        <Link
          href={query({ view: 'citizen' })}
          className={isInternal ? '' : 'on'}
          aria-current={isInternal ? undefined : 'page'}
        >
          Citizen requests
        </Link>
        <Link
          href={query({ view: 'internal' })}
          className={isInternal ? 'on' : ''}
          aria-current={isInternal ? 'page' : undefined}
        >
          Internal requests
        </Link>
      </nav>

      {isInternal ? <InternalSide data={data} /> : <CitizenSide data={data} requestUrl={requestUrl} />}

      <ActivityStrip rows={data.recentActivity} />
    </div>
  );
}

/* ── The citizen side ───────────────────────────────────────────────────────── */

function CitizenSide({
  data,
  requestUrl,
}: {
  data: CommandCenterData;
  requestUrl: (tracking: string) => string;
}) {
  const kpis = data.kpis!;
  const isOffice = data.audience === 'office';

  const avgNote = [
    kpis.avgProcessing.sample > 0
      ? `From ${number(kpis.avgProcessing.sample)} completed`
      : 'Filing to completion',
    deltaText(kpis.avgProcessing.delta),
  ]
    .filter(Boolean)
    .join(' · ');

  const overdueNote = [
    'Open now, any filing date',
    kpis.atRisk.value > 0 ? `+${number(kpis.atRisk.value)} at risk` : null,
  ]
    .filter(Boolean)
    .join(' · ');

  return (
    <>
      {/* ── Region B: KPI vitals ─────────────────────────────────────────────
          The first four are ONE set: the requests filed in the selected range,
          by where each is now, so they add back to the total. */}
      <p className="cc-tiles-caption">
        Requests filed in the last {data.filters.range} days, by where they are now
        {kpis.denied.value > 0 ? ` · ${number(kpis.denied.value)} denied` : ''}
      </p>

      {/* Three across on a wide screen: six tiles in one row left each too
          narrow for its label and drawing. */}
      <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
        <StaffStatTile
          label="Requests filed"
          value={number(kpis.total.value)}
          tone="deep"
          icon="doc"
          note={deltaText(kpis.total.delta)}
        />
        <StaffStatTile label="Pending" value={number(kpis.pending.value)} tone="mid" icon="hourglass" />
        {/* Not "In Progress": that is one stage of five in the status panel
            below, while this counts every started request. */}
        <StaffStatTile
          label="Being worked on"
          value={number(kpis.inProgress.value)}
          tone="bright"
          icon="clock"
        />
        <StaffStatTile
          label="Completed"
          value={number(kpis.completed.value)}
          tone="brightest"
          icon="check"
          note={deltaText(kpis.completed.delta)}
        />
        <StaffStatTile
          label="Avg. Processing"
          value={fromDays(kpis.avgProcessing.value)}
          tone="deep"
          icon="stopwatch"
          note={avgNote}
        />
        {/* Amber, not one more green, when anything is late. The word carries
            the meaning too, so it never rests on colour alone. */}
        <StaffStatTile
          label="Overdue"
          value={number(kpis.overdue.value)}
          tone={kpis.overdue.value > 0 ? 'amber' : 'bright'}
          icon="overdue"
          note={overdueNote}
        />
      </div>

      {/* Requests that reached no office. Their request type has no handling
          office, so no supervisor was told and they wait unseen — only this
          page can show them. */}
      {(data.unrouted ?? []).length > 0 ? (
        <Panel
          className="cc-alert"
          role="alert"
          title={`${data.unrouted!.length} request${data.unrouted!.length === 1 ? '' : 's'} reached no office`}
          sub="Their request type has no handling office, so no supervisor was notified. Give the type an office, then route these by hand."
        >
          <table className="cc-panel-table">
            <tbody>
              {data.unrouted!.map((row) => (
                <tr key={row.id}>
                  <td>
                    <Link href={requestUrl(row.tracking_number)} className="id-chip">
                      {row.tracking_number}
                    </Link>
                  </td>
                  <td>{row.document_type}</td>
                  <td>{row.created_at ? forHumans(row.created_at) : '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Panel>
      ) : null}

      <div className="grid grid-cols-1 gap-5 xl:grid-cols-2">
        {/* An office compares its resources; the city compares its offices. */}
        {isOffice ? (
          <Panel title="Bookings by Resource" sub="how much each resource is used">
            {(data.bookings ?? []).length === 0 ? (
              <p className="cc-panel-sub">This office reserves no resources.</p>
            ) : (
              <table className="cc-panel-table">
                <thead>
                  <tr>
                    <th>Resource</th>
                    <th>Total</th>
                    <th>Approved</th>
                    <th>Pending</th>
                    <th>Upcoming</th>
                  </tr>
                </thead>
                <tbody>
                  {data.bookings!.map((row) => (
                    <tr key={row.name}>
                      <td>
                        {row.name}
                        {row.is_active ? null : <span className="cc-tag"> inactive</span>}
                      </td>
                      <td>{number(row.total)}</td>
                      <td>{number(row.approved)}</td>
                      <td>{number(row.pending)}</td>
                      <td>{number(row.upcoming)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </Panel>
        ) : (
          <Panel title="Requests by Office" sub="filed in this range · read against the busiest office">
            <Bars rows={data.departmentVolume ?? []} />
          </Panel>
        )}

        <Panel title="Where requests are now" sub="every citizen request, by stage">
          {(data.statusDistribution ?? []).length === 0 ? (
            <p className="cc-panel-sub">No requests yet.</p>
          ) : (
            <div className="cc-legend">
              {data.statusDistribution!.map((row) => (
                <span key={row.status} className="cc-legend-row">
                  <span className={`cc-legend-dot band-${statusBand(row.status as DocumentStatus)}`} />
                  {statusLabel(row.status as DocumentStatus)}
                  <span className="cc-legend-value">{number(row.count)}</span>
                </span>
              ))}
            </div>
          )}
        </Panel>
      </div>

      {/* ── Region D: what needs doing ──────────────────────────────────────── */}
      <Panel
        title="Needs Attention"
        sub={`${(data.atRisk ?? []).length} overdue · worst first · open a row for its details, or notify whoever owns it`}
      >
        {(data.atRisk ?? []).length === 0 ? (
          <p className="cc-panel-sub">Nothing is overdue.</p>
        ) : (
          <table className="cc-panel-table">
            <thead>
              <tr>
                <th>Request</th>
                <th>Stage</th>
                <th>Assigned to</th>
                <th>Over by</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {data.atRisk!.slice(0, 20).map((row) => (
                <tr key={row.id}>
                  <td>
                    <Link href={requestUrl(row.tracking_number)} className="id-chip">
                      {row.tracking_number}
                    </Link>
                  </td>
                  <td>{row.stage}</td>
                  <td>{row.assignee ?? 'Unassigned'}</td>
                  <td>{row.over}</td>
                  {/* A push, not a reassignment — the command center never
                      claims or reroutes another office's work. */}
                  <td>
                    <NotifyButton
                      className="cr-btn cr-btn-sm"
                      label="Notify →"
                      subject={{
                        documentId: row.id,
                        tracking: row.tracking_number,
                        type: row.document_type,
                        stageLabel: row.stage,
                        office: row.office,
                        assignee: row.assignee,
                        over: row.over,
                      }}
                    />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Panel>

      {/* Held past their hold date. Invisible to Needs Attention because a hold
          nulls the stage SLA, so they get their own panel — the exact blind spot
          the check-holds sweep emails about. */}
      {(data.stalledHolds ?? []).length > 0 ? (
        <Panel title="Parked and Past Due" sub="on hold past the date they were due to resume">
          <table className="cc-panel-table">
            <thead>
              <tr>
                <th>Request</th>
                <th>Waiting on</th>
                <th>Assigned to</th>
                <th>Days over</th>
              </tr>
            </thead>
            <tbody>
              {data.stalledHolds!.map((row) => (
                <tr key={row.id}>
                  <td>
                    <Link href={requestUrl(row.tracking_number)} className="id-chip">
                      {row.tracking_number}
                    </Link>
                  </td>
                  <td>{row.blocked_by ?? '—'}</td>
                  <td>{row.assignee ?? 'Unassigned'}</td>
                  <td>{number(row.days_over)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Panel>
      ) : null}

      {/* ── Region C: analytics grid ────────────────────────────────────────── */}
      <div className="grid grid-cols-1 gap-5 xl:grid-cols-2">
        <Throughput rows={data.throughput ?? []} sub="new requests vs. finished, each week" />

        <Panel title="Staff Workload" sub="active + completed · idle staff included">
          {(data.staffWorkload ?? []).length === 0 ? (
            <p className="cc-panel-sub">No staff on this office&rsquo;s roster.</p>
          ) : (
            <table className="cc-panel-table">
              <thead>
                <tr>
                  <th>Staff</th>
                  <th>Active</th>
                  <th>Completed</th>
                </tr>
              </thead>
              <tbody>
                {data.staffWorkload!.map((row) => (
                  <tr key={row.name}>
                    <td>
                      {row.name}
                      {row.overloaded ? <span className="cc-tag"> overloaded</span> : null}
                    </td>
                    <td>{number(row.active)}</td>
                    <td>{number(row.completed)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </Panel>

        <Panel title="Average Time per Stage" sub="avg time in stage">
          <Bars
            rows={(data.timeByStage ?? []).map((row) => ({
              label: row.label,
              value: Math.round(row.hours),
              pct: Math.round(
                (row.hours / Math.max(1, ...(data.timeByStage ?? []).map((r) => r.hours))) * 100,
              ),
            }))}
          />
        </Panel>

        <Panel title="Volume by Request Type" sub="created in window">
          <Bars
            rows={(data.typeBreakdown ?? []).map((row) => ({
              ...row,
              pct: Math.round(
                (row.value / Math.max(1, ...(data.typeBreakdown ?? []).map((r) => r.value))) * 100,
              ),
            }))}
          />
        </Panel>

        <Panel
          title="Requirement Checklist"
          sub="mandatory requirements on citizen requests · all time"
        >
          {data.requirementStats && data.requirementStats.mandatory_total > 0 ? (
            <Bars
              rows={[
                {
                  label: 'Submitted',
                  value: data.requirementStats.submitted,
                  pct: data.requirementStats.submitted_pct,
                },
                {
                  label: 'Verified',
                  value: data.requirementStats.verified,
                  pct: data.requirementStats.verified_pct,
                },
              ]}
            />
          ) : (
            <p className="cc-panel-sub">No mandatory requirements recorded.</p>
          )}
        </Panel>
      </div>
    </>
  );
}

/* ── The internal side ──────────────────────────────────────────────────────── */

function InternalSide({ data }: { data: CommandCenterData }) {
  const kpis = data.internalKpis!;

  return (
    <>
      <p className="cc-tiles-caption">
        Internal requests filed in the last {data.filters.range} days, by where they are now
        {kpis.denied > 0 ? ` · ${number(kpis.denied)} denied` : ''}
      </p>

      <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
        <StaffStatTile
          label="Filed"
          value={number(kpis.filed)}
          tone="deep"
          icon="doc"
          note={
            kpis.filed_prev > 0
              ? `${kpis.filed >= kpis.filed_prev ? '▲' : '▼'} vs ${number(kpis.filed_prev)} before`
              : null
          }
        />
        <StaffStatTile label="Moving" value={number(kpis.moving)} tone="bright" icon="clock" />
        {/* A returned request waits on the office that filed it, with no deadline. */}
        <StaffStatTile label="Returned" value={number(kpis.returned)} tone="mid" icon="hourglass" />
        <StaffStatTile
          label="Completed"
          value={number(kpis.completed)}
          tone="brightest"
          icon="check"
        />
        <StaffStatTile
          label="Avg. Turnaround"
          value={fromDays(kpis.avg_turnaround)}
          tone="deep"
          icon="stopwatch"
          note={kpis.avg_sample > 0 ? `From ${number(kpis.avg_sample)} completed` : 'Filing to done'}
        />
        <StaffStatTile
          label="Overdue"
          value={number(kpis.overdue)}
          tone={kpis.overdue > 0 ? 'amber' : 'bright'}
          icon="overdue"
          note="Past the holding office’s own turnaround"
        />
      </div>

      <div className="grid grid-cols-1 gap-5 xl:grid-cols-2">
        <Panel title="Where they're waiting" sub="open requests per office · bottlenecks first">
          {(data.waitingByOffice ?? []).length === 0 ? (
            <p className="cc-panel-sub">Nothing is waiting.</p>
          ) : (
            <table className="cc-panel-table">
              <thead>
                <tr>
                  <th>Office</th>
                  <th>Waiting</th>
                  <th>Overdue</th>
                  <th>Longest</th>
                </tr>
              </thead>
              <tbody>
                {data.waitingByOffice!.map((row) => (
                  <tr key={row.label}>
                    <td>{row.label}</td>
                    <td>{number(row.count)}</td>
                    <td>{row.overdue > 0 ? <span className="cc-tag">{number(row.overdue)}</span> : '—'}</td>
                    <td>{row.longest}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </Panel>

        <Panel title="Filed by office" sub="who files internal requests, in this range">
          <Bars rows={data.byRequestingOffice ?? []} />
        </Panel>
      </div>

      <Panel
        title="Needs Attention"
        sub={`${(data.internalWaiting ?? []).filter((row) => row.overdue).length} past the holding office’s turnaround`}
      >
        {(data.internalWaiting ?? []).filter((row) => row.overdue).length === 0 ? (
          <p className="cc-panel-sub">Nothing is overdue.</p>
        ) : (
          <table className="cc-panel-table">
            <thead>
              <tr>
                <th>Request</th>
                <th>Held at</th>
                <th>Waited</th>
                <th>Turnaround</th>
              </tr>
            </thead>
            <tbody>
              {data
                .internalWaiting!.filter((row) => row.overdue)
                .slice(0, 20)
                .map((row) => (
                  <tr key={row.id}>
                    <td>
                      <Link href={row.url} className="id-chip">
                        {row.tracking_number}
                      </Link>
                    </td>
                    <td>{row.at}</td>
                    <td>{row.waited}</td>
                    <td>{row.limit_hours ? `${row.limit_hours}h` : '—'}</td>
                  </tr>
                ))}
            </tbody>
          </table>
        )}
      </Panel>

      <div className="grid grid-cols-1 gap-5 xl:grid-cols-2">
        <Throughput
          rows={data.throughput ?? []}
          sub="internal requests filed vs. finished, each week"
        />

        <Panel title="How long each office takes" sub="avg hold before acting, against its own turnaround">
          {(data.officeTurnaround ?? []).length === 0 ? (
            <p className="cc-panel-sub">No hops closed in this range.</p>
          ) : (
            <table className="cc-panel-table">
              <thead>
                <tr>
                  <th>Office</th>
                  <th>Hops</th>
                  <th>Average</th>
                  <th>Turnaround</th>
                </tr>
              </thead>
              <tbody>
                {data.officeTurnaround!.map((row) => (
                  <tr key={row.label}>
                    <td>
                      {row.label}
                      {row.over ? <span className="cc-tag"> over</span> : null}
                    </td>
                    <td>{number(row.hops)}</td>
                    <td>{row.avg}</td>
                    <td>{row.limit}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </Panel>
      </div>
    </>
  );
}
