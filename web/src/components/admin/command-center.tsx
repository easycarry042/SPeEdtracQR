'use client';

import { useState } from 'react';
import Link from 'next/link';
import { StaffStatTile } from '@/components/staff-stat-tile';
import { NotifyButton } from '@/components/review/notify-dialog';
import type { CommandCenterData } from '@/lib/domain/command-center';
import type { Delta } from '@/lib/domain/admin-analytics';
import { RANGES } from '@/lib/domain/command-center';
import {
  band,
  citizenCases,
  DocumentStatus,
  fromLoose,
  label as statusLabel,
} from '@/lib/domain/document-status';
import { fromDays, fromHours } from '@/lib/domain/duration';
import { forHumans, formatNaive } from '@/lib/time';

function pillClass(stage: DocumentStatus): string {
  switch (band(stage)) {
    case 'green':
      return 'p-green';
    case 'brass':
      return 'p-amber';
    case 'warn':
      return 'p-red';
    case 'hold':
      return 'p-hold';
    default:
      return 'p-muted';
  }
}

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

const STAGE_COLORS = [
  '#cdd9d1',
  '#c79a3e',
  '#e0b15a',
  '#46a268',
  '#167a3a',
  '#b45309',
  '#d8920f',
  '#c98a8a',
];

function deltaText(delta: Delta): string | null {
  if (!delta) {
    return null;
  }

  const arrow = delta.direction === 'up' ? '▲' : delta.direction === 'down' ? '▼' : '·';

  return `${arrow} ${delta.value > 0 ? '+' : ''}${delta.value}%`;
}

function buildDonut(segments: Array<[string, number, string]>) {
  const total = segments.reduce((sum, [, val]) => sum + val, 0);
  let acc = 0;
  const slices = segments.map(([label, value, color]) => {
    const pct = total ? Math.round((value / total) * 10000) / 100 : 0;
    const slice = { label, value, color, pct, offset: -1 * acc };
    acc += pct;
    return slice;
  });
  return { total, slices };
}

function ucfirst(value: string): string {
  return value.charAt(0).toUpperCase() + value.slice(1);
}

function ActivityStrip({
  rows,
  isInternal,
  isOffice,
}: {
  rows: CommandCenterData['recentActivity'];
  isInternal: boolean;
  isOffice: boolean;
}) {
  const [showAll, setShowAll] = useState(false);
  const visible = showAll ? rows : rows.slice(0, 5);

  return (
    <section className="cc-panel">
      <div className="cc-panel-head flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="cc-panel-title">Recent Activity</h2>
          <p className="cc-panel-sub">
            Latest {rows.length} changes to {isInternal ? 'internal' : 'citizen'} requests, newest
            first
          </p>
        </div>
        {!isOffice ? (
          <Link href="/admin/audit-log" className="cr-btn cr-btn-sm">
            Full audit log →
          </Link>
        ) : null}
      </div>

      <div className="st-scroll" style={{ marginTop: 14 }}>
        <table className="st-table">
          <thead>
            <tr>
              <th>Time Stamp</th>
              <th>User</th>
              <th>Role</th>
              <th>Action</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr className="st-empty">
                <td colSpan={5}>
                  Nothing has happened yet — activity will appear here as staff work.
                </td>
              </tr>
            ) : (
              visible.map((row) => (
                <tr key={row.id}>
                  <td className="whitespace-nowrap">
                    {row.createdAt ? formatNaive(row.createdAt, 'M j, g:i A') : '—'}
                  </td>
                  <td className="st-name">{row.causerName ?? 'System'}</td>
                  <td>{row.causerRole ?? (row.causerName ? 'Staff' : 'System')}</td>
                  <td className="font-medium text-ink">{row.action}</td>
                  <td>
                    {row.subject ? <span className="mono">{row.subject}</span> : null}
                    {row.detail ? (
                      <span
                        className={`${row.subject ? 'ml-1.5 ' : ''}${
                          row.detail.startsWith('→') ? 'font-medium text-ink' : 'mono'
                        }`}
                      >
                        {row.detail}
                      </span>
                    ) : null}
                    {!row.subject && !row.detail ? '—' : null}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {rows.length > 5 ? (
        <div
          className="st-pager justify-center"
          style={{ borderTop: '1px solid var(--hairline)' }}
        >
          <button
            type="button"
            onClick={() => setShowAll((prev) => !prev)}
            className="st-page-more"
          >
            {showAll ? 'Show the latest 5 only' : `Show all ${rows.length} entries`}
          </button>
        </div>
      ) : null}
    </section>
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

          <div className="field">
            <label htmlFor="range">Range</label>
            <select
              id="range"
              name="range"
              defaultValue={data.filters.range}
              className="cr-input w-48"
              onChange={(e) => e.currentTarget.form?.requestSubmit()}
            >
              {RANGES.map((range) => (
                <option key={range} value={range}>
                  Last {range} days
                </option>
              ))}
            </select>
          </div>

          {/* Every internal request shares one type, so the slice is citizen-only. */}
          {isInternal ? null : (
            <div className="field">
              <label htmlFor="document_type">Type</label>
              <select
                id="document_type"
                name="document_type"
                defaultValue={data.filters.document_type ?? ''}
                className="cr-input w-56"
                onChange={(e) => e.currentTarget.form?.requestSubmit()}
              >
                <option value="">All</option>
                {data.documentTypes.map((type) => (
                  <option key={type} value={type}>
                    {type}
                  </option>
                ))}
              </select>
            </div>
          )}

          <button type="submit" className="cr-btn cr-btn-primary cr-btn-sm">
            Apply
          </button>

          {data.filters.active ? (
            <Link href={query({ range: 30, document_type: null })} className="cr-btn cr-btn-sm">
              Reset
            </Link>
          ) : null}

          {/* The same view, window and type as the page, as a spreadsheet. */}
          <a
            href={query({}).replace(pagePath, `${pagePath}/export`)}
            className="cr-btn cr-btn-sm"
            title="Download these figures as a CSV (opens in Excel)"
          >
            Export CSV
          </a>
        </form>
      </div>

      {/* The two sides. Citizen requests and office-to-office requests used to be
          counted together, so INT- requests sat in the citizen overdue list
          judged by citizen stage clocks. Now each side is read on its own. */}
      <nav className="st-tabs self-start" aria-label="Which requests">
        <Link
          href={query({ view: 'citizen', document_type: null })}
          className={isInternal ? '' : 'on'}
          aria-current={isInternal ? undefined : 'page'}
        >
          Citizen requests
        </Link>
        <Link
          href={query({ view: 'internal', document_type: null })}
          className={isInternal ? 'on' : ''}
          aria-current={isInternal ? 'page' : undefined}
        >
          Internal requests
        </Link>
      </nav>

      {isInternal ? (
        <InternalSide data={data} />
      ) : (
        <CitizenSide data={data} requestUrl={requestUrl} />
      )}

      <ActivityStrip rows={data.recentActivity} isInternal={isInternal} isOffice={isOffice} />
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

  const avgDelta = kpis.avgProcessing.delta;
  const avgNote =
    (kpis.avgProcessing.sample > 0
      ? `From ${number(kpis.avgProcessing.sample)} completed`
      : 'Filing to completion') +
    (avgDelta
      ? ` · ${avgDelta.value}% ${avgDelta.direction === 'up' ? 'slower' : 'faster'}`
      : '');

  const overdueNote = [
    'Open now, any filing date',
    kpis.atRisk.value > 0 ? `+${number(kpis.atRisk.value)} at risk` : null,
  ]
    .filter(Boolean)
    .join(' · ');

  // Map all 8 citizen stages in canonical order so colors and legend rows match Blade.
  const statusMap = new Map(
    (data.statusDistribution ?? []).map((row) => [row.status, row.count]),
  );
  const sd = buildDonut(
    citizenCases().map((stage, i) => [
      statusLabel(stage),
      statusMap.get(stage) ?? 0,
      STAGE_COLORS[i % STAGE_COLORS.length],
    ]),
  );

  const bookings = data.bookings ?? [];
  const bookingMax = Math.max(1, ...bookings.map((b) => b.total));
  const departmentVolume = data.departmentVolume ?? [];
  const atRisk = data.atRisk ?? [];
  const attentionRows = atRisk.slice(0, 12);
  const stalledHolds = data.stalledHolds ?? [];
  const throughput = data.throughput ?? [];
  const tputCreated = throughput.reduce((sum, w) => sum + w.created, 0);
  const tputCompleted = throughput.reduce((sum, w) => sum + w.completed, 0);
  const tputMax = Math.max(1, ...throughput.flatMap((w) => [w.created, w.completed]));
  const staffWorkload = data.staffWorkload ?? [];
  const wlMax = Math.max(1, ...staffWorkload.map((s) => s.completed + s.active));
  const timeByStage = data.timeByStage ?? [];
  const stageTotalHours = timeByStage.reduce((sum, s) => sum + s.hours, 0);
  const stageMaxHours = Math.max(0.01, ...timeByStage.map((s) => s.hours));
  const typeBreakdown = data.typeBreakdown ?? [];
  const typeMax = Math.max(1, ...typeBreakdown.map((t) => t.value));
  const reqStats = data.requirementStats;

  return (
    <>
      {/* ── Region B: KPI vitals ───────────────────────────────────────────── */}
      <p className="cc-tiles-caption" data-tile-caption>
        Requests filed in the last {data.filters.range} days, by where they are now
        {kpis.denied.value > 0 ? ` · ${number(kpis.denied.value)} denied` : ''}
      </p>

      <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
        <StaffStatTile
          label="Requests filed"
          value={number(kpis.total.value)}
          tone="deep"
          icon="doc"
          note={deltaText(kpis.total.delta)}
        />
        <StaffStatTile
          label="Pending"
          value={number(kpis.pending.value)}
          tone="mid"
          icon="hourglass"
        />
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
        <StaffStatTile
          label="Overdue"
          value={number(kpis.overdue.value)}
          tone={kpis.overdue.value > 0 ? 'amber' : 'bright'}
          icon="overdue"
          note={overdueNote}
        />
      </div>

      {/* Unrouted requests alert (city command center only) */}
      {(data.unrouted ?? []).length > 0 ? (
        <section className="cc-panel cc-alert" role="alert">
          <div className="cc-panel-head flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 className="cc-panel-title">
                {data.unrouted!.length} request{data.unrouted!.length === 1 ? '' : 's'} reached no
                office
              </h2>
              <p className="cc-panel-sub">
                Their request type has no handling office, so no supervisor was notified. Give the
                type an office, then route these by hand.
              </p>
            </div>
            <Link href="/admin/request-types" className="cr-btn cr-btn-sm">
              Open Request Types →
            </Link>
          </div>
          <div className="cc-panel-body flex flex-wrap gap-2">
            {data.unrouted!.map((doc) => (
              <Link
                key={doc.id}
                href={requestUrl(doc.tracking_number)}
                className="cc-chip"
              >
                <span className="code">{doc.tracking_number}</span> {doc.document_type}
              </Link>
            ))}
          </div>
        </section>
      ) : null}

      {/* ── The frame's two reading panels ─────────────────────────────────── */}
      <div
        className={`grid grid-cols-1 gap-5${
          !isOffice || bookings.length > 0 ? ' lg:grid-cols-2' : ''
        }`}
      >
        {isOffice ? (
          bookings.length > 0 ? (
            <section className="cc-panel">
              <div className="cc-panel-head">
                <h2 className="cc-panel-title">Bookings by Resource</h2>
                <p className="cc-panel-sub">This office&rsquo;s bookable resources · all time</p>
              </div>
              <div className="cc-panel-body">
                <div className="cc-bars">
                  {bookings.map((resource) => (
                    <div
                      key={resource.name}
                      className={`cc-bar-row${resource.total === 0 ? ' is-idle' : ''}`}
                    >
                      <span className="cc-bar-label">
                        {resource.name}
                        <span className="block text-[11px] text-ink-soft">
                          {resource.pending} pending · {resource.upcoming} upcoming
                        </span>
                      </span>
                      <div className="cc-bar-track">
                        <div
                          className="cc-bar-fill"
                          style={{ width: `${Math.round((resource.total / bookingMax) * 100)}%` }}
                          role="img"
                          aria-label={`${resource.total} bookings, ${resource.pending} awaiting approval`}
                        />
                      </div>
                      <span className="cc-bar-value">
                        {resource.total}
                        {resource.pending > 0 ? (
                          <span className="cc-tag cc-tag-back">{resource.pending} pending</span>
                        ) : null}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            </section>
          ) : null
        ) : (
          <section className="cc-panel">
            <div className="cc-panel-head">
              <h2 className="cc-panel-title">Requests by Office</h2>
              <p className="cc-panel-sub">
                Filed in this period · every office with a citizen service, idle ones included
              </p>
            </div>
            <div className="cc-panel-body">
              {departmentVolume.length === 0 ? (
                <div className="sp-empty">
                  No office handles a citizen service yet. Give each request type an office on the
                  Request Types page.
                </div>
              ) : (
                <div className="cc-bars">
                  {departmentVolume.map((dept) => (
                    <div
                      key={dept.label}
                      className={`cc-bar-row${
                        dept.value === 0 ? ' is-idle' : dept.busiest ? ' is-busiest' : ''
                      }`}
                    >
                      <span className="cc-bar-label">{dept.label}</span>
                      <div className="cc-bar-track">
                        <div
                          className="cc-bar-fill"
                          style={{ width: `${dept.pct}%` }}
                          role="img"
                          aria-label={`${dept.value} document${
                            dept.value === 1 ? '' : 's'
                          } — ${dept.pct}% of the busiest office`}
                        />
                      </div>
                      <span className="cc-bar-value">{number(dept.value)}</span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </section>
        )}

        <section className="cc-panel">
          <div className="cc-panel-head">
            <h2 className="cc-panel-title">Where requests are now</h2>
            <p className="cc-panel-sub">
              The {number(sd.total)} request{sd.total === 1 ? '' : 's'} above, by stage
            </p>
          </div>
          <div className="cc-panel-body">
            <div className="cc-donut">
              <svg
                viewBox="0 0 140 140"
                width="152"
                height="152"
                style={{ flex: 'none' }}
                role="img"
                aria-label="Requests by stage"
              >
                <g transform="rotate(-90 70 70)" fill="none" strokeWidth="22">
                  {sd.total > 0 ? (
                    sd.slices.map((slice) => (
                      <circle
                        key={slice.label}
                        cx="70"
                        cy="70"
                        r="52"
                        pathLength="100"
                        stroke={slice.color}
                        strokeDasharray={`${slice.pct} ${100 - slice.pct}`}
                        strokeDashoffset={slice.offset}
                      />
                    ))
                  ) : (
                    <circle cx="70" cy="70" r="52" stroke="#eef2ef" />
                  )}
                </g>
                <text
                  x="70"
                  y="68"
                  textAnchor="middle"
                  className="mono"
                  fontSize="22"
                  fontWeight="600"
                  fill="#16211b"
                >
                  {number(sd.total)}
                </text>
                <text x="70" y="86" textAnchor="middle" fontSize="10" fill="#5b6b62">
                  requests
                </text>
              </svg>
              <div className="cc-legend">
                {sd.slices.map((slice) => (
                  <div
                    key={slice.label}
                    className={`cc-legend-row${slice.value === 0 ? ' is-empty' : ''}`}
                  >
                    <span className="cc-legend-dot" style={{ background: slice.color }} />
                    <span>{slice.label}</span>
                    <span className="cc-legend-value">
                      {number(slice.value)} · {Math.round(slice.pct)}%
                    </span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </section>
      </div>

      {/* ── Region D: what needs doing ──────────────────────────────────────── */}
      <div className="an-grid">
        <div className="cc-panel an-span-6">
          <div className="cc-panel-head">
            <h2 className="cc-panel-title">Needs Attention</h2>
            <p className="cc-panel-sub">
              {number(atRisk.length)} overdue
              {atRisk.length > attentionRows.length
                ? ` · showing the ${attentionRows.length} most overdue`
                : ''}{' '}
              · open a row for its details, or notify whoever owns it
            </p>
          </div>
          {atRisk.length === 0 ? (
            <div className="cc-panel-body">
              <div className="sp-empty">Nothing overdue — the pipeline is on time.</div>
            </div>
          ) : (
            <div className="table-wrap cc-panel-table">
              <table className="reg">
                <thead>
                  <tr>
                    <th>Tracking</th>
                    <th>Type</th>
                    <th>Stage</th>
                    <th>Assignee</th>
                    <th>Over SLA</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {attentionRows.map((row) => {
                    const detailsUrl = requestUrl(row.tracking_number);
                    const stageEnum = fromLoose(row.status);
                    return (
                      <tr key={row.id}>
                        <td>
                          <Link href={detailsUrl} className="code hover:underline">
                            {row.tracking_number}
                          </Link>
                        </td>
                        <td>{row.document_type}</td>
                        <td>
                          <span className={`pill ${pillClass(stageEnum)}`}>
                            {statusLabel(stageEnum)}
                          </span>
                        </td>
                        <td className="muted">{row.assignee ?? 'Unassigned'}</td>
                        <td
                          className="mono"
                          style={{ color: 'var(--red)', fontWeight: 600, whiteSpace: 'nowrap' }}
                        >
                          +{row.over}
                        </td>
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
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      {/* Stalled holds (full width) */}
      {stalledHolds.length > 0 ? (
        <div className="cc-panel">
          <div className="cc-panel-head">
            <h2 className="cc-panel-title">Parked and Past Due</h2>
            <p className="cc-panel-sub">
              {stalledHolds.length} stalled hold{stalledHolds.length === 1 ? '' : 's'} · SLA paused,
              so these never reach the overdue list
            </p>
          </div>
          <div className="table-wrap cc-panel-table">
            <table className="reg">
              <thead>
                <tr>
                  <th>Tracking</th>
                  <th>Type</th>
                  <th>Waiting on</th>
                  <th>Assignee</th>
                  <th>Held since</th>
                  <th>Past due</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {stalledHolds.map((row) => (
                  <tr key={row.id}>
                    <td>
                      <span className="code">{row.tracking_number}</span>
                    </td>
                    <td>{row.document_type}</td>
                    <td className="muted">{row.blocked_by ? ucfirst(row.blocked_by) : '—'}</td>
                    <td className="muted">{row.assignee ?? 'Unassigned'}</td>
                    <td className="muted">
                      {row.held_since ? formatNaive(row.held_since, 'M d, Y') : '—'}
                    </td>
                    <td className="mono" style={{ color: 'var(--red)', fontWeight: 600 }}>
                      +{row.days_over}d
                    </td>
                    <td>
                      <Link className="cr-btn cr-btn-sm" href={requestUrl(row.tracking_number)}>
                        Open →
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      ) : null}

      {/* ── Region C: analytics grid ────────────────────────────────────────── */}
      <div className="an-grid">
        {/* Throughput (full width) */}
        <div className="cc-panel an-span-6">
          <div className="cc-panel-head">
            <h2 className="cc-panel-title">Requests Filed vs. Completed</h2>
            <p className="cc-panel-sub">new requests vs. finished, each week</p>
          </div>
          <div className="cc-panel-body">
            {tputCreated + tputCompleted === 0 ? (
              <div className="sp-empty">No throughput in this window yet.</div>
            ) : (
              <>
                <p className="chart-summary">
                  <b>{tputCreated}</b> created · <b>{tputCompleted}</b> completed in this period
                </p>
                <div className="vbars vbars-axis" style={{ height: 170 }}>
                  {throughput.map((w) => (
                    <div key={w.label} className="vbar">
                      <div className="grp">
                        <div className="gcol" title={`${w.created} created`}>
                          <span className="num">{w.created || ''}</span>
                          <div
                            className="col pale"
                            style={{
                              height: `${
                                w.created ? Math.max(3, Math.round((w.created / tputMax) * 100)) : 0
                              }%`,
                            }}
                          />
                        </div>
                        <div className="gcol" title={`${w.completed} completed`}>
                          <span className="num">{w.completed || ''}</span>
                          <div
                            className="col"
                            style={{
                              height: `${
                                w.completed
                                  ? Math.max(3, Math.round((w.completed / tputMax) * 100))
                                  : 0
                              }%`,
                            }}
                          />
                        </div>
                      </div>
                      <div className="cap">{w.label}</div>
                    </div>
                  ))}
                </div>
                <div className="legend" style={{ marginTop: 10 }}>
                  <span>
                    <i style={{ background: '#bfe0cb' }} />
                    Created
                  </span>
                  <span>
                    <i style={{ background: '#167a3a' }} />
                    Completed
                  </span>
                </div>
              </>
            )}
          </div>
        </div>

        {/* Staff workload (1/2) */}
        <div className="cc-panel an-span-3">
          <div className="cc-panel-head">
            <h2 className="cc-panel-title">Staff Workload</h2>
            <p className="cc-panel-sub">active + completed · idle staff included</p>
          </div>
          <div className="cc-panel-body" style={{ paddingTop: 6 }}>
            {staffWorkload.length === 0 ? (
              <div className="sp-empty">No staff accounts yet.</div>
            ) : (
              <>
                {staffWorkload.map((s) => (
                  <div key={s.name} className="barrow">
                    <div className="nm">
                      {s.name}{' '}
                      {s.overloaded ? (
                        <span title="Overloaded" style={{ color: 'var(--red)' }}>
                          ⚑
                        </span>
                      ) : null}
                    </div>
                    <div className="track">
                      <div
                        style={{
                          display: 'flex',
                          height: '100%',
                          width: `${Math.round(((s.completed + s.active) / wlMax) * 100)}%`,
                        }}
                      >
                        <span
                          style={{
                            display: 'block',
                            height: '100%',
                            background: 'var(--green)',
                            flex: s.completed,
                          }}
                        />
                        <span
                          style={{
                            display: 'block',
                            height: '100%',
                            background: 'var(--brass)',
                            flex: s.active,
                          }}
                        />
                      </div>
                    </div>
                    <div className="val">{s.completed + s.active || 'idle'}</div>
                  </div>
                ))}
                <div className="legend" style={{ marginTop: 10 }}>
                  <span>
                    <i style={{ background: '#167a3a' }} />
                    Completed
                  </span>
                  <span>
                    <i style={{ background: '#c79a3e' }} />
                    Active
                  </span>
                </div>
              </>
            )}
          </div>
        </div>

        {/* Processing time by stage (1/2) */}
        <div className="cc-panel an-span-3">
          <div className="cc-panel-head">
            <h2 className="cc-panel-title">Average Time per Stage</h2>
            <p className="cc-panel-sub">avg time in stage</p>
          </div>
          <div className="cc-panel-body">
            {stageTotalHours === 0 ? (
              <div className="sp-empty">Not enough stage history yet.</div>
            ) : (
              <div className="vbars vbars-axis" style={{ height: 150 }}>
                {timeByStage.map((stg) => {
                  const isMax = stg.hours >= stageMaxHours && stg.hours > 0;
                  return (
                    <div key={stg.label} className="vbar">
                      <div className="num">{stg.hours ? fromHours(stg.hours) : ''}</div>
                      <div
                        className={`col ${isMax ? 'red' : 'brass'}`}
                        style={{ height: `${Math.round((stg.hours / stageMaxHours) * 100)}%` }}
                      />
                      <div className="cap">{stg.label.slice(0, 10)}</div>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </div>

        {/* Document type breakdown (full width) */}
        <div className="cc-panel an-span-6">
          <div className="cc-panel-head">
            <h2 className="cc-panel-title">Volume by Request Type</h2>
            <p className="cc-panel-sub">created in window</p>
          </div>
          <div className="cc-panel-body">
            {typeBreakdown.length === 0 ? (
              <div className="sp-empty">No documents in this window yet.</div>
            ) : (
              <div className="vbars vbars-axis" style={{ height: 140 }}>
                {typeBreakdown.map((t) => (
                  <div key={t.label} className="vbar">
                    <div className="num">{t.value}</div>
                    <div
                      className="col"
                      style={{ height: `${Math.round((t.value / typeMax) * 100)}%` }}
                    />
                    <div className="cap">{t.label.slice(0, 12)}</div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        {/* Requirement checklist health */}
        <div className="cc-panel an-span-6">
          <div className="cc-panel-head">
            <h2 className="cc-panel-title">Requirement Checklist</h2>
            <p className="cc-panel-sub">mandatory requirements on citizen requests · all time</p>
          </div>
          <div className="cc-panel-body">
            {!reqStats || reqStats.mandatory_total === 0 ? (
              <div className="sp-empty">No mandatory requirements attached to requests yet.</div>
            ) : (
              <div className="cc-bars">
                {(
                  [
                    ['Handed in by the citizen', reqStats.submitted, reqStats.submitted_pct],
                    ['Checked by staff', reqStats.verified, reqStats.verified_pct],
                  ] as const
                ).map(([label, count, pct]) => (
                  <div key={label} className="cc-bar-row">
                    <span className="cc-bar-label">{label}</span>
                    <div className="cc-bar-track">
                      <div
                        className="cc-bar-fill"
                        style={{ width: `${pct}%` }}
                        role="img"
                        aria-label={`${count} of ${reqStats.mandatory_total} (${pct}%)`}
                      />
                    </div>
                    <span className="cc-bar-value">
                      {count} / {reqStats.mandatory_total}
                    </span>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </>
  );
}

/* ── The internal side ──────────────────────────────────────────────────────── */

function InternalSide({ data }: { data: CommandCenterData }) {
  const k = data.internalKpis!;
  const isOffice = data.audience === 'office';
  const filedDelta =
    k.filed_prev > 0
      ? `${k.filed >= k.filed_prev ? '▲ ' : '▼ '}${Math.abs(
          Math.round(((k.filed - k.filed_prev) / k.filed_prev) * 100),
        )}%`
      : k.filed > 0
        ? `▲ +${k.filed}`
        : null;
  const turnaroundNote =
    k.avg_sample > 0 ? `From ${number(k.avg_sample)} completed` : 'Filing to done';

  const internalWaiting = data.internalWaiting ?? [];
  const overdueOpen = internalWaiting.filter((r) => r.overdue).length;
  const attentionRows = internalWaiting.slice(0, 12);
  const waitingByOffice = data.waitingByOffice ?? [];
  const byOfficeMax = Math.max(1, ...waitingByOffice.map((o) => o.count));
  const byRequestingOffice = data.byRequestingOffice ?? [];
  const throughput = data.throughput ?? [];
  const tputCreated = throughput.reduce((sum, w) => sum + w.created, 0);
  const tputCompleted = throughput.reduce((sum, w) => sum + w.completed, 0);
  const tputMax = Math.max(1, ...throughput.flatMap((w) => [w.created, w.completed]));
  const officeTurnaround = data.officeTurnaround ?? [];

  return (
    <>
      <p className="cc-tiles-caption" data-tile-caption>
        Internal requests {isOffice ? 'your office filed or handled — ' : ''}filed in the last{' '}
        {data.filters.range} days, by where they are now
        {k.denied > 0 ? ` · ${number(k.denied)} denied` : ''}
      </p>

      <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
        <StaffStatTile
          label="Requests filed"
          value={number(k.filed)}
          tone="deep"
          icon="doc"
          note={filedDelta}
        />
        <StaffStatTile
          label="Moving through offices"
          value={number(k.moving)}
          tone="mid"
          icon="clock"
        />
        <StaffStatTile
          label="Returned for revision"
          value={number(k.returned)}
          tone="bright"
          icon="hourglass"
        />
        <StaffStatTile
          label="Completed"
          value={number(k.completed)}
          tone="brightest"
          icon="check"
        />
        <StaffStatTile
          label="Avg. Turnaround"
          value={fromDays(k.avg_turnaround)}
          tone="deep"
          icon="stopwatch"
          note={turnaroundNote}
        />
        <StaffStatTile
          label="Overdue at a step"
          value={number(k.overdue)}
          tone={k.overdue > 0 ? 'amber' : 'bright'}
          icon="overdue"
          note="Open now, past the office's turnaround"
        />
      </div>

      <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <section className="cc-panel">
          <div className="cc-panel-head">
            <h2 className="cc-panel-title">Where they&rsquo;re waiting</h2>
            <p className="cc-panel-sub">
              Open requests by the office holding them now, any filing date
            </p>
          </div>
          <div className="cc-panel-body">
            {waitingByOffice.length === 0 ? (
              <div className="sp-empty">No internal request is open right now.</div>
            ) : (
              <div className="cc-bars">
                {waitingByOffice.map((office) => (
                  <div
                    key={office.label}
                    className={`cc-bar-row${office.overdue > 0 ? ' is-late' : ''}`}
                  >
                    <span className="cc-bar-label">
                      {office.label}
                      <span className="block text-[11px] text-ink-soft">
                        longest {office.longest}
                        {office.limit ? ` · turnaround ${office.limit}` : ''}
                      </span>
                    </span>
                    <div className="cc-bar-track">
                      <div
                        className="cc-bar-fill"
                        style={{ width: `${Math.round((office.count / byOfficeMax) * 100)}%` }}
                        role="img"
                        aria-label={`${office.count} open, ${office.overdue} overdue`}
                      />
                    </div>
                    <span className="cc-bar-value">
                      {office.count}
                      {office.overdue > 0 ? (
                        <span className="cc-tag cc-tag-late">{office.overdue} late</span>
                      ) : null}
                    </span>
                  </div>
                ))}
              </div>
            )}
          </div>
        </section>

        <section className="cc-panel">
          <div className="cc-panel-head">
            <h2 className="cc-panel-title">Filed by office</h2>
            <p className="cc-panel-sub">Who filed internal requests in this period</p>
          </div>
          <div className="cc-panel-body">
            {byRequestingOffice.length === 0 ? (
              <div className="sp-empty">No office filed an internal request in this period.</div>
            ) : (
              <div className="cc-bars">
                {byRequestingOffice.map((office) => (
                  <div key={office.label} className="cc-bar-row">
                    <span className="cc-bar-label">{office.label}</span>
                    <div className="cc-bar-track">
                      <div
                        className="cc-bar-fill"
                        style={{ width: `${office.pct}%` }}
                        role="img"
                        aria-label={`${office.value} filed`}
                      />
                    </div>
                    <span className="cc-bar-value">{number(office.value)}</span>
                  </div>
                ))}
              </div>
            )}
          </div>
        </section>
      </div>

      <div className="cc-panel">
        <div className="cc-panel-head">
          <h2 className="cc-panel-title">Needs Attention</h2>
          <p className="cc-panel-sub">
            {number(internalWaiting.length)} open · {number(overdueOpen)} overdue at a step
            {internalWaiting.length > attentionRows.length
              ? ` · showing the ${attentionRows.length} longest waits`
              : ''}
          </p>
        </div>
        {internalWaiting.length === 0 ? (
          <div className="cc-panel-body">
            <div className="sp-empty">No internal request is open right now.</div>
          </div>
        ) : (
          <div className="table-wrap cc-panel-table">
            <table className="reg">
              <thead>
                <tr>
                  <th>Tracking</th>
                  <th>Request</th>
                  <th>Filed by</th>
                  <th>Now with</th>
                  <th>Waiting</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {attentionRows.map((row) => {
                  const title = (row.purpose || row.document_type || 'Internal request').slice(
                    0,
                    48,
                  );
                  return (
                    <tr key={row.id}>
                      <td>
                        <span className="code">{row.tracking_number}</span>
                      </td>
                      <td>{title}</td>
                      <td className="muted">{row.filed_by ?? '—'}</td>
                      <td>{row.at}</td>
                      <td className="whitespace-nowrap">
                        <span className="mono">{row.waited}</span>
                        {row.returned ? (
                          <span className="cc-tag cc-tag-back">Returned</span>
                        ) : row.overdue ? (
                          <span className="cc-tag cc-tag-late">Overdue</span>
                        ) : null}
                      </td>
                      <td>
                        <Link className="cr-btn cr-btn-sm" href={row.url}>
                          Open →
                        </Link>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      <div className="an-grid">
        <div className="cc-panel an-span-6">
          <div className="cc-panel-head">
            <h2 className="cc-panel-title">Requests Filed vs. Completed</h2>
            <p className="cc-panel-sub">internal requests filed vs. finished, each week</p>
          </div>
          <div className="cc-panel-body">
            {tputCreated + tputCompleted === 0 ? (
              <div className="sp-empty">No internal requests filed or finished in this window.</div>
            ) : (
              <>
                <p className="chart-summary">
                  <b>{tputCreated}</b> filed · <b>{tputCompleted}</b> completed in this period
                </p>
                <div className="vbars vbars-axis" style={{ height: 170 }}>
                  {throughput.map((w) => (
                    <div key={w.label} className="vbar">
                      <div className="grp">
                        <div className="gcol" title={`${w.created} filed`}>
                          <span className="num">{w.created || ''}</span>
                          <div
                            className="col pale"
                            style={{
                              height: `${
                                w.created ? Math.max(3, Math.round((w.created / tputMax) * 100)) : 0
                              }%`,
                            }}
                          />
                        </div>
                        <div className="gcol" title={`${w.completed} completed`}>
                          <span className="num">{w.completed || ''}</span>
                          <div
                            className="col"
                            style={{
                              height: `${
                                w.completed
                                  ? Math.max(3, Math.round((w.completed / tputMax) * 100))
                                  : 0
                              }%`,
                            }}
                          />
                        </div>
                      </div>
                      <div className="cap">{w.label}</div>
                    </div>
                  ))}
                </div>
                <div className="legend" style={{ marginTop: 10 }}>
                  <span>
                    <i style={{ background: '#bfe0cb' }} />
                    Filed
                  </span>
                  <span>
                    <i style={{ background: '#167a3a' }} />
                    Completed
                  </span>
                </div>
              </>
            )}
          </div>
        </div>

        <div className="cc-panel an-span-6">
          <div className="cc-panel-head">
            <h2 className="cc-panel-title">How long each office takes</h2>
            <p className="cc-panel-sub">
              Average time from a request reaching an office to its decision · decisions in this
              period
            </p>
          </div>
          {officeTurnaround.length === 0 ? (
            <div className="cc-panel-body">
              <div className="sp-empty">No office acted on an internal request in this period.</div>
            </div>
          ) : (
            <div className="table-wrap cc-panel-table">
              <table className="reg">
                <thead>
                  <tr>
                    <th>Office</th>
                    <th>Decisions</th>
                    <th>Average time</th>
                    <th>Turnaround</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {officeTurnaround.map((office) => (
                    <tr key={office.label}>
                      <td>{office.label}</td>
                      <td className="mono">{office.hops}</td>
                      <td className="mono">{office.avg}</td>
                      <td className="muted mono">{office.limit}</td>
                      <td>
                        <span
                          className={`cc-tag ${office.over ? 'cc-tag-late' : 'cc-tag-ok'}`}
                        >
                          {office.over ? 'Slower than turnaround' : 'Within turnaround'}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </>
  );
}
