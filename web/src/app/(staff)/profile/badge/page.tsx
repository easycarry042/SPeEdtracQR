import type { Metadata } from 'next';
import QRCode from 'qrcode';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { requireActor } from '@/lib/auth/actor';
import { badgePayload } from '@/lib/domain/profile';
import { BadgeActions } from './badge-actions';

export const metadata: Metadata = { title: 'Staff Badge' };

export const dynamic = 'force-dynamic';

/**
 * Port of `StaffBadgeController@show` — the signer's own printable badge.
 * Always the signed-in user's: nobody can open anyone else's.
 */
function headline(role: string): string {
  return role
    .split('_')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
}

export default async function BadgePage() {
  const actor = await requireActor();
  const svg = await QRCode.toString(await badgePayload(actor), { type: 'svg', margin: 1, width: 220 });

  return (
    <AuthenticatedPage title="Staff Badge">
      <div className="mx-auto w-full max-w-md py-6">
        <div className="panel p-6 text-center print:border-0 print:shadow-none">
          <p className="text-[11px] font-semibold uppercase tracking-wide text-ink-soft">San Pedro · records office</p>
          <p className="mt-1 text-lg font-extrabold text-green-deep">{actor.name}</p>
          <p className="text-[13px] text-ink-soft">
            {headline(actor.roles[0] ?? 'staff')}
            {actor.departmentName ? ` · ${actor.departmentName}` : ''}
          </p>
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img
            src={`data:image/svg+xml;base64,${Buffer.from(svg).toString('base64')}`}
            alt="Staff badge QR code"
            className="mx-auto mt-4 h-56 w-56"
          />
          <p className="mt-3 text-[12px] text-ink-soft">
            Scan this code to sign an endorsement decision. Treat it like a signature — anyone holding it can sign as
            you.
          </p>
        </div>
        <BadgeActions />
      </div>
    </AuthenticatedPage>
  );
}
