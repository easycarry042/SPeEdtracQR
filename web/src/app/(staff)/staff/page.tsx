import type { Metadata } from 'next';
import Link from 'next/link';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { requireActor } from '@/lib/auth/actor';
import { directory } from '@/lib/domain/staff-profile';
import { DutyStatusToggle } from '@/components/shell/duty-status-toggle';
import { routes } from '@/lib/routes';

export const metadata: Metadata = { title: 'Staff' };

export const dynamic = 'force-dynamic';

/**
 * Port of `StaffProfileController@index` / `staff/directory.blade.php` — every
 * account with light accountability figures (open load, completions), for any
 * signed-in staff member.
 */
function headline(value: string): string {
  return value
    .split('_')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
}

function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('');
}

export default async function StaffDirectoryPage({
  searchParams,
}: {
  searchParams: Promise<{ q?: string; role?: string; sort?: string }>;
}) {
  const actor = await requireActor();
  const data = await directory(await searchParams);
  const chip = (role: string | null) =>
    routes.staffIndex(
      Object.fromEntries(
        Object.entries({ role, q: data.q || null, sort: data.sort !== 'name' ? data.sort : null }).filter(([, v]) => v),
      ) as Record<string, string>,
    );
  const filtered = data.q !== '' || data.role !== '' || data.sort !== 'name';

  return (
    <AuthenticatedPage title="Staff">
      <div className="w-full">
        <div className="mb-3 flex flex-wrap items-center justify-end gap-3">
          <form method="GET" action={routes.staffIndex()} className="toolbar">
            {data.role ? <input type="hidden" name="role" value={data.role} /> : null}
            <div className="field">
              <svg fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="11" cy="11" r="7" />
                <path strokeLinecap="round" d="m21 21-4.3-4.3" />
              </svg>
              <input type="text" name="q" defaultValue={data.q} placeholder="Name or email" aria-label="Search staff" />
            </div>
            <div className="field">
              <label htmlFor="dir-sort">Sort</label>
              <select id="dir-sort" name="sort" defaultValue={data.sort} className="w-48">
                <option value="name">Name</option>
                <option value="load">Open load</option>
                <option value="completed">Completions</option>
              </select>
            </div>
            <button type="submit" className="cr-btn cr-btn-primary cr-btn-sm">
              Search
            </button>
            {filtered ? (
              <Link href={routes.staffIndex()} className="cr-btn cr-btn-sm">
                Reset
              </Link>
            ) : null}
          </form>
        </div>

        <div className="dir-chips" aria-label="Filter by role">
          <Link href={chip(null)} className={`dir-chip${data.role === '' ? ' on' : ''}`}>
            All
          </Link>
          {data.roles.map((role) => (
            <Link key={role} href={chip(role)} className={`dir-chip${data.role === role ? ' on' : ''}`}>
              {headline(role)}
            </Link>
          ))}
          <span className="dir-count">
            {data.staff.length} {data.staff.length === 1 ? 'person' : 'people'}
          </span>
        </div>

        <div className="dir-grid">
          {data.staff.length === 0 ? (
            <div className="panel sp-empty" style={{ gridColumn: '1/-1' }}>
              <p>
                No staff found{data.q ? ` for "${data.q}"` : ''}
                {data.role ? ` with the role "${headline(data.role)}"` : ''}.
              </p>
              {data.q || data.role ? (
                <p style={{ marginTop: 4 }}>
                  <Link href={routes.staffIndex()} className="cr-link">
                    Clear filters
                  </Link>
                </p>
              ) : null}
            </div>
          ) : null}

          {data.staff.map((person) => (
            <div key={person.id} className={`panel dir-card${person.overdue > 0 ? ' has-ovd' : ''}`}>
              <Link href={routes.staffProfile(person.id)} className="dir-cover" aria-label={`View ${person.name}'s profile`} />
              <div className="dir-top">
                {person.avatarUrl ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={person.avatarUrl} alt="" className="avatar dir-avatar object-cover" />
                ) : (
                  <span className="avatar dir-avatar">{initials(person.name)}</span>
                )}
                <div className="dir-meta">
                  <div className="dir-name">{person.name}</div>
                  <div className="dir-sub">
                    {person.role ? <span className="sp-role">{headline(person.role)}</span> : null}
                    <span className="dir-active">
                      {person.activeNow ? 'Active now' : person.lastActive ? `Active ${person.lastActive}` : 'No activity yet'}
                    </span>
                  </div>
                </div>
                <div className="ml-auto flex items-center gap-1.5 shrink-0">
                  {actor.isSupervisor() || actor.isSuperAdmin() || actor.id === person.id ? (
                    <DutyStatusToggle
                      userId={person.id}
                      isAvailable={person.isAvailable}
                      size="sm"
                    />
                  ) : (
                    <span className={`pill text-[11px] ${person.isAvailable ? 'p-green' : 'p-amber'}`}>
                      {person.isAvailable ? 'On Duty' : 'On Leave'}
                    </span>
                  )}
                  {person.overdue > 0 ? <span className="pill p-red dir-ovd">{person.overdue} overdue</span> : null}
                </div>
              </div>
              <div className="dir-stats2">
                <div className="dir-stat">
                  <span className="lbl">Open</span>
                  <b>{person.assigned}</b>
                  <span className="dir-bar" aria-hidden="true">
                    <i style={{ width: `${Math.round((person.assigned / data.loadMax) * 100)}%` }} />
                  </span>
                </div>
                <div className="dir-stat">
                  <span className="lbl">Completed</span>
                  <b>{person.completed}</b>
                  {person.month > 0 ? <span className="mo">+{person.month} this month</span> : null}
                </div>
              </div>
              {actor.can('assign documents') ? (
                <Link href={routes.dashboard({ tab: 'inprogress', q: person.name })} className="dir-action">
                  View assignments
                  <svg width="12" height="12" fill="none" stroke="currentColor" strokeWidth="2.2" viewBox="0 0 24 24" aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M5 12h14m-6-6 6 6-6 6" />
                  </svg>
                </Link>
              ) : null}
            </div>
          ))}
        </div>
      </div>
    </AuthenticatedPage>
  );
}
