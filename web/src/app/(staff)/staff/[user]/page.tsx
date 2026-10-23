import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { requireActor } from '@/lib/auth/actor';
import { db, id } from '@/lib/db';
import { narrate } from '@/lib/domain/activity-narrative';
import { profile, type DocRow, type FeedEntry, type Heatmap } from '@/lib/domain/staff-profile';
import { routes } from '@/lib/routes';
import { forHumans, formatNaive, now } from '@/lib/time';
import { ClaimButton, Composer, FoldedTimeline } from './profile-parts';

export const dynamic = 'force-dynamic';

/** Each profile titled by its person — a row of tabs all saying "Staff Profile" is not tellable apart. */
export async function generateMetadata({ params }: { params: Promise<{ user: string }> }): Promise<Metadata> {
  const userId = Number((await params).user);
  const user = Number.isInteger(userId) && userId > 0
    ? await db.users.findUnique({ where: { id: id(userId) }, select: { name: true } })
    : null;

  return { title: user?.name ?? 'Staff' };
}

/**
 * Port of `StaffProfileController@show` / `staff/profile.blade.php`: the
 * identity header, the owner's worklist and Unclaimed queue, a year of
 * completions, and the Activity / Assigned / Completions tabs.
 */
const TABS = ['activity', 'assigned', 'completions'] as const;

const STATUS: Record<string, [string, string]> = {
  in_progress: ['p-amber', 'In Progress'],
  in_transit: ['p-amber', 'In Progress'],
  in_review: ['p-amber', 'In Review'],
  approved: ['p-green', 'Approved'],
  pending: ['p-muted', 'Pending'],
  rejected: ['p-red', 'Rejected'],
  denied: ['p-red', 'Denied'],
  withdrawn: ['p-muted', 'Withdrawn'],
  returned: ['p-red', 'Returned'],
  completed: ['p-green', 'Completed'],
  on_hold: ['p-hold', 'On Hold'],
};

function StatusBadge({ status }: { status: string }) {
  const [tone, label] = STATUS[status] ?? STATUS.pending;

  return <span className={`pill ${tone}`}>{label}</span>;
}

function Origin({ doc }: { doc: DocRow }) {
  const online = doc.source === 'online';

  return (
    <span className="origin-line">
      <span className={`pill ${online ? 'p-amber' : 'p-muted'}`}>{online ? 'Online' : 'Walk-in'}</span>
      <span className="origin-who">{online ? 'Submitted online by citizen' : `by ${doc.creatorName || '—'}`}</span>
      {doc.createdAt ? <span className="origin-date">· {formatNaive(doc.createdAt, 'M d, Y')}</span> : null}
    </span>
  );
}

function HoldNote({ doc }: { doc: DocRow }) {
  if (!doc.hold) {
    return null;
  }

  return (
    <span className="gs-hold">
      <span>On hold</span>
      {doc.hold.blockedBy ? (
        <>
          <span aria-hidden="true">·</span>
          <span>waiting on {doc.hold.blockedBy}</span>
        </>
      ) : null}
      {doc.hold.until ? (
        <>
          <span aria-hidden="true">·</span>
          <time className="gs-num">until {formatNaive(doc.hold.until, 'M j, Y')}</time>
        </>
      ) : null}
      {/* A hold pauses the SLA clock, so this is the only sign it has overrun its own deadline. */}
      {doc.hold.overdueDays > 0 ? (
        <>
          <span aria-hidden="true">·</span>
          <strong className="gs-num" style={{ fontWeight: 600 }}>
            {doc.hold.overdueDays} days past due
          </strong>
        </>
      ) : null}
      {doc.hold.reason ? (
        <span className="gs-hold-reason">
          {doc.hold.reason.length > 100 ? `${doc.hold.reason.slice(0, 100)}...` : doc.hold.reason}
        </span>
      ) : null}
    </span>
  );
}

function DocLink({ doc, side }: { doc: DocRow; side: React.ReactNode }) {
  return (
    <Link href={`/track/${encodeURIComponent(doc.trackingNumber)}`} className="gs-row">
      <span className="gs-row-main">
        <span className="gs-row-title">{doc.documentType}</span>
        <span className="gs-row-meta">
          <span className="gs-id" translate="no">
            {doc.trackingNumber}
          </span>
          <Origin doc={doc} />
        </span>
        <HoldNote doc={doc} />
      </span>
      <span className="gs-row-side">{side}</span>
    </Link>
  );
}

function Empty({ title, body }: { title: string; body: string }) {
  return (
    <div className="gs-empty">
      <p className="gs-empty-title">{title}</p>
      <p className="gs-empty-body">{body}</p>
    </div>
  );
}

/** Weeks as columns (Sun→Sat), each month labelled over the week its 1st falls in. */
function grid(heatmap: Heatmap) {
  const hmax = Math.max(1, heatmap.max);
  const today = now();
  const cursor = new Date(`${heatmap.since}T00:00:00Z`);
  cursor.setUTCDate(cursor.getUTCDate() - cursor.getUTCDay());
  const end = new Date(today);
  end.setUTCDate(end.getUTCDate() + (6 - end.getUTCDay()));

  const weeks: Array<Array<{ label: string; count: number; level: number; future: boolean }>> = [];
  const months: Record<number, string> = {};
  let seen: string | null = null;

  while (cursor <= end) {
    const week = [];

    for (let i = 0; i < 7; i += 1) {
      const key = cursor.toISOString().slice(0, 10);
      const count = heatmap.counts[key] ?? 0;
      const future = cursor > today;

      if (seen !== key.slice(0, 7) && cursor.getUTCDate() <= 7 && !future) {
        seen = key.slice(0, 7);
        months[weeks.length] = formatNaive(cursor, 'M');
      }

      week.push({
        label: formatNaive(cursor, 'M j, Y'),
        count,
        level: count === 0 ? 0 : Math.min(4, Math.ceil((count / hmax) * 4)),
        future,
      });
      cursor.setUTCDate(cursor.getUTCDate() + 1);
    }

    weeks.push(week);
  }

  return { weeks, months };
}

function FeedCard({ entry }: { entry: FeedEntry }) {
  if (entry.kind === 'system_group') {
    // One document's run of updates on a shared rail; four show, the rest fold.
    return (
      <article className="gs-surface">
        <div className="gs-surface-head">
          <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 8, minWidth: 0 }}>
            <h3 className="gs-surface-title">{entry.document.documentType}</h3>
            <span className="gs-id" translate="no">
              {entry.document.trackingNumber}
            </span>
          </div>
          <StatusBadge status={entry.latestStatus} />
        </div>
        <div className="gs-surface-body">
          <FoldedTimeline
            rows={entry.items.map((item) => ({
              ...narrate(item.body),
              time: item.at ? formatNaive(item.at, 'g:i A') : '—',
              title: item.at ? formatNaive(item.at, 'M j, Y g:i A') : '',
            }))}
          />
          <Link href={`/track/${encodeURIComponent(entry.document.trackingNumber)}`} className="gs-docchip">
            <Origin doc={entry.document} />
          </Link>
        </div>
      </article>
    );
  }

  const row =
    entry.kind === 'manual'
      ? { tone: 'note', headline: entry.type.charAt(0).toUpperCase() + entry.type.slice(1), detail: entry.body }
      : { tone: 'green', headline: `Completed ${entry.document.documentType}`, detail: null };

  return (
    <article className="gs-surface">
      <div className="gs-surface-body">
        <ol className="gs-tl">
          <li className={`gs-tl-row t-${row.tone}`}>
            <time className="gs-tl-time" title={entry.at ? formatNaive(entry.at, 'M j, Y g:i A') : undefined}>
              {entry.at ? formatNaive(entry.at, 'g:i A') : '—'}
            </time>
            <p className="gs-tl-headline">{row.headline}</p>
            {row.detail ? <p className="gs-tl-detail">{row.detail}</p> : null}
            <p className="gs-tl-meta">
              {entry.author}
              {entry.at ? (
                <>
                  {' '}
                  <span aria-hidden="true">·</span> <span className="gs-num">{formatNaive(entry.at, 'M j, Y')}</span>
                </>
              ) : null}
            </p>
          </li>
        </ol>
        {entry.document ? (
          <Link href={`/track/${encodeURIComponent(entry.document.trackingNumber)}`} className="gs-docchip">
            <span className="gs-id" translate="no">
              {entry.document.trackingNumber}
            </span>
            <span>{entry.document.documentType}</span>
            <StatusBadge status={entry.document.status} />
          </Link>
        ) : null}
      </div>
    </article>
  );
}

export default async function StaffProfilePage({
  params,
  searchParams,
}: {
  params: Promise<{ user: string }>;
  searchParams: Promise<{ tab?: string }>;
}) {
  const viewer = await requireActor();
  const userId = Number((await params).user);
  const requested = (await searchParams).tab;
  const tab = (TABS as readonly string[]).includes(requested ?? '') ? (requested as (typeof TABS)[number]) : 'activity';
  const data = Number.isInteger(userId) && userId > 0 ? await profile(userId, viewer, tab) : null;

  if (!data) {
    notFound();
  }

  const { heatmap, kpis } = data;
  const active = data.lastActiveAt !== null && now().getTime() - data.lastActiveAt.getTime() < 5 * 60_000;
  const role = data.role
    ?.split('_')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
  const { weeks, months } = grid(heatmap);
  const plural = (n: number, word: string) => `${word}${n === 1 ? '' : 's'}`;

  const heatLabel =
    heatmap.total === 0
      ? 'Completion activity for the last year: no completions yet.'
      : `${heatmap.total} ${plural(heatmap.total, 'completion')} in the last year${
          heatmap.peakDay
            ? `, the most on ${formatNaive(new Date(`${heatmap.peakDay}T00:00:00Z`), 'F j, Y')} with ${heatmap.peakDayCount}.`
            : '.'
        }`;

  const stats: Array<[string, string | number, string | null]> = [
    ['Most handled', heatmap.busiestType ?? '—', heatmap.busiestType ? `${heatmap.busiestTypeCount} completed` : 'No completions yet'],
    ['Last 30 days', heatmap.last30, heatmap.last30 === 1 ? 'request' : 'requests'],
    [
      'Peak day',
      heatmap.peakDay ? formatNaive(new Date(`${heatmap.peakDay}T00:00:00Z`), 'M j') : '—',
      heatmap.peakDay ? `${heatmap.peakDayCount} in one day` : null,
    ],
    [
      'Peak month',
      heatmap.peakMonth ? formatNaive(new Date(`${heatmap.peakMonth}-01T00:00:00Z`), 'M Y') : '—',
      heatmap.peakMonth ? `${heatmap.peakMonthCount} completed` : null,
    ],
    ['Current streak', heatmap.currentStreak, heatmap.currentStreak === 1 ? 'day running' : 'days running'],
    ['Longest streak', heatmap.longestStreak, heatmap.longestStreak === 1 ? 'day' : 'days'],
  ];

  const slaTone =
    kpis.slaRate === null ? '' : kpis.slaRate >= 90 ? 'gs-badge-green' : kpis.slaRate >= 75 ? 'gs-badge-amber' : 'gs-badge-red';

  const initials = data.user.name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('');

  return (
    <AuthenticatedPage title={data.user.name}>
      <div className="gs-root">
        <header className="gs-pagehead">
          <div className="gs-pagehead-id">
            {data.user.avatarUrl ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={data.user.avatarUrl} alt="" className="gs-avatar object-cover" />
            ) : (
              <span className="gs-avatar">{initials}</span>
            )}
            <div className="gs-pagehead-text">
              <h1>{data.user.name}</h1>
              <div className="gs-pagehead-meta">
                {role ? (
                  <>
                    <span>{role}</span>
                    <span aria-hidden="true">·</span>
                  </>
                ) : null}
                <span className={`gs-status${active ? ' is-live' : ''}`}>
                  {active ? 'Active now' : data.lastActiveAt ? `Last active ${forHumans(data.lastActiveAt)}` : 'No activity yet'}
                </span>
              </div>
            </div>
          </div>
          {data.isOwn ? (
            <div className="gs-pagehead-actions">
              <Link href={routes.profileEdit()} className="gs-btn">
                Edit Profile
              </Link>
            </div>
          ) : null}
        </header>

        <div className="gs-layout">
          <main className="gs-main">
            {data.isOwn ? (
              <section className="gs-surface" aria-labelledby="gs-worklist-h">
                <div className="gs-surface-head">
                  <div>
                    <h2 className="gs-surface-title" id="gs-worklist-h">
                      My Worklist
                    </h2>
                    <p className="gs-surface-sub">Requests assigned to you and still open.</p>
                  </div>
                  <span className="gs-count">{data.worklist.length}</span>
                </div>
                {data.worklist.length > 0 ? (
                  <div className="gs-rows">
                    {data.worklist.map((doc) => (
                      <DocLink key={doc.id} doc={doc} side={<StatusBadge status={doc.status} />} />
                    ))}
                  </div>
                ) : (
                  <Empty
                    title="Your worklist is clear"
                    body="Requests assigned to you appear here. Accept one from Unclaimed below to start work on it."
                  />
                )}
              </section>
            ) : null}

            {data.isOwn && data.unclaimed.length > 0 ? (
              <section className="gs-surface" aria-labelledby="gs-unclaimed-h">
                <div className="gs-surface-head">
                  <div>
                    <h2 className="gs-surface-title" id="gs-unclaimed-h">
                      Unclaimed
                    </h2>
                    <p className="gs-surface-sub">Waiting for someone in your office to take them on.</p>
                  </div>
                  <span className="gs-count">{data.unclaimed.length}</span>
                </div>
                <div className="gs-rows">
                  {data.unclaimed.map((doc) => (
                    <div key={doc.id} className="gs-row">
                      <span className="gs-row-main">
                        <span className="gs-row-title">{doc.documentType}</span>
                        <span className="gs-row-meta">
                          <span className="gs-id" translate="no">
                            {doc.trackingNumber}
                          </span>
                        </span>
                      </span>
                      {viewer.can('accept documents') ? (
                        <ClaimButton documentId={doc.id} label={`${doc.documentType} ${doc.trackingNumber}`} />
                      ) : null}
                    </div>
                  ))}
                </div>
              </section>
            ) : null}

            {/* Full width: 53 weeks across a 300px rail is unreadable. */}
            <section className="gs-surface gs-activity" aria-labelledby="gs-heat-h">
              <div className="gs-surface-head">
                <div>
                  <h2 className="gs-surface-title" id="gs-heat-h">
                    Completion Activity
                  </h2>
                  <p className="gs-surface-sub">
                    <span className="gs-num">{heatmap.total.toLocaleString()}</span> {plural(heatmap.total, 'request')} completed in the last year
                  </p>
                </div>
              </div>
              <div className="gs-surface-body">
                <div className="gs-heat-scroll">
                  <div className="gs-heat-grid">
                    <div className="gs-heat-months" aria-hidden="true">
                      {weeks.map((_, w) => (
                        <span key={w} className="gs-heat-month">
                          {months[w] ?? ''}
                        </span>
                      ))}
                    </div>
                    <div className="gs-heat-body">
                      <div className="gs-heat-days" aria-hidden="true">
                        <span>Mon</span>
                        <span>Wed</span>
                        <span>Fri</span>
                      </div>
                      <div className="gs-heat" role="img" aria-label={heatLabel}>
                        {weeks.map((week, w) => (
                          <div key={w} className="gs-heat-col" aria-hidden="true">
                            {week.map((day) => (
                              <span
                                key={day.label}
                                className={`gs-heat-cell gs-heat-l${day.level}${day.future ? ' gs-heat-future' : ''}`}
                                title={`${day.count} ${plural(day.count, 'completion')} on ${day.label}`}
                              />
                            ))}
                          </div>
                        ))}
                      </div>
                    </div>
                  </div>
                </div>
                <p className="gs-heat-legend" aria-hidden="true">
                  <span>Less</span>
                  {[0, 1, 2, 3, 4].map((level) => (
                    <span key={level} className={`gs-heat-cell gs-heat-l${level}`} />
                  ))}
                  <span>More</span>
                </p>
              </div>
              <dl className="gs-activity-stats">
                {stats.map(([label, value, sub]) => (
                  <div key={label} className="gs-activity-stat">
                    <dt>{label}</dt>
                    <dd className="gs-num">{value}</dd>
                    {sub ? <p>{sub}</p> : null}
                  </div>
                ))}
              </dl>
            </section>

            <nav className="gs-tabs" aria-label="Profile sections">
              {TABS.map((key) => (
                <Link
                  key={key}
                  href={`${routes.staffProfile(data.user.id)}?tab=${key}`}
                  className="gs-tab"
                  aria-current={tab === key ? 'page' : undefined}
                >
                  {key.charAt(0).toUpperCase() + key.slice(1)}
                  {key === 'assigned' ? <span className="gs-count">{kpis.assigned}</span> : null}
                </Link>
              ))}
            </nav>

            {tab === 'activity' ? (
              <>
                {data.isOwn ? <Composer documents={data.ownDocuments} /> : null}
                <div className="gs-feed">
                  {data.feed.length === 0 ? (
                    <div className="gs-surface">
                      <Empty
                        title="No activity yet"
                        body={
                          data.isOwn
                            ? 'Completing a request adds it here automatically. You can also post a note or milestone with the box above.'
                            : 'Once this staff member completes a request or posts a note, it will show up here.'
                        }
                      />
                    </div>
                  ) : (
                    data.feed.map((entry, index) => <FeedCard key={index} entry={entry} />)
                  )}
                </div>
              </>
            ) : tab === 'assigned' ? (
              <section className="gs-surface">
                {data.assigned.length > 0 ? (
                  <div className="gs-rows">
                    {data.assigned.map((doc) => (
                      <DocLink
                        key={doc.id}
                        doc={doc}
                        side={
                          <>
                            {doc.overdue ? <span className="pill p-red">Overdue</span> : null}
                            <StatusBadge status={doc.status} />
                          </>
                        }
                      />
                    ))}
                  </div>
                ) : (
                  <Empty
                    title="Nothing assigned"
                    body={
                      data.isOwn
                        ? 'Requests you accept or that a supervisor assigns to you will be listed here.'
                        : 'This staff member has no open requests right now.'
                    }
                  />
                )}
              </section>
            ) : (
              <section className="gs-surface">
                {data.completions.length > 0 ? (
                  <div className="gs-rows">
                    {data.completions.map((doc) => (
                      <DocLink
                        key={doc.id}
                        doc={doc}
                        side={
                          doc.completedAt ? (
                            <time className="gs-num" style={{ fontSize: 13, color: 'var(--gs-mute)' }}>
                              {formatNaive(doc.completedAt, 'M j, Y')}
                            </time>
                          ) : null
                        }
                      />
                    ))}
                  </div>
                ) : (
                  <Empty
                    title="No completed requests yet"
                    body={
                      data.isOwn
                        ? 'Requests you finish are recorded here, and count toward the stats on this page.'
                        : 'This staff member has not completed a request yet.'
                    }
                  />
                )}
              </section>
            )}
          </main>

          <aside className="gs-aside">
            <section className="gs-surface" aria-labelledby="gs-stats-h">
              <div className="gs-surface-head">
                <h2 className="gs-surface-title" id="gs-stats-h">
                  Stats
                </h2>
              </div>
              <dl className="gs-stats">
                <div className="gs-stat">
                  <dt>Completed</dt>
                  <dd>{kpis.completed.toLocaleString()}</dd>
                </div>
                <div className="gs-stat">
                  <dt>Currently Assigned</dt>
                  <dd>{kpis.assigned.toLocaleString()}</dd>
                </div>
                <div className="gs-stat">
                  <dt>Avg. Processing</dt>
                  <dd>{kpis.avgProcessingDays !== null ? `${kpis.avgProcessingDays} days` : '—'}</dd>
                </div>
                <div className="gs-stat">
                  <dt>On Time</dt>
                  <dd>
                    {kpis.slaRate !== null ? (
                      <span className={`gs-badge ${slaTone} gs-num`}>{kpis.slaRate}% on time</span>
                    ) : (
                      <span className="gs-badge">Not enough data</span>
                    )}
                  </dd>
                </div>
              </dl>
            </section>
            <section className="gs-surface" aria-labelledby="gs-about-h">
              <div className="gs-surface-head">
                <h2 className="gs-surface-title" id="gs-about-h">
                  Details
                </h2>
              </div>
              <dl className="gs-stats">
                {data.user.createdAt ? (
                  <div className="gs-stat">
                    <dt>Member since</dt>
                    <dd>{formatNaive(data.user.createdAt, 'M Y')}</dd>
                  </div>
                ) : null}
                <div className="gs-stat" style={{ flexDirection: 'column', alignItems: 'flex-start', gap: 2 }}>
                  <dt>Internal email</dt>
                  <dd style={{ fontSize: 13, fontWeight: 400, overflowWrap: 'anywhere' }}>
                    <a href={`mailto:${data.user.email}`} className="gs-link">
                      {data.user.email}
                    </a>
                  </dd>
                </div>
              </dl>
            </section>
          </aside>
        </div>
      </div>
    </AuthenticatedPage>
  );
}
