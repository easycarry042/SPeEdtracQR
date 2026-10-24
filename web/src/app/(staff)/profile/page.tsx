import type { Metadata } from 'next';
import Link from 'next/link';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { requireActor } from '@/lib/auth/actor';
import { db, id } from '@/lib/db';
import { routes } from '@/lib/routes';
import { AvatarForm, DetailsForm, PasswordForm, SignatureForm } from './forms';

export const metadata: Metadata = { title: 'Profile' };

export const dynamic = 'force-dynamic';

/**
 * Port of `profile/edit.blade.php`. Account deletion is deliberately NOT
 * offered: a staff account carries the audit trail of everything it
 * processed, so only an administrator may archive one.
 */
export default async function ProfilePage() {
  const actor = await requireActor();
  const user = await db.users.findUnique({
    where: { id: id(actor.id) },
    select: { name: true, email: true, avatar_path: true, signature_path: true },
  });

  const initials = (user?.name ?? actor.name)
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('');

  return (
    <AuthenticatedPage title="Profile">
      <div className="page-shell page-shell-loose">
        <Card>
          <AvatarForm avatarUrl={user?.avatar_path ? routes.staffAvatar(actor.id) : null} initials={initials} />
        </Card>
        <Card>
          <DetailsForm name={user?.name ?? actor.name} email={user?.email ?? actor.email} />
        </Card>
        <Card>
          <PasswordForm />
        </Card>
        {actor.can('act on internal requests') ? (
          <Card wide>
            <SignatureForm hasSignature={Boolean(user?.signature_path)} />
          </Card>
        ) : null}
        <Card>
          <h2 className="text-lg font-medium text-gray-900">Staff badge</h2>
          <p className="mt-1 text-sm text-gray-600">
            Your badge QR signs endorsement decisions. Print it, keep it with you, and reissue it if the card is lost.
          </p>
          <Link href={routes.profileBadge()} className="cr-btn cr-btn-sm mt-4">
            Open my badge
          </Link>
        </Card>
      </div>
    </AuthenticatedPage>
  );
}

function Card({ children, wide = false }: { children: React.ReactNode; wide?: boolean }) {
  return (
    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
      <div className={wide ? 'max-w-2xl' : 'max-w-xl'}>{children}</div>
    </div>
  );
}
